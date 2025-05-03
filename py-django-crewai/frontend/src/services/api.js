import axios from 'axios';
import { loadApiConfig, getConfig } from '../config';

// Initialize with default configuration
let apiConfig = getConfig();

// Create an axios instance with CSRF token handling
const api = axios.create({
  headers: {
    'Content-Type': 'application/json',
  },
  // Add timeout to prevent hanging requests
  timeout: apiConfig.apiTimeout, // Will use the value from config
});

// Load configuration from backend
loadApiConfig().then(config => {
  // Update the axios instance with the loaded configuration
  api.defaults.timeout = config.apiTimeout;
  console.log(`API timeout set to ${config.apiTimeout}ms`);
}).catch(error => {
  console.error('Error loading API configuration:', error);
});

// Add CSRF token to requests
api.interceptors.request.use(config => {
  const csrfToken = getCookie('csrftoken');
  if (csrfToken) {
    config.headers['X-CSRFToken'] = csrfToken;
  }
  return config;
});

// Add response interceptor for error handling
api.interceptors.response.use(
  response => response,
  error => {
    // Handle network errors
    if (!error.response) {
      console.error('Network error:', error);
      return Promise.reject({
        status: 'error',
        message: 'Network error. Please check your connection and try again.'
      });
    }

    // Handle server errors
    if (error.response.status >= 500) {
      console.error('Server error:', error);
      return Promise.reject({
        status: 'error',
        message: 'Server error. Please try again later.'
      });
    }

    // Handle client errors
    console.error('Request error:', error);
    return Promise.reject({
      status: 'error',
      message: error.response.data.message || 'An error occurred. Please try again.'
    });
  }
);

// Helper to get cookies (for CSRF token)
function getCookie(name) {
  let value = `; ${document.cookie}`;
  let parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
}

// Simple in-memory cache
const cache = {
  theaters: new Map(),
  // Cache expiration time (5 minutes)
  expirationTime: 5 * 60 * 1000,

  // Get item from cache
  get(key) {
    const item = this.theaters.get(key);
    if (!item) return null;

    // Check if item is expired
    if (Date.now() > item.expiry) {
      this.theaters.delete(key);
      return null;
    }

    return item.value;
  },

  // Set item in cache
  set(key, value) {
    const expiry = Date.now() + this.expirationTime;
    this.theaters.set(key, { value, expiry });
  },

  // Clear entire cache
  clear() {
    this.theaters.clear();
  }
};

