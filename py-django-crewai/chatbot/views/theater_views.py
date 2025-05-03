"""
Theater and showtime views for the chatbot application.
This module handles API endpoints related to theater data and movie showtimes.
"""

import json
import logging
import traceback
import time
from datetime import datetime
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.conf import settings
from django.utils import timezone
from ..models import Conversation, Message, MovieRecommendation, Theater, Showtime
from .common_views import _parse_request_data, _get_or_create_conversation, get_client_ip

# Configure logger
logger = logging.getLogger('chatbot')

@csrf_exempt
def get_movies_theaters_and_showtimes(request):
    """Process a message in First Run mode to get movies, theaters, and showtimes."""
    if request.method != 'POST':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts POST requests'
        }, status=405)

    # Check if First Run mode is enabled via feature flag
    if not settings.FEATURES.get('ENABLE_FIRST_RUN_MODE', True):
        logger.warning("First Run mode is disabled but endpoint was accessed")
        return JsonResponse({
            'status': 'error',
            'message': 'First Run mode is currently disabled. Please use Casual Viewing mode instead.'
        }, status=400)

    try:
        logger.info("=== Processing First Run mode request for movies, theaters, and showtimes ===")
        start_time = time.time()

        # Parse the request data
        data = _parse_request_data(request)
        conversation = _get_or_create_conversation(request, 'first_run')

        # Extract message and location from request data
        user_message_text = (
            data.get('message') or
            data.get('text') or
            data.get('query') or
            ''
        )
        location = (
            data.get('location') or
            data.get('city') or
            data.get('loc') or
            request.session.get('user_location') or
            'Unknown'
        )
        timezone_str = (
            data.get('timezone') or
            request.session.get('user_timezone') or
            'America/Los_Angeles'
        )

        # Save user message
        user_message = Message.objects.create(
            conversation=conversation,
            sender='user',
            content=user_message_text
        )

        # Store the query in the session for polling
        request.session['first_run_query'] = user_message_text
        request.session['user_location'] = location
        request.session['user_timezone'] = timezone_str
        request.session['first_run_query_timestamp'] = timezone.now().isoformat()

        # Measure request processing time
        processing_time = time.time() - start_time
        logger.info(f"Request processing took {processing_time:.2f}s")

        # Return a processing status to enable polling
        return JsonResponse({
            'status': 'processing',
            'message': 'Your movie recommendations are being processed. Please wait a moment.',
            'conversation_id': conversation.id
        })

    except Exception as e:
        logger.error(f"Error initiating first run movie recommendation request: {str(e)}")
        logger.error(traceback.format_exc())
        return JsonResponse({
            'status': 'error',
            'message': 'An error occurred while processing your request.'
        }, status=500)

