from django.shortcuts import render, redirect
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.conf import settings
from .models import Conversation, Message, MovieRecommendation, Theater, Showtime
from .services.movie_crew import MovieCrewManager
import json
import logging
import traceback
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

@csrf_exempt
def send_message(request):
    """Process a message sent by the user and return the chatbot's response."""
    if request.method == 'POST':
        try:
            logger.info("=== Processing new chat message ===")
            
            # Parse request data first to determine the mode
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
                            return JsonResponse({
                                'status': 'error',
                                'message': 'Invalid request format. Could not parse message.'
                            }, status=400)
                
                # Extract first run filter preference to determine which conversation to use
                first_run_filter = data.get('first_run_filter', True)
                if isinstance(first_run_filter, str):
                    first_run_filter = first_run_filter.lower() == 'true'
                
                logger.info(f"Message mode: {'First Run' if first_run_filter else 'Casual Viewing'}")
            except Exception as parsing_error:
                logger.error(f"Error parsing request: {str(parsing_error)}")
                # Default to First Run mode if we can't determine from the request
                first_run_filter = True
                logger.info("Defaulting to First Run mode due to parsing error")
            
            # Get the appropriate conversation based on the mode
            if first_run_filter:
                conversation_id = request.session.get('first_run_conversation_id')
                if not conversation_id:
                    logger.info("Creating new First Run conversation")
                    conversation = Conversation.objects.create(mode='first_run')
                    request.session['first_run_conversation_id'] = conversation.id
                    logger.info(f"New First Run conversation created with ID: {conversation.id}")
                else:
                    logger.info(f"Using existing First Run conversation with ID: {conversation_id}")
                    try:
                        conversation = Conversation.objects.get(id=conversation_id)
                        # Set mode if it's not already set (for backwards compatibility)
                        if conversation.mode != 'first_run':
                            conversation.mode = 'first_run'
                            conversation.save()
                    except Conversation.DoesNotExist:
                        # Create a new one if the stored ID doesn't exist
                        conversation = Conversation.objects.create(mode='first_run')
                        request.session['first_run_conversation_id'] = conversation.id
            else:
                conversation_id = request.session.get('casual_conversation_id')
                if not conversation_id:
                    logger.info("Creating new Casual Viewing conversation")
                    conversation = Conversation.objects.create(mode='casual')
                    request.session['casual_conversation_id'] = conversation.id
                    logger.info(f"New Casual Viewing conversation created with ID: {conversation.id}")
                else:
                    logger.info(f"Using existing Casual Viewing conversation with ID: {conversation_id}")
                    try:
                        conversation = Conversation.objects.get(id=conversation_id)
                        # Set mode if it's not already set (for backwards compatibility)
                        if conversation.mode != 'casual':
                            conversation.mode = 'casual'
                            conversation.save()
                    except Conversation.DoesNotExist:
                        # Create a new one if the stored ID doesn't exist
                        conversation = Conversation.objects.create(mode='casual')
                        request.session['casual_conversation_id'] = conversation.id

            # Robust JSON parsing with comprehensive logging
            try:
                # First, log the raw request body
                raw_body = request.body.decode('utf-8')
                logger.debug(f"Raw request body: {raw_body}")
                logger.debug(f"Request method: {request.method}")
                logger.debug(f"Request content type: {request.content_type}")
                logger.debug(f"Request headers: {dict(request.headers)}")

                # Try parsing with multiple strategies
                try:
                    # First, standard json.loads
                    data = json.loads(raw_body)
                except json.JSONDecodeError:
                    # Handle common JSON parsing issues
                    try:
                        # Try parsing with single quotes or other variations
                        data = json.loads(raw_body.replace("'", '"'))
                    except Exception as parse_error:
                        # If all parsing fails, try extracting message by simple string operations
                        if raw_body.startswith('"') and raw_body.endswith('"'):
                            data = {"message": raw_body.strip('"')}
                            logger.warning("Received message as a raw string, converted to dictionary")
                        else:
                            logger.error(f"Failed to parse request body: {parse_error}")
                            return JsonResponse({
                                'status': 'error',
                                'message': 'Invalid request format. Could not parse message.'
                            }, status=400)

                # Ensure data is a dictionary
                if not isinstance(data, dict):
                    logger.error(f"Request data is not a dictionary after parsing: {data}")
                    return JsonResponse({
                        'status': 'error',
                        'message': 'Invalid request format'
                    }, status=400)

                # Extract user message with multiple fallback strategies
                user_message_text = (
                    data.get('message') or
                    data.get('text') or
                    data.get('query') or
                    ''
                )

                # Additional safety check to ensure it's a string
                if not isinstance(user_message_text, str):
                    logger.error(f"User message is not a string: {user_message_text}")
                    user_message_text = str(user_message_text)

                # Trim message to reasonable length
                user_message_text = user_message_text[:500]  # Limit to 500 characters

                logger.info(f"Processed user message: {user_message_text}")

                # Extract location with multiple keys and improved handling
                location = (
                    data.get('location') or
                    data.get('city') or
                    data.get('loc') or
                    request.session.get('user_location') or
                    'Unknown'
                )
                # Store location in session for future use
                request.session['user_location'] = location

                # Extract first run filter preference (default to True for first run movie mode)
                first_run_filter = data.get('first_run_filter', True)
                # Convert string representation to boolean if needed
                if isinstance(first_run_filter, str):
                    first_run_filter = first_run_filter.lower() == 'true'
                logger.info(f"First run filter: {first_run_filter}")

                # Log location details
                if location and location.lower() != 'unknown':
                    logger.info(f"User provided location: {location}")
                else:
                    logger.info("No user location provided, using default")

            except Exception as parsing_error:
                logger.error(f"Comprehensive parsing error: {str(parsing_error)}")
                logger.error(traceback.format_exc())
                return JsonResponse({
                    'status': 'error',
                    'message': 'Failed to process your request. Please try again.'
                }, status=400)

            # Save user message with additional logging
            try:
                user_message = Message.objects.create(
                    conversation=conversation,
                    sender='user',
                    content=user_message_text
                )
                logger.info(f"Saved user message: {user_message.id}")
            except Exception as save_error:
                logger.error(f"Error saving user message: {str(save_error)}")
                logger.error(traceback.format_exc())
                return JsonResponse({
                    'status': 'error',
                    'message': 'Could not save your message. Please try again.'
                }, status=500)

            # Initialize the movie crew with robust configuration
            try:
                # Validate configuration before initialization
                if not settings.LLM_CONFIG.get('api_key'):
                    logger.error("Missing API key in LLM_CONFIG")
                    return JsonResponse({
                        'status': 'error',
                        'message': 'LLM API key is not configured. Please check your environment variables.'
                    }, status=500)

                logger.info(f"Initializing MovieCrewManager with model: {settings.LLM_CONFIG.get('model', 'gpt-4o-mini')}")
                logger.info(f"Base URL configured: {'Yes' if settings.LLM_CONFIG.get('base_url') else 'No'}")
                logger.info(f"TMDb API key configured: {'Yes' if settings.TMDB_API_KEY else 'No'}")
                logger.info(f"User location: {location}")

                # Get client IP address for geolocation
                client_ip = get_client_ip(request)
                logger.info(f"Client IP: {client_ip}")

                movie_crew_manager = MovieCrewManager(
                    api_key=settings.LLM_CONFIG['api_key'],
                    base_url=settings.LLM_CONFIG.get('base_url'),
                    model=settings.LLM_CONFIG.get('model', 'gpt-4o-mini'),
                    tmdb_api_key=settings.TMDB_API_KEY,
                    user_location=location,
                    user_ip=client_ip  # Pass the IP directly in constructor
                )
                logger.info("Movie crew manager initialized successfully")
            except Exception as init_error:
                logger.error(f"Error initializing movie crew manager: {str(init_error)}")
                logger.error(traceback.format_exc())
                return JsonResponse({
                    'status': 'error',
                    'message': 'Could not initialize movie assistant. Please try again.'
                }, status=500)

            # Process the message with extensive error handling
            try:
                # Prepare conversation history with clear logging
                conversation_messages = conversation.messages.all()
                logger.info(f"Found {conversation_messages.count()} messages in conversation history")

                conversation_history = [{
                    'sender': msg.sender,
                    'content': msg.content
                } for msg in conversation_messages]

                logger.debug(f"Conversation history preview: {conversation_history[-3:] if len(conversation_history) > 3 else conversation_history}")

                # Adjust the query to indicate first run filter preference if in casual viewing mode
                query_to_use = user_message_text
                if not first_run_filter:
                    # Add a context hint for casual viewing mode (no theaters/showtimes needed)
                    query_to_use = f"{user_message_text} [CASUAL_MODE: Focus on recommendations only, no theater information needed]"
                    logger.info(f"Using casual mode query: '{query_to_use[:50]}{'...' if len(query_to_use) > 50 else ''}'")
                else:
                    logger.info(f"Using first run mode query: '{query_to_use[:50]}{'...' if len(query_to_use) > 50 else ''}'")

                # Track processing time
                import time
                start_time = time.time()

                response_data = movie_crew_manager.process_query(
                    query=query_to_use,
                    conversation_history=conversation_history,
                    first_run_mode=first_run_filter
                )

                processing_time = time.time() - start_time
                logger.info(f"Query processing completed in {processing_time:.2f} seconds")

                logger.info(f"Response data type: {type(response_data)}")
                logger.info(f"Response data keys: {response_data.keys() if isinstance(response_data, dict) else 'Not a dictionary'}")

                if isinstance(response_data, dict) and 'movies' in response_data:
                    logger.info(f"Received {len(response_data.get('movies', []))} movie recommendations")
            except Exception as process_error:
                logger.error(f"Error processing query: {str(process_error)}")
                logger.error(traceback.format_exc())
                # Instead of returning an error, we'll set response_data to a default value and continue
                response_data = {
                    'response': 'I encountered an issue while processing your request about movies. Please try asking in a different way or check back later.',
                    'movies': []
                }
                logger.info("Set default response_data after catching process_query exception")

            # Handle the case when response_data is None
            if response_data is None:
                logger.warning("response_data is None from movie_crew_manager.process_query")
                response_data = {
                    'response': 'I encountered an issue while searching for movies. Please try again or be more specific.',
                    'movies': []
                }

            # Save chatbot response
            try:
                # Safely extract response content
                bot_response = response_data.get('response', 'Sorry, I could not generate a response.')

                bot_message = Message.objects.create(
                    conversation=conversation,
                    sender='bot',
                    content=bot_response
                )
                logger.info(f"Saved bot message: {bot_message.id}")
            except Exception as save_error:
                logger.error(f"Error saving bot message: {str(save_error)}")
                logger.error(traceback.format_exc())
                bot_response = 'Sorry, I could not generate a response.'

            # Save movie recommendations with error handling
            try:
                # Safely extract movies, default to empty list
                movies = response_data.get('movies', [])
                if not isinstance(movies, list):
                    logger.warning(f"Movies data is not a list, type: {type(movies)}")
                    movies = []

                saved_movies = []
                for movie_index, movie_data in enumerate(movies):
                    logger.debug(f"Processing movie {movie_index+1}/{len(movies)}: {movie_data.get('title', 'Unknown')}")

                    # Validate required fields
                    if not movie_data.get('title'):
                        logger.warning(f"Movie {movie_index+1} missing title, using default")

                    # Robust data extraction with defaults
                    movie = MovieRecommendation.objects.create(
                        conversation=conversation,
                        title=movie_data.get('title', 'Unknown Movie'),
                        overview=movie_data.get('overview', ''),
                        poster_url=movie_data.get('poster_url', ''),
                        release_date=movie_data.get('release_date'),
                        tmdb_id=movie_data.get('tmdb_id'),
                        rating=movie_data.get('rating')
                    )
                    saved_movies.append(movie)

                    # Track theaters data
                    theaters_count = 0
                    showtimes_count = 0

                    # Check if this is a current release movie before processing theaters
                    is_current_release = movie_data.get('is_current_release', False)

                    # Save associated theaters and showtimes - only for current releases and in first run mode
                    if first_run_filter and is_current_release and 'theaters' in movie_data and movie_data['theaters']:
                        theaters_data = movie_data['theaters']
                        logger.debug(f"Found {len(theaters_data)} theaters for current movie: {movie.title}")

                        for theater_index, theater_data in enumerate(theaters_data):
                            logger.debug(f"Processing theater {theater_index+1}/{len(theaters_data)}: {theater_data.get('name', 'Unknown')}")

                            theater, created = Theater.objects.get_or_create(
                                name=theater_data.get('name', 'Unknown Theater'),
                                defaults={
                                    'address': theater_data.get('address', ''),
                                    'latitude': theater_data.get('latitude'),
                                    'longitude': theater_data.get('longitude')
                                }
                            )
                            theaters_count += 1

                            # Log whether we created a new theater or found existing
                            if created:
                                logger.debug(f"Created new theater: {theater.name}")
                            else:
                                logger.debug(f"Using existing theater: {theater.name}")

                            # Save showtimes
                            if 'showtimes' in theater_data:
                                showtime_data_list = theater_data['showtimes']
                                logger.debug(f"Found {len(showtime_data_list)} showtimes for theater: {theater.name}")

                                for showtime_data in showtime_data_list:
                                    # Try to parse the start time
                                    try:
                                        start_time_str = showtime_data.get('start_time', '')
                                        if start_time_str:
                                            start_time = datetime.fromisoformat(start_time_str.replace(' ', 'T'))
                                        else:
                                            continue
                                    except (ValueError, TypeError):
                                        continue

                                    # Create or update showtime
                                    Showtime.objects.create(
                                        movie=movie,
                                        theater=theater,
                                        start_time=start_time,
                                        format=showtime_data.get('format', 'Standard')
                                    )
                                    showtimes_count += 1
                    else:
                        if not first_run_filter:
                            logger.info(f"Movie '{movie.title}' processed in casual viewing mode, skipping theaters and showtimes")
                        elif not is_current_release:
                            logger.info(f"Movie '{movie.title}' is not a current release, skipping theaters and showtimes")
                        else:
                            logger.debug(f"No theaters found for movie: {movie.title}")

                    logger.info(f"Movie '{movie.title}' saved with {theaters_count} theaters and {showtimes_count} showtimes")

                logger.info(f"Saved {len(saved_movies)} movie recommendations out of {len(movies)} received")
            except Exception as rec_error:
                logger.error(f"Error saving movie recommendations: {str(rec_error)}")
                logger.error(traceback.format_exc())

            # Return response with comprehensive error handling
            try:
                # Fetch recent recommendations
                recent_recs = conversation.recommendations.order_by('-created_at')[:5]

                # Debug logging of theaters/showtimes
                for rec in recent_recs:
                    showtime_count = rec.showtimes.count()
                    if showtime_count > 0:
                        logger.info(f"Movie {rec.title} has {showtime_count} showtimes in database")
                    else:
                        logger.warning(f"Movie {rec.title} has NO showtimes in database")

                # Check if this is a current year movie
                from datetime import datetime
                current_year = datetime.now().year

                recommendations_data = [{
                    'id': rec.id,
                    'title': rec.title,
                    'overview': rec.overview,
                    'poster_url': rec.poster_url,
                    'release_date': rec.release_date.isoformat() if rec.release_date else None,
                    'rating': float(rec.rating) if rec.rating else None,
                    # Mark as current release if it's from current year or if it has showtimes
                    'is_current_release': (rec.release_date and rec.release_date.year >= current_year - 1) or rec.showtimes.exists(),
                    # Properly format theater data with all required fields
                    'theaters': [{
                        'name': showtime.theater.name,
                        'address': showtime.theater.address,
                        'distance_miles': 5.0,  # Default distance if not available
                        'showtimes': [{
                            'start_time': showtime.start_time.isoformat(),
                            'format': showtime.format
                        }]
                    } for showtime in rec.showtimes.all()]
                } for rec in recent_recs]

                return JsonResponse({
                    'status': 'success',
                    'message': bot_response,
                    'recommendations': recommendations_data
                })
            except Exception as final_error:
                logger.error(f"Final response generation error: {str(final_error)}")
                logger.error(traceback.format_exc())
                return JsonResponse({
                    'status': 'success',
                    'message': bot_response,
                    'recommendations': []
                })

        except Exception as global_error:
            logger.error(f"Global error in send_message: {str(global_error)}")
            logger.error(traceback.format_exc())
            return JsonResponse({
                'status': 'error',
                'message': 'An unexpected error occurred. Please try again.'
            }, status=500)

    return JsonResponse({
        'status': 'error',
        'message': 'Invalid request method'
    }, status=400)

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
