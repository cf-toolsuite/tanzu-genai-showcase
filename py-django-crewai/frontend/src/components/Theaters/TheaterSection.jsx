import React, { useMemo, useEffect, useRef, useState } from 'react';
import { useAppContext } from '../../context/AppContext';
import { chatApi } from '../../services/api';
import DateSelector from './DateSelector';
import TheaterList from './TheaterList';
import ProgressIndicator from '../Chat/ProgressIndicator';

function TheaterSection() {
  const {
    firstRunMovies,
    selectedMovieId,
    selectedDateIndex,
    isLoadingTheaters,
    theaterError,
    fetchTheatersForMovie,
    setFirstRunMovies,
    setIsLoadingTheaters,
    setTheaterError
  } = useAppContext();

  // Local state for polling
  const [pollingStatus, setPollingStatus] = useState('');
  const [pollingProgress, setPollingProgress] = useState(0);
  const [pollingMessage, setPollingMessage] = useState('');
  const pollingIntervalRef = useRef(null);
  const pollingTimeoutRef = useRef(null);
  const pollingDelayRef = useRef(2000); // Start with 2 second delay
  const pollingAttemptsRef = useRef(0);
  
  // Find selected movie
  const selectedMovie = useMemo(() => {
    if (!selectedMovieId) return null;
    return firstRunMovies.find(movie => movie.id === selectedMovieId);
  }, [firstRunMovies, selectedMovieId]);
  
  // Get today's date and the selected date
  const today = new Date();
  const selectedDate = new Date(today);
  selectedDate.setDate(today.getDate() + selectedDateIndex);
  const selectedDateStr = selectedDate.toISOString().split('T')[0];

  // Find theaters with showtimes for the selected date
  const theatersForDate = useMemo(() => {
    if (!selectedMovie || !selectedMovie.theaters) return [];

    return selectedMovie.theaters
      .map(theater => {
        // Filter showtimes for the selected date
        const showtimesForDate = theater.showtimes.filter(showtime => {
          if (!showtime.start_time) return false;
          const date = new Date(showtime.start_time);
          return date.toISOString().split('T')[0] === selectedDateStr;
        });

        if (showtimesForDate.length === 0) return null;

        // Group showtimes by format
        const showtimesByFormat = {};
        showtimesForDate.forEach(showtime => {
          const format = showtime.format || 'Standard';
          if (!showtimesByFormat[format]) {
            showtimesByFormat[format] = [];
          }
          showtimesByFormat[format].push(showtime.start_time);
        });

        return {
          ...theater,
          showtimesByFormat
        };
      })
      .filter(Boolean) // Remove null theaters
      .sort((a, b) => (a.distance_miles || 0) - (b.distance_miles || 0));
  }, [selectedMovie, selectedDateStr]);

  // Function to poll for theater status
  const pollTheaterStatus = async () => {
    if (!selectedMovieId) return;

    try {
      pollingAttemptsRef.current += 1;
      console.log(`Polling attempt ${pollingAttemptsRef.current} for theaters for movie ${selectedMovieId}`);
      
      // Update progress based on attempts (max 90%)
      const newProgress = Math.min(pollingAttemptsRef.current * 5 + 30, 90);
      setPollingProgress(newProgress);
      
      const response = await chatApi.pollTheaterStatus(selectedMovieId);
      
      if (response.status === 'success') {
        console.log('Theater polling complete, data received');
        
        // Update the movie in the firstRunMovies array
        setFirstRunMovies(prevMovies =>
          prevMovies.map(m =>
            m.id === selectedMovieId
              ? { ...m, theaters: response.theaters }
              : m
          )
        );
        
        // Clear the polling interval and show completion
        clearInterval(pollingIntervalRef.current);
        clearTimeout(pollingTimeoutRef.current);
        pollingIntervalRef.current = null;
        pollingTimeoutRef.current = null;
        
        setPollingProgress(100);
        setPollingStatus('complete');
        setPollingMessage('Theaters loaded successfully!');
        
        // Clear loading state after a short delay
        setTimeout(() => {
          setPollingStatus('');
          setIsLoadingTheaters(false);
        }, 1000);
      }
      else if (response.status === 'processing') {
        // Still processing, continue polling
        setPollingStatus('processing');
        setPollingMessage(response.message || 'Still searching for theaters...');
        
        // Gradually increase polling delay up to 5 seconds
        pollingDelayRef.current = Math.min(pollingDelayRef.current * 1.2, 5000);
      }
      else if (response.status === 'error') {
        // Handle error
        setTheaterError(response.message || 'Error loading theaters');
        clearInterval(pollingIntervalRef.current);
        clearTimeout(pollingTimeoutRef.current);
        setIsLoadingTheaters(false);
      }
    } catch (error) {
      console.error('Error during polling:', error);
      
      // If we've tried more than 15 times (about 30+ seconds), stop polling
      if (pollingAttemptsRef.current > 15) {
        clearInterval(pollingIntervalRef.current);
        clearTimeout(pollingTimeoutRef.current);
        setTheaterError('Timed out while fetching theaters. Please try again.');
        setIsLoadingTheaters(false);
      }
    }
  };

  // Effect to start polling when a movie is selected
  useEffect(() => {
    // Skip if no movie is selected
    if (!selectedMovieId) return;
    
    // Skip if movie already has theaters
    if (selectedMovie?.theaters?.length > 0) return;
    
    // Skip if already loading or error state
    if (isLoadingTheaters || theaterError) return;
    
    console.log('Starting theater polling for movie:', selectedMovieId);
    setIsLoadingTheaters(true);
    setPollingStatus('starting');
    setPollingMessage('Initializing theater search...');
    setPollingProgress(10);
    pollingAttemptsRef.current = 0;
    pollingDelayRef.current = 2000; // Reset delay
    
    // Clear any existing polling
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }
    
    if (pollingTimeoutRef.current) {
      clearTimeout(pollingTimeoutRef.current);
      pollingTimeoutRef.current = null;
    }
    
    // Start initial polling after a short delay
    pollingTimeoutRef.current = setTimeout(() => {
      pollTheaterStatus();
      
      // Set up recurring polling
      pollingIntervalRef.current = setInterval(pollTheaterStatus, pollingDelayRef.current);
      
      // Set a timeout to stop polling after 2 minutes to prevent infinite polling
      pollingTimeoutRef.current = setTimeout(() => {
        if (pollingIntervalRef.current) {
          clearInterval(pollingIntervalRef.current);
          setTheaterError('Request took too long. Please try again later.');
          setIsLoadingTheaters(false);
        }
      }, 2 * 60 * 1000); // 2 minutes max polling
    }, 500);
    
    // Cleanup function
    return () => {
      if (pollingIntervalRef.current) {
        clearInterval(pollingIntervalRef.current);
      }
      if (pollingTimeoutRef.current) {
        clearTimeout(pollingTimeoutRef.current);
      }
    };
  }, [selectedMovieId, selectedMovie, isLoadingTheaters, theaterError, setFirstRunMovies, setIsLoadingTheaters, setTheaterError]);


  // If no movie is selected or not in first run mode
  if (!selectedMovie) {
    return (
      <div className="content-wrapper">
        <div className="section-header d-flex justify-content-between align-items-center">
          <h4 className="mb-0">Nearby Theaters</h4>
        </div>
        <div className="theaters-outer-wrapper">
          <div className="text-center text-muted mt-4 py-4">
            <i className="bi bi-film me-2 fs-4"></i>
            <p className="mt-3">Select a movie above to see available theaters and showtimes</p>
          </div>
        </div>
      </div>
    );
  }

  // If loading theaters
  if (isLoadingTheaters) {
    return (
      <div className="content-wrapper">
        <div className="section-header d-flex justify-content-between align-items-center">
          <h4 className="mb-0">Nearby Theaters</h4>
          <span className="text-muted small">Loading theaters...</span>
        </div>
        <div className="theaters-outer-wrapper">
          <ProgressIndicator 
            progress={pollingStatus ? pollingProgress : 70} 
            message={pollingStatus ? pollingMessage : `Finding theaters showing "${selectedMovie.title}"...`} 
          />
        </div>
      </div>
    );
  }

  // If there was an error loading theaters
  if (theaterError) {
    return (
      <div className="content-wrapper">
        <div className="section-header d-flex justify-content-between align-items-center">
          <h4 className="mb-0">Nearby Theaters</h4>
          <button
            className="btn btn-sm btn-outline-danger"
            onClick={() => fetchTheatersForMovie(selectedMovieId)}
          >
            <i className="bi bi-arrow-repeat me-1"></i>
            Retry
          </button>
        </div>
        <div className="theaters-outer-wrapper">
          <div className="alert alert-danger mt-3" role="alert">
            <i className="bi bi-exclamation-triangle-fill me-2"></i>
            {theaterError}
          </div>
        </div>
      </div>
    );
  }

  // If movie has no theaters
  if (!selectedMovie.theaters || selectedMovie.theaters.length === 0) {
    return (
      <div className="content-wrapper">
        <div className="section-header d-flex justify-content-between align-items-center">
          <h4 className="mb-0">Nearby Theaters</h4>
        </div>
        <div className="theaters-outer-wrapper">
          <div className="text-center text-muted mt-4 py-4">
            <i className="bi bi-calendar-x me-2 fs-4"></i>
            <p className="mt-3">
              No theaters are currently showing "{selectedMovie.title}".
              <br />Please check back later or select a different movie.
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="content-wrapper">
      <div className="section-header d-flex justify-content-between align-items-center">
        <h4 className="mb-0">Nearby Theaters</h4>
        {selectedMovie?.theaters?.length > 0 && (
          <span className="text-muted small">
            {selectedMovie.theaters.length} theater{selectedMovie.theaters.length !== 1 ? 's' : ''} found
          </span>
        )}
      </div>

      <div className="theaters-outer-wrapper">
        <DateSelector />

        {theatersForDate.length === 0 ? (
          <div className="text-center text-muted mt-4 py-4">
            <i className="bi bi-calendar-x me-2 fs-4"></i>
            <p className="mt-3">
              No showtimes available for "{selectedMovie.title}"
              <br />on {selectedDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}.
            </p>
          </div>
        ) : (
          <TheaterList theaters={theatersForDate} />
        )}
      </div>
    </div>
  );
}

export default TheaterSection;
