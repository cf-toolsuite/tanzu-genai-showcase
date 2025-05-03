from django.shortcuts import render, redirect
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.conf import settings
from django.utils import timezone
from .models import Conversation, Message, MovieRecommendation, Theater, Showtime
from .services.movie_crew import MovieCrewManager
from .services.movie_crew_optimized import MovieCrewManagerOptimized
from django.conf import settings
from .performance_settings import THEATER_SEARCH_TIMEOUT, THEATER_FALLBACK_ENABLED
import json
import logging
import traceback
import time
from datetime import datetime as dt
from datetime import datetime

logger = logging.getLogger('chatbot')

def get_client_ip(request):
    """Extract the client's IP address from the request."""
    x_forwarded_for = request.META.get('HTTP_X_FORWARDED_FOR')
    if x_forwarded_for:
        # X-Forwarded-For can be a comma-separated list of IPs
        # The first one is the client's IP
        ip = x_forwarded_for.split(',')[0].strip()
    else:
        # If no X-Forwarded-For header, use REMOTE_ADDR
        ip = request.META.get('REMOTE_ADDR', '')

    # Strip port if present
    if ':' in ip:
        ip = ip.split(':')[0]

    return ip

def index(request):
    """Render the chatbot interface."""
    # Track conversations for both modes
    first_run_conversation_id = request.session.get('first_run_conversation_id')
    casual_conversation_id = request.session.get('casual_conversation_id')

    # First Run mode conversation (default)
    if first_run_conversation_id:
        try:
            first_run_conversation = Conversation.objects.get(id=first_run_conversation_id)
        except Conversation.DoesNotExist:
            first_run_conversation = Conversation.objects.create(mode='first_run')
            request.session['first_run_conversation_id'] = first_run_conversation.id
    else:
        first_run_conversation = Conversation.objects.create(mode='first_run')
        request.session['first_run_conversation_id'] = first_run_conversation.id

    # Casual Viewing mode conversation
    if casual_conversation_id:
        try:
            casual_conversation = Conversation.objects.get(id=casual_conversation_id)
        except Conversation.DoesNotExist:
            casual_conversation = Conversation.objects.create(mode='casual')
            request.session['casual_conversation_id'] = casual_conversation.id
    else:
        casual_conversation = Conversation.objects.create(mode='casual')
        request.session['casual_conversation_id'] = casual_conversation.id

    # Get messages for both conversations
    first_run_messages = first_run_conversation.messages.all()
    casual_messages = casual_conversation.messages.all()

    # Get recommendations for both conversations
    first_run_recommendations = first_run_conversation.recommendations.all()
    casual_recommendations = casual_conversation.recommendations.all()

    # Add welcome messages if needed for first run mode
    if not first_run_messages:
        welcome_message = Message.objects.create(
            conversation=first_run_conversation,
            sender='bot',
            content="Hello! I'm your movie assistant for finding films currently in theaters. Tell me what kind of movie you're in the mood for, and I can recommend options and show you where they're playing nearby. For example, you could say 'I want to see a thriller' or 'Show me family movies playing this weekend'."
        )

    # Add welcome message for casual viewing mode
    if not casual_messages:
        casual_welcome_message = Message.objects.create(
            conversation=casual_conversation,
            sender='bot',
            content="Welcome to Casual Viewing mode! Here I can recommend movies from any time period based on your preferences, not just those currently in theaters. Try asking for recommendations like 'Show me sci-fi movies with time travel' or 'Recommend comedies from the 2010s'."
        )

    # Pass both conversations to the template
    return render(request, 'chatbot/index.html', {
        'first_run_conversation': first_run_conversation,
        'casual_conversation': casual_conversation,
        'first_run_messages': first_run_messages,
        'casual_messages': casual_messages,
        'first_run_recommendations': first_run_recommendations,
        'casual_recommendations': casual_recommendations,
    })

