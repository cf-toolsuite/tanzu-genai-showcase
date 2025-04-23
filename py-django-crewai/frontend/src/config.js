// Frontend configuration module for API settings

import axios from 'axios';

// Default configuration values
const defaultConfig = {
  apiTimeout: 60000, // 60 seconds in milliseconds
  apiMaxRetries: 10,  // Maximum number of polling attempts
  apiRetryBackoffFactor: 1.5 // Exponential backoff factor
};

// Configuration state
let appConfig = { ...defaultConfig };

/**
 * Load API configuration from the backend
 * @returns {Promise<Object>} The loaded configuration
 */
export const loadApiConfig = async () => {
  try {
    console.log('Loading API configuration from backend...');
    const response = await axios.get('/api-config/');

    if (response.data) {
      // Convert seconds to milliseconds for axios timeout
      if (response.data.api_timeout_seconds) {
        appConfig.apiTimeout = response.data.api_timeout_seconds * 1000;
      }

      if (response.data.api_max_retries) {
        appConfig.apiMaxRetries = response.data.api_max_retries;
      }

      if (response.data.api_retry_backoff_factor) {
        appConfig.apiRetryBackoffFactor = response.data.api_retry_backoff_factor;
      }

      console.log('API configuration loaded:', appConfig);
    }

    return appConfig;
  } catch (error) {
    console.error('Failed to load API configuration, using defaults:', error);
    return appConfig;
  }
};

/**
 * Get the current configuration
 * @returns {Object} The current configuration
 */
export const getConfig = () => {
  return appConfig;
};

export default {
  loadApiConfig,
  getConfig
};
