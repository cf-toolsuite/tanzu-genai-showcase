"""
Utility functions for making API requests with retries and timeouts.
"""

import time
import logging
import requests
from typing import Dict, Any, Optional, Callable
from django.conf import settings

# Configure logger
logger = logging.getLogger('chatbot.api_utils')

class APIRequestHandler:
    """Handler for making API requests with retry logic and timeouts."""

    @staticmethod
    def make_request(
        request_func: Callable,
        *args,
        timeout: Optional[int] = None,
        max_retries: Optional[int] = None,
        backoff_factor: Optional[float] = None,
        **kwargs
    ) -> Any:
        """
        Make an API request with retry logic and timeout handling.

        Args:
            request_func: Function to make the request (typically a method from requests lib or similar)
            *args: Arguments to pass to the request function
            timeout: Request timeout in seconds (overrides settings.API_REQUEST_TIMEOUT if provided)
            max_retries: Maximum number of retries (overrides settings.API_MAX_RETRIES if provided)
            backoff_factor: Backoff factor between retries (overrides settings.API_RETRY_BACKOFF_FACTOR if provided)
            **kwargs: Keyword arguments to pass to the request function

        Returns:
            Result from the request function

        Raises:
            Exception: If all retry attempts fail
        """
        # Use provided values or defaults from settings
        timeout = timeout or getattr(settings, 'API_REQUEST_TIMEOUT', 10)
        max_retries = max_retries or getattr(settings, 'API_MAX_RETRIES', 3)
        backoff_factor = backoff_factor or getattr(settings, 'API_RETRY_BACKOFF_FACTOR', 0.5)
        
        # Add timeout to kwargs if not already present
        if 'timeout' not in kwargs:
            kwargs['timeout'] = timeout
        
        logger.debug(f"Making API request with timeout={timeout}s, max_retries={max_retries}")
        
        last_exception = None
        for attempt in range(max_retries + 1):  # +1 because first attempt is not a retry
            try:
                if attempt > 0:
                    # Calculate backoff time: backoff_factor * (2 ^ (attempt - 1))
                    # For backoff_factor=0.5: 0.5, 1, 2, 4, 8, etc.
                    backoff_time = backoff_factor * (2 ** (attempt - 1))
                    logger.info(f"Retry attempt {attempt}/{max_retries} after {backoff_time:.2f}s backoff")
                    time.sleep(backoff_time)
                
                # Make the request
                start_time = time.time()
                response = request_func(*args, **kwargs)
                elapsed_time = time.time() - start_time
                
                logger.debug(f"API request completed in {elapsed_time:.2f}s")
                return response
                
            except (requests.Timeout, requests.ConnectionError) as e:
                logger.warning(f"API request attempt {attempt+1}/{max_retries+1} failed: {str(e)}")
                last_exception = e
                
                # If this was the last attempt, re-raise the exception
                if attempt == max_retries:
                    logger.error(f"API request failed after {max_retries+1} attempts: {str(e)}")
                    raise
            except Exception as e:
                # For other exceptions, don't retry
                logger.error(f"API request failed with unexpected error: {str(e)}")
                raise
        
        # This should not be reached due to the raise in the except block above
        if last_exception:
            raise last_exception
        
        # Fallback error if we somehow exit the loop without raising or returning
        raise Exception("API request failed without a specific error")
