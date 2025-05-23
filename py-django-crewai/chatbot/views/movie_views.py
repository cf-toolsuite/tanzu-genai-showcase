"""
Movie recommendation views for the chatbot application.
This module handles all movie recommendation related API endpoints.
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
from ..services.movie_crew_integration import MovieCrewService
from .common_views import _parse_request_data, _get_or_create_conversation

# Configure logger
logger = logging.getLogger('chatbot')

@csrf_exempt
def get_movie_recommendations(request):
    """Process a message in Casual Viewing mode to get movie recommendations."""
    if request.method != 'POST':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts POST requests'
        }, status=405)

    try:
        logger.info("=== Processing Casual Viewing mode request for movie recommendations ===")
        start_time = time.time()

        # Parse the request data
        data = _parse_request_data(request)
        conversation = _get_or_create_conversation(request, 'casual')

        # Extract message from request data
        user_message_text = (
            data.get('message') or
            data.get('text') or
            data.get('query') or
            ''
        )

        # Save user message
        user_message = Message.objects.create(
            conversation=conversation,
            sender='user',
            content=user_message_text
        )

        # Store the query in the session for polling
        request.session['casual_query'] = user_message_text
        request.session['casual_query_timestamp'] = timezone.now().isoformat()

        # Measure processing time
        processing_time = time.time() - start_time
        logger.info(f"Request processing took {processing_time:.2f}s")

        # Return a processing status to enable polling
        return JsonResponse({
            'status': 'processing',
            'message': 'Your movie recommendations are being processed. Please wait a moment.',
            'conversation_id': conversation.id
        })

    except Exception as e:
        logger.error(f"Error initiating movie recommendation request: {str(e)}")
        logger.error(traceback.format_exc())
        return JsonResponse({
            'status': 'error',
            'message': 'An error occurred while processing your request.'
        }, status=500)

@csrf_exempt
def poll_movie_recommendations(request):
    """Poll for movie recommendations that are being processed."""
    if request.method != 'GET':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts GET requests'
        }, status=405)

    try:
        # Get the conversation
        conversation = _get_or_create_conversation(request, 'casual')

        # Check if we have a query to process
        user_message_text = request.session.get('casual_query')
        if not user_message_text:
            return JsonResponse({
                'status': 'error',
                'message': 'No pending movie recommendation request found.'
            }, status=404)

        # Check if we already have recommendations for this conversation
        existing_recommendations = conversation.recommendations.all()

        # If we have recommendations and they were created after the query timestamp,
        # return them as the result
        query_timestamp = request.session.get('casual_query_timestamp')
        if query_timestamp and existing_recommendations.exists():
            # Get the latest recommendation timestamp
            latest_recommendation = existing_recommendations.order_by('-created_at').first()
            if latest_recommendation and latest_recommendation.created_at.isoformat() > query_timestamp:
                # We have fresh recommendations, return them
                logger.info(f"Found existing recommendations for conversation {conversation.id}")

                # Get the bot message
                bot_message = conversation.messages.filter(sender='bot').order_by('-created_at').first()
                bot_response = bot_message.content if bot_message else "Here are your movie recommendations."

                # Format recommendations
                recommendations_data = []
                # Convert ISO format string to datetime object
                try:
                    query_dt = datetime.fromisoformat(query_timestamp)
                except ValueError:
                    # Handle invalid format
                    logger.warning(f"Invalid timestamp format: {query_timestamp}")
                    query_dt = timezone.now() - timezone.timedelta(hours=24)  # Use 24 hours ago as fallback

                # If the query_dt is naive (no timezone), make it timezone-aware
                if query_dt.tzinfo is None:
                    query_dt = timezone.make_aware(query_dt)

                for movie in existing_recommendations.filter(created_at__gt=query_dt):
                    recommendations_data.append({
                        'id': movie.id,
                        'title': movie.title,
                        'overview': movie.overview,
                        'poster_url': movie.poster_url,
                        'release_date': movie.release_date.isoformat() if movie.release_date and hasattr(movie.release_date, 'isoformat') else movie.release_date,
                        'rating': float(movie.rating) if movie.rating else None,
                        'theaters': []  # No theaters for casual mode
                    })

                if recommendations_data:
                    # Clear the query from the session
                    if 'casual_query' in request.session:
                        del request.session['casual_query']
                    if 'casual_query_timestamp' in request.session:
                        del request.session['casual_query_timestamp']

                    return JsonResponse({
                        'status': 'success',
                        'message': bot_response,
                        'recommendations': recommendations_data
                    })

        # If we don't have recommendations yet, process the query
        # Check if we're already processing this query
        if getattr(request, '_processing_casual_query', False):
            return JsonResponse({
                'status': 'processing',
                'message': 'Your movie recommendations are still being processed. Please wait a moment.',
                'conversation_id': conversation.id
            })

        # Set a flag to prevent concurrent processing
        setattr(request, '_processing_casual_query', True)

        # Log the conversation mode to help with debugging
        logger.info(f"Processing query in poll_movie_recommendations with conversation mode: {conversation.mode}")

        try:
            # Get conversation history
            conversation_history = [{
                'sender': msg.sender,
                'content': msg.content
            } for msg in conversation.messages.all()]

            # Process the query using our optimized service
            response_data = MovieCrewService.process_query(
                query=user_message_text,
                conversation_history=conversation_history,
                first_run_mode=False,  # Explicitly set to False for casual mode
                timezone=request.session.get('user_timezone')
            )

            # Save bot response
            bot_response = response_data.get('response', 'Sorry, I could not generate a response.')
            bot_message = Message.objects.create(
                conversation=conversation,
                sender='bot',
                content=bot_response
            )

            # Process and save movie recommendations
            recommendations_data = []
            for movie_data in response_data.get('movies', []):
                # Convert release_date string to a proper date object
                release_date_str = movie_data.get('release_date')
                release_date = None
                if release_date_str:
                    try:
                        release_date = datetime.strptime(release_date_str, '%Y-%m-%d').date()
                    except ValueError:
                        # Handle invalid date format
                        logger.warning(f"Invalid release date format: {release_date_str}")
                        pass

                movie = MovieRecommendation.objects.create(
                    conversation=conversation,
                    title=movie_data.get('title', 'Unknown Movie'),
                    overview=movie_data.get('overview', ''),
                    poster_url=movie_data.get('poster_url', ''),
                    release_date=release_date,  # Now a proper date object
                    tmdb_id=movie_data.get('tmdb_id'),
                    rating=movie_data.get('rating')
                )

                recommendations_data.append({
                    'id': movie.id,
                    'title': movie.title,
                    'overview': movie.overview,
                    'poster_url': movie.poster_url,
                    'release_date': movie.release_date.isoformat() if movie.release_date and hasattr(movie.release_date, 'isoformat') else movie.release_date,
                    'rating': float(movie.rating) if movie.rating else None,
                    'theaters': []  # No theaters for casual mode
                })

            # Clear the query from the session
            if 'casual_query' in request.session:
                del request.session['casual_query']
            if 'casual_query_timestamp' in request.session:
                del request.session['casual_query_timestamp']

            return JsonResponse({
                'status': 'success',
                'message': bot_response,
                'recommendations': recommendations_data
            })
        finally:
            # Clear the processing flag
            setattr(request, '_processing_casual_query', False)

        # If we get here, we're still processing
        return JsonResponse({
            'status': 'processing',
            'message': 'Your movie recommendations are still being processed. Please wait a moment.',
            'conversation_id': conversation.id
        })

    except Exception as e:
        logger.error(f"Error processing movie recommendation poll: {str(e)}")
        logger.error(traceback.format_exc())
        return JsonResponse({
            'status': 'error',
            'message': 'An error occurred while processing your request.'
        }, status=500)

@csrf_exempt
def poll_first_run_recommendations(request):
    """Poll for first run movie recommendations with improved performance."""
    if request.method != 'GET':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts GET requests'
        }, status=405)

    try:
        processing_start_time = time.time()

        # Get the conversation
        conversation = _get_or_create_conversation(request, 'first_run')

        # Check if we have a query to process
        user_message_text = request.session.get('first_run_query')
        user_location = request.session.get('user_location', '')

        if not user_message_text:
            return JsonResponse({
                'status': 'error',
                'message': 'No pending first run movie request found.'
            }, status=404)

        # Check if we already have recommendations for this conversation
        existing_recommendations = conversation.recommendations.all()

        # If we have recommendations and they were created after the query timestamp,
        # return them as the result
        query_timestamp = request.session.get('first_run_query_timestamp')
        if query_timestamp and existing_recommendations.exists():
            # Get the latest recommendation timestamp
            latest_recommendation = existing_recommendations.order_by('-created_at').first()
            if latest_recommendation and latest_recommendation.created_at.isoformat() > query_timestamp:
                # We have fresh recommendations, return them
                logger.info(f"Found existing recommendations for conversation {conversation.id}")

                # Get the bot message
                bot_message = conversation.messages.filter(sender='bot').order_by('-created_at').first()
                bot_response = bot_message.content if bot_message else "Here are your movie recommendations."

                # Format recommendations
                recommendations_data = []
                # Convert ISO format string to datetime object
                try:
                    query_dt = datetime.fromisoformat(query_timestamp)
                except ValueError:
                    # Handle invalid format
                    logger.warning(f"Invalid timestamp format: {query_timestamp}")
                    query_dt = timezone.now() - timezone.timedelta(hours=24)  # Use 24 hours ago as fallback

                # If the query_dt is naive (no timezone), make it timezone-aware
                if query_dt.tzinfo is None:
                    query_dt = timezone.make_aware(query_dt)

                for movie in existing_recommendations.filter(created_at__gt=query_dt):
                                # Get theaters for this movie
                                theaters_data = []
                                for showtime in movie.showtimes.all():
                                    theater = showtime.theater

                                    # Check if this theater is already in the list
                                    theater_exists = False
                                    for t in theaters_data:
                                        if t['name'] == theater.name:
                                            theater_exists = True
                                            # Add showtime to existing theater
                                            t['showtimes'].append({
                                                'start_time': showtime.start_time.isoformat() if hasattr(showtime.start_time, 'isoformat') else showtime.start_time,
                                                'format': showtime.format
                                            })
                                            break

                                    # If theater not in list, add it
                                    if not theater_exists:
                                        theaters_data.append({
                                            'name': theater.name,
                                            'address': theater.address,
                                            'distance_miles': float(theater.distance_miles) if theater.distance_miles else None,
                                            'showtimes': [{
                                                'start_time': showtime.start_time.isoformat() if hasattr(showtime.start_time, 'isoformat') else showtime.start_time,
                                                'format': showtime.format
                                            }]
                                        })

                                recommendations_data.append({
                                    'id': movie.id,
                                    'title': movie.title,
                                    'overview': movie.overview,
                                    'poster_url': movie.poster_url,
                                    'release_date': movie.release_date.isoformat() if movie.release_date and hasattr(movie.release_date, 'isoformat') else movie.release_date,
                                    'rating': float(movie.rating) if movie.rating else None,
                                    'theaters': theaters_data
                                })

                if recommendations_data:
                    # Clear the query from the session
                    if 'first_run_query' in request.session:
                        del request.session['first_run_query']
                    if 'first_run_query_timestamp' in request.session:
                        del request.session['first_run_query_timestamp']

                    # Log processing time
                    processing_time = time.time() - processing_start_time
                    logger.info(f"Recommendation processing completed in {processing_time:.2f}s")

                    return JsonResponse({
                        'status': 'success',
                        'message': bot_response,
                        'recommendations': recommendations_data
                    })

        # If we don't have recommendations yet, process the query
        # Check if we're already processing this query
        if getattr(request, '_processing_first_run_query', False):
            return JsonResponse({
                'status': 'processing',
                'message': 'Your movie recommendations are still being processed. Please wait a moment.',
                'conversation_id': conversation.id
            })

        # Set a flag to prevent concurrent processing
        setattr(request, '_processing_first_run_query', True)

        try:
            # Get conversation history for context
            conversation_history = [{
                'sender': msg.sender,
                'content': msg.content
            } for msg in conversation.messages.all()]

            # Process the query using our optimized service
            from .common_views import get_client_ip
            client_ip = get_client_ip(request)
            user_timezone = request.session.get('user_timezone', 'America/Los_Angeles')

            response_data = MovieCrewService.process_query(
                query=user_message_text,
                conversation_history=conversation_history,
                first_run_mode=True,  # Explicitly set to True for first run mode
                user_location=user_location,
                user_ip=client_ip,
                timezone=user_timezone
            )

            # Save bot response
            bot_response = response_data.get('response', 'Sorry, I could not generate a response.')
            bot_message = Message.objects.create(
                conversation=conversation,
                sender='bot',
                content=bot_response
            )

            # Process and save movie recommendations
            recommendations_data = []
            for movie_data in response_data.get('movies', []):
                # Convert release_date string to a proper date object
                release_date_str = movie_data.get('release_date')
                release_date = None
                if release_date_str:
                    try:
                        release_date = datetime.strptime(release_date_str, '%Y-%m-%d').date()
                    except ValueError:
                        # Handle invalid date format
                        logger.warning(f"Invalid release date format: {release_date_str}")
                        pass

                movie = MovieRecommendation.objects.create(
                    conversation=conversation,
                    title=movie_data.get('title', 'Unknown Movie'),
                    overview=movie_data.get('overview', ''),
                    poster_url=movie_data.get('poster_url', ''),
                    release_date=release_date,
                    tmdb_id=movie_data.get('tmdb_id'),
                    rating=movie_data.get('rating')
                )

                # Process theaters and showtimes
                theaters_data = []
                if movie_data.get('theaters'):
                    for theater_data in movie_data['theaters']:
                        theater, _ = Theater.objects.get_or_create(
                            name=theater_data.get('name', 'Unknown Theater'),
                            defaults={
                                'address': theater_data.get('address', ''),
                                'latitude': theater_data.get('latitude'),
                                'longitude': theater_data.get('longitude'),
                                'distance_miles': theater_data.get('distance_miles')
                            }
                        )

                        # Save showtimes
                        showtimes_data = []
                        for showtime_data in theater_data.get('showtimes', []):
                            try:
                                # Process the showtime data
                                try:
                                    # Save the original time string format for response
                                    original_time_str = showtime_data['start_time']

                                    # Check for non-ISO format times (e.g., "8:00 PM")
                                    time_str = original_time_str

                                    # First check if it's a time like "8:00 PM" or other non-ISO format
                                    if isinstance(time_str, str) and (":" in time_str and ("AM" in time_str.upper() or "PM" in time_str.upper())):
                                        # Parse time like "8:00 PM"
                                        import pytz

                                        # Get the base date (today)
                                        today = datetime.now().date()

                                        # Parse the time
                                        time_format = "%I:%M %p"  # Format for "8:00 PM"
                                        time_obj = datetime.strptime(time_str, time_format).time()

                                        # Combine date and time
                                        start_time = datetime.combine(today, time_obj)

                                        # Make it timezone aware
                                        tz = pytz.timezone(user_timezone)
                                        start_time = tz.localize(start_time)

                                        # Log successful conversion
                                        logger.info(f"Converted time format '{original_time_str}' to ISO format")
                                    else:
                                        try:
                                            # Standard ISO format parsing
                                            start_time = datetime.fromisoformat(time_str)
                                            if start_time.tzinfo is None:
                                                # Make timezone-aware if needed
                                                start_time = timezone.make_aware(start_time)
                                        except ValueError:
                                            # If ISO parsing fails, try one more time with AM/PM format
                                            # This catches cases where the format detection might have failed
                                            logger.warning(f"Trying alternative parsing for: {time_str}")
                                            import pytz
                                            today = datetime.now().date()

                                            # Try multiple time formats
                                            formats_to_try = ["%I:%M %p", "%I:%M%p", "%H:%M"]
                                            parsed = False

                                            for fmt in formats_to_try:
                                                try:
                                                    time_obj = datetime.strptime(time_str, fmt).time()
                                                    start_time = datetime.combine(today, time_obj)
                                                    tz = pytz.timezone(user_timezone)
                                                    start_time = tz.localize(start_time)
                                                    parsed = True
                                                    logger.info(f"Parsed time with format {fmt}: {time_str}")
                                                    break
                                                except ValueError:
                                                    continue

                                            if not parsed:
                                                # If all parsing attempts fail, raise an error
                                                raise ValueError(f"Could not parse time: {time_str}")
                                except ValueError as e:
                                    # Handle invalid datetime format by skipping this showtime
                                    logger.warning(f"Invalid datetime format in showtime: {time_str} - {str(e)}")
                                    continue

                                # Create the showtime
                                showtime = Showtime.objects.create(
                                    movie=movie,
                                    theater=theater,
                                    start_time=start_time,
                                    format=showtime_data.get('format', 'Standard')
                                )

                                # Add formatted showtime to the response
                                showtimes_data.append({
                                    'start_time': showtime.start_time.isoformat(),
                                    'format': showtime.format
                                })
                            except (ValueError, TypeError) as e:
                                # Log the error but continue processing other showtimes
                                logger.warning(f"Invalid datetime format in showtime: {showtime_data['start_time']} - {str(e)}")
                                # Skip this showtime
                                continue

                        theaters_data.append({
                            'name': theater.name,
                            'address': theater.address,
                            'distance_miles': float(theater.distance_miles) if theater.distance_miles else None,
                            'showtimes': showtimes_data
                        })

                recommendations_data.append({
                    'id': movie.id,
                    'title': movie.title,
                    'overview': movie.overview,
                    'poster_url': movie.poster_url,
                    'release_date': movie.release_date.isoformat() if movie.release_date and hasattr(movie.release_date, 'isoformat') else movie.release_date,
                    'rating': float(movie.rating) if movie.rating else None,
                    'theaters': theaters_data
                })

            # Clear the query from the session
            if 'first_run_query' in request.session:
                del request.session['first_run_query']
            if 'first_run_query_timestamp' in request.session:
                del request.session['first_run_query_timestamp']

            # Log processing time
            processing_time = time.time() - processing_start_time
            logger.info(f"First run recommendations processed in {processing_time:.2f}s")

            return JsonResponse({
                'status': 'success',
                'message': bot_response,
                'recommendations': recommendations_data
            })
        finally:
            # Clear the processing flag
            setattr(request, '_processing_first_run_query', False)

        # If we get here, we're still processing
        return JsonResponse({
            'status': 'processing',
            'message': 'Your movie recommendations are still being processed. Please wait a moment.',
            'conversation_id': conversation.id
        })

    except Exception as e:
        logger.error(f"Error processing first run movie recommendation poll: {str(e)}")
        logger.error(traceback.format_exc())
        return JsonResponse({
            'status': 'error',
            'message': 'An error occurred while processing your request.'
        }, status=500)