def _parse_request_data(request):
    """Helper function to parse request data."""
    try:
        raw_body = request.body.decode('utf-8')
        try:
            data = json.loads(raw_body)
        except json.JSONDecodeError:
            try:
                data = json.loads(raw_body.replace("'", '"'))
            except Exception:
                if raw_body.startswith('"') and raw_body.endswith('"'):
                    data = {"message": raw_body.strip('"')}
                else:
                    raise ValueError('Invalid request format. Could not parse message.')
        return data
    except Exception as parsing_error:
        logger.error(f"Error parsing request: {str(parsing_error)}")
        raise

def _get_or_create_conversation(request, mode):
    """Helper function to get or create a conversation."""
    session_key = f"{mode}_conversation_id"
    conversation_id = request.session.get(session_key)

    if not conversation_id:
        logger.info(f"Creating new {mode} conversation")
        conversation = Conversation.objects.create(mode=mode)
        request.session[session_key] = conversation.id
        logger.info(f"New {mode} conversation created with ID: {conversation.id}")
    else:
        logger.info(f"Using existing {mode} conversation with ID: {conversation_id}")
        try:
            conversation = Conversation.objects.get(id=conversation_id)
            if conversation.mode != mode:
                conversation.mode = mode
                conversation.save()
        except Conversation.DoesNotExist:
            conversation = Conversation.objects.create(mode=mode)
            request.session[session_key] = conversation.id

    return conversation

