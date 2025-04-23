import React, { createContext, useState, useContext, useCallback, useEffect } from 'react';
import { chatApi } from '../services/api';

// Create the context
const AppContext = createContext(null);

// Provider component
export function AppProvider({ children }) {
  // Tabs state
  const [activeTab, setActiveTab] = useState('first-run');

  // Message state
  const [firstRunMessages, setFirstRunMessages] = useState([]);
  const [casualMessages, setCasualMessages] = useState([]);

  // Movie state
  const [firstRunMovies, setFirstRunMovies] = useState([]);
  const [casualMovies, setCasualMovies] = useState([]);
  const [selectedMovieId, setSelectedMovieId] = useState(null);
  const [selectedDateIndex, setSelectedDateIndex] = useState(0);

  // UI state
  const [loading, setLoading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [location, setLocation] = useState('');
  const [requestStage, setRequestStage] = useState('idle'); // idle, sending, searching, analyzing, theaters, complete
  const [isLoadingLocation, setIsLoadingLocation] = useState(false);

  // Loading states
  const [isLoadingTheaters, setIsLoadingTheaters] = useState(false);
  const [theaterError, setTheaterError] = useState(null);

  // Helper functions
  const selectMovie = useCallback((movieId) => {
    console.log('Selecting movie:', movieId);
    setSelectedMovieId(movieId);
    setSelectedDateIndex(0); // Reset date selection when movie changes

    // If we're in first run mode, fetch theaters for this movie
    if (activeTab === 'first-run' && movieId) {
      fetchTheatersForMovie(movieId);
    }
  }, [activeTab]);

  // Fetch theaters for a movie
  const fetchTheatersForMovie = useCallback(async (movieId) => {
    // Skip if no movie ID
    if (!movieId) return;

    // Find the movie in our state
    const movie = firstRunMovies.find(m => m.id === movieId);
    if (!movie) {
      console.log(`Movie with ID ${movieId} not found in firstRunMovies`);
      return;
    }

    // If movie already has theaters, no need to fetch
    if (movie.theaters && movie.theaters.length > 0) {
      console.log(`Movie ${movie.title} already has theaters, skipping fetch`);
      return;
    }

    try {
      console.log(`Fetching theaters for movie: ${movie.title} (ID: ${movieId})`);
      setIsLoadingTheaters(true);
      setTheaterError(null);

      // Fetch theaters from API
      const response = await chatApi.getTheaters(movieId);
      console.log('Theater API response:', response);

      // Update the movie with theaters
      if (response.status === 'success' && response.theaters) {
        console.log(`Found ${response.theaters.length} theaters for movie: ${movie.title}`);
        setFirstRunMovies(prevMovies =>
          prevMovies.map(m =>
            m.id === movieId
              ? { ...m, theaters: response.theaters }
              : m
          )
        );
      } else if (response.status === 'processing') {
        // If theaters are still being processed, poll for updates
        console.log('Theaters are still being processed, starting polling');
        let attempts = 0;
        const maxAttempts = 10;
        const pollInterval = 2000; // 2 seconds

        const poll = async () => {
          if (attempts >= maxAttempts) {
            console.log('Max polling attempts reached');
            setTheaterError('Unable to retrieve theater information. Please try again later.');
            return;
          }

          attempts++;
          console.log(`Polling attempt ${attempts}/${maxAttempts}`);

          try {
            const pollResponse = await chatApi.pollTheaterStatus(movieId);

            if (pollResponse.status === 'success' && pollResponse.theaters) {
              console.log(`Polling successful, found ${pollResponse.theaters.length} theaters`);
              setFirstRunMovies(prevMovies =>
                prevMovies.map(m =>
                  m.id === movieId
                    ? { ...m, theaters: pollResponse.theaters }
                    : m
                )
              );
              return; // Exit polling once we get results
            } else if (pollResponse.status === 'processing') {
              // Continue polling
              setTimeout(poll, pollInterval);
            } else {
              console.log('Polling returned unexpected status:', pollResponse.status);
              setTheaterError('Failed to load theaters. Please try again.');
            }
          } catch (pollError) {
            console.error('Error while polling for theaters:', pollError);
            setTheaterError('Failed to load theaters. Please try again.');
          }
        };

        // Start polling
        setTimeout(poll, pollInterval);
      }
    } catch (error) {
      console.error('Error fetching theaters:', error);
      setTheaterError('Failed to load theaters. Please try again.');
    } finally {
      setIsLoadingTheaters(false);
    }
  }, [firstRunMovies]);

  const resetMovieSelection = useCallback(() => {
    console.log('Resetting movie selection');
    setSelectedMovieId(null);
    setSelectedDateIndex(0);
  }, []);

  // Switch between tabs
  const switchTab = useCallback((tab) => {
    if (tab === activeTab) {
      console.log(`Already on ${tab} tab, no switch needed`);
      return;
    }

    console.log(`Switching tab from ${activeTab} to ${tab}`);
    setActiveTab(tab);
    resetMovieSelection();

    // Cancel any ongoing loading state when switching tabs
    if (loading) {
      console.log('Canceling ongoing loading state due to tab switch');
      setLoading(false);
      setProgress(0);
      setRequestStage('idle');
    }
  }, [resetMovieSelection, activeTab, loading]);

  // Helper to convert UI tab name to backend mode
  const getBackendMode = useCallback((tab) => {
    return tab === 'casual-viewing' ? 'casual' : 'first_run';
  }, []);

  // Effect to initialize state
  useEffect(() => {
    // Initialize with first-run tab
    setActiveTab('first-run');
    console.log('AppContext initialized with first-run tab');
  }, []);

  // Effect to fetch theaters when movie is selected
  useEffect(() => {
    if (activeTab === 'first-run' && selectedMovieId) {
      console.log(`Effect triggered: activeTab=${activeTab}, selectedMovieId=${selectedMovieId}`);
      fetchTheatersForMovie(selectedMovieId);
    }
  }, [activeTab, selectedMovieId, fetchTheatersForMovie]);

  // Effect to log tab changes for debugging
  useEffect(() => {
    console.log(`Active tab changed to: ${activeTab}`);
  }, [activeTab]);

  // Return the context provider
  return (
    <AppContext.Provider value={{
      // Tab state
      activeTab, switchTab,
      getBackendMode, // Add this to explicitly export the helper function

      // Message state
      firstRunMessages, setFirstRunMessages,
      casualMessages, setCasualMessages,

      // Movie state
      firstRunMovies, setFirstRunMovies,
      casualMovies, setCasualMovies,
      selectedMovieId, selectMovie,
      selectedDateIndex, setSelectedDateIndex,
      resetMovieSelection,

      // Theater state
      isLoadingTheaters, setIsLoadingTheaters,
      theaterError, setTheaterError,
      fetchTheatersForMovie,

      // UI state
      loading, setLoading,
      progress, setProgress,
      location, setLocation,
      requestStage, setRequestStage,
      isLoadingLocation, setIsLoadingLocation
    }}>
      {children}
    </AppContext.Provider>
  );
}

// Custom hook for using the context
export function useAppContext() {
  const context = useContext(AppContext);
  if (context === null) {
    throw new Error('useAppContext must be used within an AppProvider');
  }
  return context;
}
