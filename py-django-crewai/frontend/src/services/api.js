import axios from 'axios';

// Create an axios instance with CSRF token handling
const api = axios.create({
  headers: {
    'Content-Type': 'application/json',
  },
  // Add timeout to prevent hanging requests
  timeout: 60000, // 60 seconds
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
  sendMessage: async (message, isFirstRun, location = '') => {
    try {
      console.log(`Sending message: "${message}" (First Run: ${isFirstRun}, Location: ${location})`);

      const response = await api.post('/send-message/', {
        message,
        first_run_filter: isFirstRun,
        location,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
      });

      // If this is a successful response with recommendations, cache theaters
      if (response.data.status === 'success' &&
          response.data.recommendations &&
          response.data.recommendations.length > 0) {

        // Cache theaters for each movie if they exist
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
      console.error('Error sending message:', error);
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

      // If not in cache, fetch from API
      const response = await api.get(`/get-theaters/${movieId}/`);

      // Cache the response
      if (response.data.status === 'success' && response.data.theaters) {
        cache.set(movieId, response.data);
      }

      return response.data;
    } catch (error) {
      console.error('Error fetching theaters:', error);
      throw error;
    }
  },

  resetConversation: async () => {
    try {
      console.log('Resetting conversation');

      // Clear cache when resetting conversation
      cache.clear();

      await api.get('/reset-conversation/');
      return { status: 'success' };
    } catch (error) {
      console.error('Error resetting conversation:', error);
      throw error;
    }
  }
};