@csrf_exempt
def get_movies_theaters_and_showtimes(request):
    """Process a message in First Run mode to get movies, theaters, and showtimes."""
    if request.method != 'POST':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts POST requests'
        }, status=405)

    # Check if First Run mode is enabled
    if not settings.FEATURES.get('ENABLE_FIRST_RUN_MODE', True):
        logger.warning("First Run mode is disabled but endpoint was accessed")
        return JsonResponse({
            'status': 'error',
            'message': 'First Run mode is currently disabled. Please use Casual Viewing mode instead.'
        }, status=403)

    try:
        logger.info("=== Processing First Run mode request for movies, theaters, and showtimes ===")
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
def get_movie_recommendations(request):
    """Process a message in Casual Viewing mode to get movie recommendations."""
    if request.method != 'POST':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts POST requests'
        }, status=405)

    try:
        logger.info("=== Processing Casual Viewing mode request for movie recommendations ===")
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

        # Check if we should process immediately or return a processing status
        # For simplicity, we'll always return processing status to enable polling
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

    timeout_seconds = getattr(settings, "THEATER_SEARCH_TIMEOUT", 60)
    start_time = time.time()

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
                from datetime import datetime
                query_dt = datetime.fromisoformat(query_timestamp)

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
        # Initialize movie crew manager
        # Use optimized manager if optimization is enabled in settings
        use_optimized = getattr(settings, "USE_OPTIMIZED_COMPONENTS", True)

        if use_optimized:
            # Note: The optimized manager doesn't support timeout and fallback parameters yet
            movie_crew_manager = MovieCrewManagerOptimized(
                api_key=settings.LLM_CONFIG['api_key'],
                base_url=settings.LLM_CONFIG.get('base_url'),
                model=settings.LLM_CONFIG.get('model', 'gpt-4o-mini'),
                tmdb_api_key=settings.TMDB_API_KEY,
                user_location='',  # No location needed for casual mode
                user_ip=get_client_ip(request),
                timezone='UTC'  # Default timezone for casual mode
                # timeout and fallback parameters removed as they're not supported
            )
        else:
            movie_crew_manager = MovieCrewManager(
                api_key=settings.LLM_CONFIG['api_key'],
                base_url=settings.LLM_CONFIG.get('base_url'),
                model=settings.LLM_CONFIG.get('model', 'gpt-4o-mini'),
                tmdb_api_key=settings.TMDB_API_KEY,
                user_location='',  # No location needed for casual mode
                user_ip=get_client_ip(request),
                timezone='UTC'  # Default timezone for casual mode
            )

        # Process query with casual mode
        conversation_history = [{
            'sender': msg.sender,
            'content': msg.content
        } for msg in conversation.messages.all()]

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

        # Ensure we're explicitly setting first_run_mode to False for casual mode
        first_run_mode = False
        if conversation.mode != 'casual':
            logger.warning(f"Unexpected conversation mode in casual polling: {conversation.mode}. Forcing casual mode.")
            # Update the conversation mode if it's not set correctly
            conversation.mode = 'casual'
            conversation.save()

        try:
            # Add timeout handling for the process_query call
            # Use the timeout parameter if the manager supports it
            response_data = movie_crew_manager.process_query(
                query=user_message_text,
                conversation_history=conversation_history,
                first_run_mode=False  # Explicitly set to False for casual mode
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
                        from datetime import datetime
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
        # Check if it's a timeout issue
        elapsed = time.time() - start_time if 'start_time' in locals() else float('inf')
        if elapsed >= timeout_seconds:
            logger.warning(f"Timeout occurred while processing theater data after {elapsed:.1f} seconds")
            # Return partial results with empty theaters on timeout
            return JsonResponse({
                'status': 'success',
                'message': 'Your movie recommendations are ready, but we couldn\'t find theater information within the time limit.',
                'recommendations': [
                    {
                        'id': movie.id,
                        'title': movie.title,
                        'overview': movie.overview,
                        'poster_url': movie.poster_url,
                        'release_date': movie.release_date.isoformat() if movie.release_date and hasattr(movie.release_date, 'isoformat') else None,
                        'rating': float(movie.rating) if movie.rating else None,
                        'theaters': []
                    } for movie in conversation.recommendations.all().order_by('-created_at')[:5]
                ]
            })

        logger.error(f"Error processing movie recommendation poll: {str(e)}")
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

def reset_conversation(request):
    """Reset the conversations and start new ones."""
    # Reset both conversation types
    if 'first_run_conversation_id' in request.session:
        del request.session['first_run_conversation_id']

    if 'casual_conversation_id' in request.session:
        del request.session['casual_conversation_id']

    # For backward compatibility - also remove the old format if it exists
    if 'conversation_id' in request.session:
        del request.session['conversation_id']

    return redirect('index')


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


@csrf_exempt
def poll_first_run_recommendations(request):
    """Poll for first run movie recommendations, theaters, and showtimes that are being processed."""
    if request.method != 'GET':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts GET requests'
        }, status=405)

    # Check if First Run mode is enabled
    if not settings.FEATURES.get('ENABLE_FIRST_RUN_MODE', True):
        logger.warning("First Run mode is disabled but poll endpoint was accessed")
        return JsonResponse({
            'status': 'error',
            'message': 'First Run mode is currently disabled. Please use Casual Viewing mode instead.'
        }, status=403)

    timeout_seconds = getattr(settings, "THEATER_SEARCH_TIMEOUT", 60)
    start_time = time.time()

    try:
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
                from datetime import datetime
                query_dt = datetime.fromisoformat(query_timestamp)

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
                                    'start_time': showtime.start_time.isoformat(),
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
                                    'start_time': showtime.start_time.isoformat(),
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

                    return JsonResponse({
                        'status': 'success',
                        'message': bot_response,
                        'recommendations': recommendations_data
                    })

        # If we don't have recommendations yet, process the query
        # Initialize movie crew manager
        # Use optimized manager if optimization is enabled in settings
        use_optimized = getattr(settings, "USE_OPTIMIZED_COMPONENTS", True)

        if use_optimized:
            # Note: The optimized manager doesn't support timeout and fallback parameters yet
            movie_crew_manager = MovieCrewManagerOptimized(
                api_key=settings.LLM_CONFIG['api_key'],
                base_url=settings.LLM_CONFIG.get('base_url'),
                model=settings.LLM_CONFIG.get('model', 'gpt-4o-mini'),
                tmdb_api_key=settings.TMDB_API_KEY,
                user_location=user_location,
                user_ip=get_client_ip(request),
                timezone=request.session.get('user_timezone', 'America/Los_Angeles')
                # timeout and fallback parameters removed as they're not supported
            )
        else:
            movie_crew_manager = MovieCrewManager(
                api_key=settings.LLM_CONFIG['api_key'],
                base_url=settings.LLM_CONFIG.get('base_url'),
                model=settings.LLM_CONFIG.get('model', 'gpt-4o-mini'),
                tmdb_api_key=settings.TMDB_API_KEY,
                user_location=user_location,
                user_ip=get_client_ip(request),
                timezone=request.session.get('user_timezone', 'America/Los_Angeles')
            )

        # Process query with first run mode
        conversation_history = [{
            'sender': msg.sender,
            'content': msg.content
        } for msg in conversation.messages.all()]

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
            # Add timeout handling for the process_query call
            # Use the timeout parameter if the manager supports it
            response_data = movie_crew_manager.process_query(
                query=user_message_text,
                conversation_history=conversation_history,
                first_run_mode=True  # Explicitly set to True for first run mode
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
                        from datetime import datetime
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
                            # Check if the datetime is already timezone-aware
                            dt_obj = dt.fromisoformat(showtime_data['start_time'])
                            if dt_obj.tzinfo is not None:
                                # Already timezone-aware, use as is
                                start_time = dt_obj
                            else:
                                # Naive datetime, make it timezone-aware
                                start_time = timezone.make_aware(dt_obj)

                            showtime = Showtime.objects.create(
                                movie=movie,
                                theater=theater,
                                start_time=start_time,
                                format=showtime_data.get('format', 'Standard')
                            )
                            showtimes_data.append({
                                'start_time': showtime.start_time.isoformat(),
                                'format': showtime.format
                            })

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
        # Check if it's a timeout issue
        elapsed = time.time() - start_time if 'start_time' in locals() else float('inf')
        if elapsed >= timeout_seconds:
            logger.warning(f"Timeout occurred while processing theater data after {elapsed:.1f} seconds")
            # Return partial results with empty theaters on timeout
            return JsonResponse({
                'status': 'success',
                'message': 'Your movie recommendations are ready, but we couldn\'t find theater information within the time limit.',
                'recommendations': [
                    {
                        'id': movie.id,
                        'title': movie.title,
                        'overview': movie.overview,
                        'poster_url': movie.poster_url,
                        'release_date': movie.release_date.isoformat() if movie.release_date and hasattr(movie.release_date, 'isoformat') else None,
                        'rating': float(movie.rating) if movie.rating else None,
                        'theaters': []
                    } for movie in conversation.recommendations.all().order_by('-created_at')[:5]
                ]
            })

        logger.error(f"Error processing first run movie recommendation poll: {str(e)}")
        logger.error(traceback.format_exc())
        return JsonResponse({
            'status': 'error',
            'message': 'An error occurred while processing your request.'
        }, status=500)

@csrf_exempt
def get_api_config(request):
    """Return API configuration settings for the frontend."""
    if request.method != 'GET':
        return JsonResponse({
            'status': 'error',
            'message': 'This endpoint only accepts GET requests'
        }, status=405)

    try:
        from movie_chatbot.settings import app_config

        # Return non-sensitive configuration settings including feature flags
        return JsonResponse({
            'api_timeout_seconds': app_config.API_REQUEST_TIMEOUT,
            'api_max_retries': app_config.API_MAX_RETRIES,
            'api_retry_backoff_factor': app_config.API_RETRY_BACKOFF_FACTOR,
            'features': {
                'enable_first_run_mode': settings.FEATURES.get('ENABLE_FIRST_RUN_MODE', True)
            }
        })
    except Exception as e:
        logger.error(f"Error getting API configuration: {str(e)}")
        return JsonResponse({
            'status': 'error',
            'message': 'An error occurred while getting API configuration'
        }, status=500)
