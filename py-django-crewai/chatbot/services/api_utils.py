"""
Utility functions for making API requests with retries and timeouts.
"""

import time
import logging
import inspect
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
        timeout = timeout or getattr(settings, 'API_REQUEST_TIMEOUT', 15)  # Increased timeout for SerpAPI
        max_retries = max_retries or getattr(settings, 'API_MAX_RETRIES', 4)  # Increased retries
        backoff_factor = backoff_factor or getattr(settings, 'API_RETRY_BACKOFF_FACTOR', 1.0)  # Increased backoff factor

        # Only add timeout parameter if it's not already present AND the function can accept it
        # Check if the function can accept a timeout parameter (either directly or via **kwargs)
        try:
            sig = inspect.signature(request_func)
            accepts_timeout = ('timeout' in sig.parameters or
                              any(p.kind == inspect.Parameter.VAR_KEYWORD for p in sig.parameters.values()))

            if accepts_timeout and 'timeout' not in kwargs:
                kwargs['timeout'] = timeout
                logger.debug(f"Adding timeout={timeout}s to request")
            elif not accepts_timeout and 'timeout' in kwargs:
                # Remove timeout if function doesn't accept it but it was provided
                logger.debug(f"Function doesn't accept timeout parameter, removing it")
                del kwargs['timeout']
            elif not accepts_timeout:
                logger.debug(f"Function doesn't accept timeout parameter, not adding it")
        except (TypeError, ValueError) as e:
            # If we can't inspect the function, log warning and don't add timeout
            logger.warning(f"Could not inspect request function, timeout handling may not work: {str(e)}")

        logger.debug(f"Making API request with max_retries={max_retries}")

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
                # For other exceptions, check if it's worth retrying
                logger.error(f"API request failed with error: {str(e)}")
                if 'rate limit' in str(e).lower() or 'too many requests' in str(e).lower() or '429' in str(e):
                    # This is likely a rate limit issue, retry with backoff
                    last_exception = e
                    logger.warning(f"Rate limit detected, will retry with backoff (attempt {attempt+1}/{max_retries+1})")
                    # Use a longer delay for rate limit errors
                    time.sleep(backoff_factor * (4 ** (attempt)))
                    
                    # If this was the last attempt, re-raise the exception
                    if attempt == max_retries:
                        logger.error(f"API request failed after {max_retries+1} attempts due to rate limiting")
                        raise
                else:
                    # For other non-retryable exceptions, don't retry
                    logger.error(f"API request failed with non-retryable error: {str(e)}")
                    raise

        # This should not be reached due to the raise in the except block above
        if last_exception:
            raise last_exception

        # Fallback error if we somehow exit the loop without raising or returning
        raise Exception("API request failed without a specific error")
