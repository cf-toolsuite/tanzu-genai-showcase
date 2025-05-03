"""
Common view utilities and helpers for the chatbot application.
This module provides shared functions used across various views.
"""

import json
import logging
from django.shortcuts import render, redirect
from django.conf import settings
from django.utils import timezone
from ..models import Conversation, Message

# Configure logger
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
