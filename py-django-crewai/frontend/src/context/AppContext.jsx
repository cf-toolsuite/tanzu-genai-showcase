import React, { createContext, useState, useContext, useCallback, useEffect } from 'react';
import { chatApi } from '../services/api';
import { getConfig } from '../config';

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
  const [isProcessing, setIsProcessing] = useState(false); // Global state to track if any request is being processed

  // Loading states
  const [isLoadingTheaters, setIsLoadingTheaters] = useState(false);
  const [theaterError, setTheaterError] = useState(null);

  // Helper functions
  const selectMovie = useCallback((movieId) => {
    console.log('Selecting movie:', movieId);

    // Reset loading states when changing movies
    setTheaterError(null);
    setIsLoadingTheaters(false);

    // Update selected movie and reset date index
    setSelectedMovieId(movieId);
    setSelectedDateIndex(0); // Reset date selection when movie changes

    // If we're in first run mode, ensure theaters are initialized for this movie
    if (activeTab === 'first-run' && movieId) {
      // Check if the movie already has its theaters property initialized
      const movie = firstRunMovies.find(m => m.id === movieId);
      if (movie && movie.theaters === undefined) {
        console.log(`Movie ${movieId} theaters not initialized yet, will fetch theaters`);
        fetchTheatersForMovie(movieId);
      } else {
        console.log(`Movie ${movieId} already has theaters information, no need to fetch`);
      }
    }
  }, [activeTab, firstRunMovies]);

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

    // If movie already has theaters property defined (even empty array), no need to fetch
    if (movie.theaters !== undefined) {
      console.log(`Movie ${movie.title} already has theaters data (${movie.theaters.length} theaters), skipping fetch`);
      return;
    }

    try {
      // Reset error state
      setTheaterError(null);
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
        setIsLoadingTheaters(false);
      } else if (response.status === 'processing') {
        // If theaters are still being processed, poll for updates
        console.log('Theaters are still being processed, starting polling');
        let attempts = 0;
        const maxAttempts = 5; // Reduced from 10 to 5
        const pollInterval = 2000; // 2 seconds

        const poll = async () => {
          if (attempts >= maxAttempts) {
            console.log('Max polling attempts reached, assuming no theaters available');
            // Instead of showing an error, just set empty theaters array
            setFirstRunMovies(prevMovies =>
              prevMovies.map(m =>
                m.id === movieId
                  ? { ...m, theaters: [] }
                  : m
              )
            );
            setIsLoadingTheaters(false);
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
              setIsLoadingTheaters(false);
              return; // Exit polling once we get results
            } else if (pollResponse.status === 'processing') {
              // Continue polling
              setTimeout(poll, pollInterval);
            } else {
              console.log('Polling returned unexpected status:', pollResponse.status);
              // Instead of showing an error, just set empty theaters array
              setFirstRunMovies(prevMovies =>
                prevMovies.map(m =>
                  m.id === movieId
                    ? { ...m, theaters: [] }
                    : m
                )
              );
              setIsLoadingTheaters(false);
            }
          } catch (pollError) {
            console.error('Error while polling for theaters:', pollError);
            // Instead of showing an error, just set empty theaters array
            setFirstRunMovies(prevMovies =>
              prevMovies.map(m =>
                m.id === movieId
                  ? { ...m, theaters: [] }
                  : m
              )
            );
            setIsLoadingTheaters(false);
          }
        };

        // Start polling
        setTimeout(poll, pollInterval);
      }
    } catch (error) {
      console.error('Error fetching theaters:', error);
      // Instead of showing an error, just set empty theaters array
      setFirstRunMovies(prevMovies =>
        prevMovies.map(m =>
          m.id === movieId
            ? { ...m, theaters: [] }
            : m
        )
      );
      setIsLoadingTheaters(false);
    }
  }, [firstRunMovies]);

  const resetMovieSelection = useCallback(() => {
    console.log('Resetting movie selection');
    setSelectedMovieId(null);
    setSelectedDateIndex(0);
  }, []);

  // Check if the application is in a processing state
  const checkIsProcessing = useCallback(() => {
    return loading || requestStage !== 'idle' || isProcessing;
  }, [loading, requestStage, isProcessing]);

  // Switch between tabs
  const switchTab = useCallback((tab) => {
    // Don't allow tab switching during processing
    if (checkIsProcessing()) {
      console.log('Tab switch prevented: Application is currently processing a request');
      return;
    }

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
  }, [resetMovieSelection, activeTab, loading, checkIsProcessing]);

  // Helper to convert UI tab name to backend mode
  const getBackendMode = useCallback((tab) => {
    return tab === 'casual-viewing' ? 'casual' : 'first_run';
  }, []);

  // Effect to initialize state based on feature flags
  useEffect(() => {
    // Check if First Run mode is enabled
    const config = getConfig();
    const isFirstRunEnabled = config.features?.enableFirstRunMode !== false;

    if (isFirstRunEnabled) {
      // If First Run Mode is enabled, initialize with first-run tab
      setActiveTab('first-run');
      console.log('AppContext initialized with first-run tab');
    } else {
      // If First Run Mode is disabled, initialize with casual-viewing tab
      setActiveTab('casual-viewing');
      console.log('First Run Mode disabled, AppContext initialized with casual-viewing tab');
    }
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
      isLoadingLocation, setIsLoadingLocation,
      isProcessing, setIsProcessing,
      checkIsProcessing
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