// API service functions
export const chatApi = {
  getMoviesTheatersAndShowtimes: async (message, location = '') => {
    // Validate message is a string
    if (typeof message !== 'string') {
      console.error('Invalid message type in getMoviesTheatersAndShowtimes:', typeof message, message);
      return Promise.reject({
        status: 'error',
        message: 'Invalid message format'
      });
    }

    if (!message) {
      console.error('Empty message in getMoviesTheatersAndShowtimes');
      return Promise.reject({
        status: 'error',
        message: 'Message cannot be empty'
      });
    }

    try {
      console.log(`[First Run Mode] Getting movies, theaters, and showtimes for: "${message}" (Location: ${location})`);

      const response = await api.post('/api/movies-theaters-showtimes/', {
        message: message,
        location,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        mode: 'first_run' // Explicitly set mode to first_run
      });

      // If the response indicates processing, return that status
      if (response.data && response.data.status === 'processing') {
        console.log('First Run movie recommendations are being processed, will start polling...');
        return {
          status: 'processing',
          message: response.data.message || 'Processing your movie recommendations and theaters...',
          conversation_id: response.data.conversation_id
        };
      }

      if (!response.data || response.data.status !== 'success') {
        throw new Error(response.data?.message || 'Failed to get movies and theaters');
      }

      // Cache theaters for each movie if they exist
      if (response.data.status === 'success' &&
          response.data.recommendations &&
          response.data.recommendations.length > 0) {
        response.data.recommendations.forEach(movie => {
          if (movie.theaters && movie.theaters.length > 0) {
            cache.set(movie.id, {
              status: 'success',
              theaters: movie.theaters
            });
          }
        });
      }

      return response.data;
    } catch (error) {
      console.error('Error getting movies and theaters:', error);
      throw error;
    }
  },

  getMovieRecommendations: async (message) => {
    // Validate message is a string
    if (typeof message !== 'string') {
      console.error('Invalid message type in getMovieRecommendations:', typeof message, message);
      return Promise.reject({
        status: 'error',
        message: 'Invalid message format'
      });
    }

    if (!message) {
      console.error('Empty message in getMovieRecommendations');
      return Promise.reject({
        status: 'error',
        message: 'Message cannot be empty'
      });
    }

    try {
      console.log(`[Casual Mode] Getting movie recommendations for: "${message}"`);

      const response = await api.post('/api/movie-recommendations/', {
        message: message,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        mode: 'casual' // Explicitly set mode to casual
      });

      // If the response indicates processing, start polling
      if (response.data && response.data.status === 'processing') {
        console.log('Movie recommendations are being processed, will start polling...');
        return {
          status: 'processing',
          message: response.data.message || 'Processing your movie recommendations...',
          conversation_id: response.data.conversation_id
        };
      }

      if (!response.data || response.data.status !== 'success') {
        throw new Error(response.data?.message || 'Failed to get movie recommendations');
      }

      return response.data;
    } catch (error) {
      console.error('Error getting movie recommendations:', error);
      throw error;
    }
  },

  // Method for polling movie recommendation status
  pollMovieRecommendations: async () => {
    try {
      console.log('Polling for movie recommendations...');
      const response = await api.get('/api/poll-movie-recommendations/');

      // If the processing is complete, return the results
      if (response.data.status === 'success' && response.data.recommendations) {
        console.log('Polling successful, found recommendations');
        return response.data;
      } else if (response.data.status === 'processing') {
        // Still processing
        return {
          status: 'processing',
          message: response.data.message || 'Still processing your movie recommendations...'
        };
      } else {
        // Error or unexpected status
        console.error('Unexpected response from polling:', response.data);
        throw new Error(response.data?.message || 'Failed to get movie recommendations');
      }
    } catch (error) {
      console.error('Error polling for movie recommendations:', error);
      throw error;
    }
  },

  // Method for polling first run movie recommendations
  pollFirstRunRecommendations: async () => {
    try {
      console.log('Polling for first run movie recommendations...');
      const response = await api.get('/api/poll-first-run-recommendations/');

      // If the processing is complete, return the results
      if (response.data.status === 'success' && response.data.recommendations) {
        console.log('Polling successful, found first run recommendations');
        return response.data;
      } else if (response.data.status === 'processing') {
        // Still processing
        return {
          status: 'processing',
          message: response.data.message || 'Still processing your movie recommendations and theaters...'
        };
      } else {
        // Error or unexpected status
        console.error('Unexpected response from first run polling:', response.data);
        throw new Error(response.data?.message || 'Failed to get movie recommendations');
      }
    } catch (error) {
      console.error('Error polling for first run movie recommendations:', error);
      throw error;
    }
  },

  getTheaters: async (movieId) => {
    try {
      console.log(`Fetching theaters for movie ID: ${movieId}`);

      // Check cache first
      const cachedData = cache.get(movieId);
      if (cachedData) {
        console.log('Using cached theater data');
        return cachedData;
      }

      // If not in cache, make initial request to fetch or start processing
      const response = await api.get(`/api/theaters/${movieId}/`);

      // If response contains a status of "processing", start polling
      if (response.data.status === 'processing') {
        console.log('Theaters are being processed, will start polling...');
        return {
          status: 'processing',
          message: 'Fetching theaters and showtimes...'
        };
      }

      // If we got a direct success response, cache it
      if (response.data.status === 'success' && response.data.theaters) {
        cache.set(movieId, response.data);
      }

      return response.data;
    } catch (error) {
      console.error('Error fetching theaters:', error);
      throw error;
    }
  },

  // Method for polling theater status
  pollTheaterStatus: async (movieId) => {
    try {
      const response = await api.get(`/api/theater-status/${movieId}/`);

      // If the processing is complete, cache the results
      if (response.data.status === 'success' && response.data.theaters) {
        cache.set(movieId, response.data);
      }

      return response.data;
    } catch (error) {
      console.error('Error polling theater status:', error);
      throw error;
    }
  },

  resetConversation: async () => {
    try {
      console.log('Resetting conversation');

      // Clear cache when resetting conversation
      cache.clear();

      await api.get('/api/reset/');
      return { status: 'success' };
    } catch (error) {
      console.error('Error resetting conversation:', error);
      throw error;
    }
  }
};
