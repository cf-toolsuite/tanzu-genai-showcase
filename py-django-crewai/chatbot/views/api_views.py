"""
API configuration views for the chatbot application.
This module provides endpoints for API configuration and utility functions.
"""

import logging
import json
import os
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.conf import settings
from .common_views import get_client_ip

# Configure logger
logger = logging.getLogger('chatbot')

@csrf_exempt
def get_api_config(request):
    """Get API configuration for the frontend."""
    try:
        logger.info("Fetching API configuration")

        # Get client IP for potential location detection
        client_ip = get_client_ip(request)

        # Gather configuration settings for the frontend
        config = {
            # Base API configuration
            'api': {
                'backendUrl': request.build_absolute_uri('/'),  # URL to the backend API
                'frontendUrl': request.build_absolute_uri('/'),  # URL to the frontend
                'pollInterval': 2000,  # Polling interval in milliseconds
                'maxPollAttempts': 15,  # Maximum number of polling attempts
                'timeoutDuration': 30000,  # Timeout duration in milliseconds
            },

            # Feature flags
            'features': {
                # Feature flag for First Run mode
                'enableFirstRunMode': settings.FEATURES.get('ENABLE_FIRST_RUN_MODE', True),
                'showDebugInfo': settings.DEBUG,  # Show debug information in the UI
                'enableTheaterSearch': True,  # Enable theater search functionality
            },

            # UI configuration
            'ui': {
                'appTitle': 'Movie Recommendation Chatbot',
                'casualModeLabel': 'Casual Viewing',
                'firstRunModeLabel': 'Theater Search',
                'defaultMode': 'casual',  # Default mode for the chatbot
                'maxRecommendations': 10,  # Maximum number of recommendations to display
            },

            # Debugging information (only in debug mode)
            'debug': {
                'clientIp': client_ip if settings.DEBUG else None,
                'djangoVersion': settings.DJANGO_VERSION if hasattr(settings, 'DJANGO_VERSION') else 'Unknown',
                'environment': os.environ.get('ENVIRONMENT', 'development'),
                'debugMode': settings.DEBUG,
            }
        }

        return JsonResponse(config)

    except Exception as e:
        logger.error(f"Error retrieving API configuration: {str(e)}")
        return JsonResponse({
            'status': 'error',
            'message': 'Error retrieving API configuration'
        }, status=500)
