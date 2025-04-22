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
    if (!movie) return;

    // If movie already has theaters, no need to fetch
    if (movie.theaters && movie.theaters.length > 0) return;

    try {
      setIsLoadingTheaters(true);
      setTheaterError(null);

      // Fetch theaters from API
      const response = await chatApi.getTheaters(movieId);

      // Update the movie with theaters
      if (response.status === 'success' && response.theaters) {
        setFirstRunMovies(prevMovies =>
          prevMovies.map(m =>
            m.id === movieId
              ? { ...m, theaters: response.theaters }
              : m
          )
        );
      }
    } catch (error) {
      console.error('Error fetching theaters:', error);
      setTheaterError('Failed to load theaters. Please try again.');
    } finally {
      setIsLoadingTheaters(false);
    }
  }, [firstRunMovies]);

  const resetMovieSelection = useCallback(() => {
    setSelectedMovieId(null);
    setSelectedDateIndex(0);
  }, []);

  // Switch between tabs
  const switchTab = useCallback((tab) => {
    setActiveTab(tab);
    resetMovieSelection();
  }, [resetMovieSelection]);

  // Effect to initialize state
  useEffect(() => {
    // Initialize with first-run tab
    setActiveTab('first-run');
  }, []);

  // Effect to fetch theaters when movie is selected
  useEffect(() => {
    if (activeTab === 'first-run' && selectedMovieId) {
      fetchTheatersForMovie(selectedMovieId);
    }
  }, [activeTab, selectedMovieId, fetchTheatersForMovie]);

  // Return the context provider
  return (
    <AppContext.Provider value={{
      // Tab state
      activeTab, switchTab,

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
      isLoadingTheaters,
      theaterError,
      fetchTheatersForMovie,

      // UI state
      loading, setLoading,
      progress, setProgress,
      location, setLocation
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