@csrf_exempt
def get_theaters(request, movie_id):
    """Fetch theaters and showtimes for a specific movie."""
    if request.method != 'GET':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts GET requests'
        }, status=405)

    try:
        logger.info(f"=== Fetching theaters for movie ID: {movie_id} ===")
        start_time = time.time()

        # Get the movie from the database
        try:
            movie = MovieRecommendation.objects.get(id=movie_id)
            logger.info(f"Found movie: {movie.title} (ID: {movie_id})")
        except MovieRecommendation.DoesNotExist:
            logger.error(f"Movie with ID {movie_id} not found")
            return JsonResponse({
                'status': 'error',
                'message': f'Movie with ID {movie_id} not found'
            }, status=404)

        # Get client IP and location for potential geolocation
        client_ip = get_client_ip(request)
        user_location = request.session.get('user_location', 'Unknown')
        timezone_str = request.session.get('user_timezone', 'America/Los_Angeles')

        # Check if we already have theaters and showtimes for this movie
        existing_showtimes = movie.showtimes.count()
        logger.info(f"Movie has {existing_showtimes} existing showtimes in database")

        # If we have showtimes already, return them
        if existing_showtimes > 0:
            # If we already have showtimes, use them
            logger.info(f"Using existing theater data for {movie.title}")

            # Group showtimes by theater
            theater_map = {}
            for showtime in movie.showtimes.all():
                theater_name = showtime.theater.name

                # Create theater entry if not exists
                if theater_name not in theater_map:
                    distance_miles = showtime.theater.distance_miles if hasattr(showtime.theater, 'distance_miles') else 10.0

                    theater_map[theater_name] = {
                        'name': theater_name,
                        'address': showtime.theater.address,
                        'distance_miles': distance_miles,
                        'showtimes': []
                    }

                # Add showtimes to the theater
                theater_map[theater_name]['showtimes'].append({
                    'start_time': showtime.start_time.isoformat(),
                    'format': showtime.format
                })

            # Convert theater map to list sorted by distance
            theater_data = []
            for _, theater in sorted(theater_map.items(), key=lambda x: x[1].get('distance_miles', float('inf'))):
                theater_data.append(theater)

            # Measure processing time
            processing_time = time.time() - start_time
            logger.info(f"Theater data processing took {processing_time:.2f}s")

            logger.info(f"Returning {len(theater_data)} theaters with existing showtimes")

            return JsonResponse({
                'status': 'success',
                'movie_id': movie_id,
                'movie_title': movie.title,
                'theaters': theater_data
            })
        else:
            # Return processing status to trigger polling
            logger.info(f"No showtimes found for {movie.title}, returning processing status")
            return JsonResponse({
                'status': 'processing',
                'message': f'Processing theater data for {movie.title}. Please check back in a moment.'
            })

    except Exception as e:
        logger.error(f"Error fetching theaters: {str(e)}")
        logger.error(traceback.format_exc())
        return JsonResponse({
            'status': 'error',
            'message': 'An error occurred while fetching theater data'
        }, status=500)

@csrf_exempt
def theater_status(request, movie_id):
    """Check the status of theater fetching for a specific movie - used for polling."""
    if request.method != 'GET':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts GET requests'
        }, status=405)

    try:
        logger.info(f"=== Checking theater status for movie ID: {movie_id} ===")
        start_time = time.time()

        # Get the movie from the database
        try:
            movie = MovieRecommendation.objects.get(id=movie_id)
            logger.info(f"Found movie: {movie.title} (ID: {movie_id})")
        except MovieRecommendation.DoesNotExist:
            logger.error(f"Movie with ID {movie_id} not found")
            return JsonResponse({
                'status': 'error',
                'message': f'Movie with ID {movie_id} not found'
            }, status=404)

        # Check if we have theaters and showtimes for this movie
        existing_showtimes = movie.showtimes.count()
        logger.info(f"Movie has {existing_showtimes} existing showtimes in database")

        # If we have showtimes already, return them
        if existing_showtimes > 0:
            logger.info(f"Theaters are ready for {movie.title}, returning data")

            # Group showtimes by theater
            theater_map = {}
            for showtime in movie.showtimes.all():
                theater_name = showtime.theater.name

                # Create theater entry if not exists
                if theater_name not in theater_map:
                    distance_miles = showtime.theater.distance_miles if hasattr(showtime.theater, 'distance_miles') else 10.0

                    theater_map[theater_name] = {
                        'name': theater_name,
                        'address': showtime.theater.address,
                        'distance_miles': distance_miles,
                        'showtimes': []
                    }

                # Add showtimes to the theater
                theater_map[theater_name]['showtimes'].append({
                    'start_time': showtime.start_time.isoformat(),
                    'format': showtime.format
                })

            # Convert theater map to list sorted by distance
            theater_data = []
            for _, theater in sorted(theater_map.items(), key=lambda x: x[1].get('distance_miles', float('inf'))):
                theater_data.append(theater)

            # Measure processing time
            processing_time = time.time() - start_time
            logger.info(f"Theater status check completed in {processing_time:.2f}s")

            return JsonResponse({
                'status': 'success',
                'movie_id': movie_id,
                'movie_title': movie.title,
                'theaters': theater_data
            })
        else:
            # If no showtimes yet, return processing status
            return JsonResponse({
                'status': 'processing',
                'message': f'Still searching for theaters for {movie.title}...'
            })

    except Exception as e:
        logger.error(f"Error checking theater status: {str(e)}")
        logger.error(traceback.format_exc())
        return JsonResponse({
            'status': 'error',
            'message': 'An error occurred while checking theater status'
        }, status=500)
