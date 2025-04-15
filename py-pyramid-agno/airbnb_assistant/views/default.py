"""
Views for the Airbnb Assistant Pyramid application
"""
import json
import logging
import uuid
import os
import requests
import re
from pyramid.view import view_config
from pyramid.response import Response
from pyramid.httpexceptions import HTTPFound, HTTPBadRequest

from ..models import DBSession, User, ChatSession, ChatMessage
from ..ai.mcp.client import MCPAirbnbClient
from ..ai.mcp.clients import create_mcp_client
from ..ai.agent import AirbnbAssistantAgent

log = logging.getLogger(__name__)


def create_mcp_client_from_config(request):
    """
    Create an MCP client based on request configuration and environment variables

    This function is deprecated and will be removed in a future version.
    Use AirbnbAssistantAgent to manage the MCP client instead.

    Args:
        request: The pyramid request object containing registry settings

    Returns:
        An appropriate MCP client instance
    """
    log.warning("create_mcp_client_from_config is deprecated - use AirbnbAssistantAgent instead")
    # Get MCP configuration from settings and environment
    mcp_url = request.registry.settings.get('mcp.airbnb_url')
    use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
    use_stdio = os.environ.get('MCP_USE_STDIO', 'false').lower() == 'true'
    mcp_server_path = os.environ.get('MCP_SERVER_PATH')

    # Determine command and args for stdio client
    mcp_command = None
    mcp_args = None

    if use_stdio and mcp_server_path:
        # Determine the command and args based on file extension
        if mcp_server_path.endswith('.js'):
            mcp_command = 'node'
            mcp_args = [mcp_server_path]
        elif mcp_server_path.endswith('.py'):
            mcp_command = 'python'
            mcp_args = [mcp_server_path]
        else:
            # Default to just executing the file directly
            mcp_command = mcp_server_path
            mcp_args = []

    # Create the client using the factory
    client = create_mcp_client(
        use_mock=use_mock,
        use_stdio=use_stdio,
        mcp_url=mcp_url,
        mcp_command=mcp_command,
        mcp_args=mcp_args
    )

    client_type = type(client).__name__
    log.info(f"Created MCP client of type: {client_type}")

    return client


@view_config(route_name='home', renderer='home.mako')
def home_view(request):
    """
    Home page view
    """
    return {'project': 'Airbnb Assistant'}


@view_config(route_name='chat')
def chat_view(request):
    """
    Chat endpoint for communicating with the Airbnb AI agent
    """
    try:
        # Parse the request
        try:
            body = request.json_body
        except Exception as e:
            log.error(f"Error parsing JSON body: {e}")
            # Try to get the raw body and parse it manually
            raw_body = request.body.decode('utf-8')
            log.info(f"Raw body: {raw_body}")
            try:
                body = json.loads(raw_body)
            except Exception as je:
                log.error(f"Error parsing raw body as JSON: {je}")
                return Response(
                    json_body={'error': 'Invalid JSON in request body'},
                    content_type='application/json',
                    status=400
                )

        message = body.get('message')
        session_id = body.get('session_id')

        if not message:
            return Response(
                json_body={'error': 'Message is required'},
                content_type='application/json',
                status=400
            )

        log.info(f"Received message: {message}")

        # Create a new session if one doesn't exist
        if not session_id:
            session_id = str(uuid.uuid4())
            log.info(f"Created new session with ID: {session_id}")
        else:
            log.info(f"Using existing session with ID: {session_id}")

        # Get the AI agent configuration
        genai_config = request.registry.settings.get('genai', {})
        mcp_url = request.registry.settings.get('mcp.airbnb_url')
        mcp_use_stdio = os.environ.get('MCP_USE_STDIO', 'false').lower() == 'true'
        mcp_server_path = os.environ.get('MCP_SERVER_PATH')

        # Determine command and args for stdio client
        mcp_command = None
        mcp_args = None

        if mcp_use_stdio and mcp_server_path:
            # Determine the command and args based on file extension
            if mcp_server_path.endswith('.js'):
                mcp_command = 'node'
                mcp_args = [mcp_server_path]
            elif mcp_server_path.endswith('.py'):
                mcp_command = 'python'
                mcp_args = [mcp_server_path]
            else:
                # Default to just executing the file directly
                mcp_command = mcp_server_path
                mcp_args = []

        # Create the AI agent directly (will create its own MCP client)
        agent = AirbnbAssistantAgent(
            api_key=os.environ.get('OPENAI_API_KEY', genai_config.get('api_key')),
            model=genai_config.get('model', 'gpt-4'),
            mcp_url=mcp_url,
            mcp_use_stdio=mcp_use_stdio,
            mcp_command=mcp_command,
            mcp_args=mcp_args
        )

        # Get the response from the agent
        try:
            try:
                result = agent.process_query(message, session_id)

                # Clean up the response
                response_text = result.get('response', 'I could not generate a response.')

                # Additional cleaning for any control characters
                response_text = clean_response_text(response_text)

                response_data = {
                    'session_id': session_id,
                    'response': response_text,
                    'context': result.get('context', {}),
                    'success': True
                }

                # Return a proper JSON response
                return Response(
                    json_body=response_data,
                    content_type='application/json'
                )
            except RuntimeError as e:
                # Handle MCP client errors specifically
                log.error(f"MCP client error in agent: {e}")

                # Return a properly formatted error message
                error_message = f"I'm sorry, but I encountered an error while searching for Airbnb listings: {str(e)}"

                # Return a proper JSON response with the error
                return Response(
                    json_body={
                        'session_id': session_id,
                        'response': error_message,
                        'context': {'error': str(e)},
                        'success': False
                    },
                    content_type='application/json'
                )
        finally:
            # Clean up agent resources
            if hasattr(agent, 'cleanup'):
                try:
                    agent.cleanup()
                except Exception as e:
                    log.error(f"Error cleaning up agent resources: {e}")
    except Exception as e:
        log.error(f"Error in chat endpoint: {e}")
        # Make sure to use json_body instead of json.dumps
        error_message = "I'm sorry, but I encountered an unexpected error. Please try again later."
        return Response(
            json_body={
                'session_id': session_id if 'session_id' in locals() else str(uuid.uuid4()),
                'response': error_message,
                'context': {'error': str(e)},
                'success': False
            },
            content_type='application/json',
            status=500
        )


def clean_response_text(text):
    """Clean response text from control characters and ANSI codes"""
    if not text:
        return ""

    # Remove ANSI escape sequences
    ansi_escape = re.compile(r'\x1B(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])')
    text = ansi_escape.sub('', text)

    # Remove control characters except newlines and tabs
    control_chars = ''.join([chr(x) for x in range(32) if x not in [9, 10, 13]])
    control_char_re = re.compile('[%s]' % re.escape(control_chars))
    text = control_char_re.sub('', text)

    # Remove any [0m][36m type strings (ANSI color codes without escape characters)
    text = re.sub(r'\[\d+m\]', '', text)

    # Remove any standalone numbers in brackets like [36m]
    text = re.sub(r'\[\d+m\]', '', text)

    # Remove any timestamp-like patterns [XXm] that might remain
    text = re.sub(r'\[\d+m', '', text)

    # Fix extra bracket characters
    text = re.sub(r'\]\[', ' ', text)

    # Remove repetitive pipe characters (|) that might appear in formatting
    text = re.sub(r'\s*\|\s*\|\s*\|+\s*', ' ', text)  # Multiple pipes with spaces
    text = re.sub(r'\|\s*\|\s*', ' ', text)  # Double pipes with spaces
    text = re.sub(r'^\s*\|\s*', '', text, flags=re.MULTILINE)  # Pipe at start of line
    text = re.sub(r'\s*\|\s*$', '', text, flags=re.MULTILINE)  # Pipe at end of line

    # Remove any excessive whitespace
    text = re.sub(r'\s+', ' ', text)  # Replace multiple spaces with one
    text = re.sub(r'\n\s*\n\s*\n+', '\n\n', text)  # No more than 2 consecutive newlines

    # Fix common formatting issues from Agno
    text = text.replace('1.', '\n1.')  # Ensure numbered lists have proper line breaks
    text = text.replace('2.', '\n2.')
    text = text.replace('3.', '\n3.')
    text = text.replace('4.', '\n4.')

    # General cleanup
    text = text.strip()

    # Replace multiple consecutive spaces with a single space
    text = re.sub(r' +', ' ', text)

    # Ensure proper spacing after punctuation
    text = re.sub(r'(\.|,|:|;|\?|!)([a-zA-Z])', r'\1 \2', text)

    return text


@view_config(route_name='search')
def search_view(request):
    """
    Search endpoint for Airbnb listings
    """
    try:
        try:
            body = request.json_body
        except Exception as e:
            log.error(f"Error parsing JSON body: {e}")
            # Try to get the raw body and parse it manually
            raw_body = request.body.decode('utf-8')
            try:
                body = json.loads(raw_body)
            except Exception as je:
                log.error(f"Error parsing raw body as JSON: {je}")
                return Response(
                    json_body={'error': 'Invalid JSON in request body'},
                    content_type='application/json',
                    status=400
                )

        query = body.get('query')
        check_in = body.get('check_in')
        check_out = body.get('check_out')
        guests = body.get('guests', 1)
        limit = body.get('limit', 5)

        if not query:
            return Response(
                json_body={'error': 'Query is required'},
                content_type='application/json',
                status=400
            )

        # Get MCP configuration
        genai_config = request.registry.settings.get('genai', {})
        mcp_url = request.registry.settings.get('mcp.airbnb_url')
        mcp_use_stdio = os.environ.get('MCP_USE_STDIO', 'false').lower() == 'true'
        mcp_server_path = os.environ.get('MCP_SERVER_PATH')

        # Determine command and args for stdio client
        mcp_command = None
        mcp_args = None

        if mcp_use_stdio and mcp_server_path:
            # Determine the command and args based on file extension
            if mcp_server_path.endswith('.js'):
                mcp_command = 'node'
                mcp_args = [mcp_server_path]
            elif mcp_server_path.endswith('.py'):
                mcp_command = 'python'
                mcp_args = [mcp_server_path]
            else:
                # Default to just executing the file directly
                mcp_command = mcp_server_path
                mcp_args = []

        # Create the AI agent to get its MCP client
        agent = AirbnbAssistantAgent(
            api_key=os.environ.get('OPENAI_API_KEY', genai_config.get('api_key')),
            model=genai_config.get('model', 'gpt-4'),
            mcp_url=mcp_url,
            mcp_use_stdio=mcp_use_stdio,
            mcp_command=mcp_command,
            mcp_args=mcp_args
        )

        # Use the agent's MCP client
        mcp_client = agent.mcp_client
        log.info(f"Using MCP client from agent for search endpoint")

        try:
            try:
                # Search for listings
                listings = mcp_client.search_listings(
                    location=query,
                    check_in=check_in,
                    check_out=check_out,
                    guests=guests,
                    limit=limit
                )

                response_data = {
                    'results': listings,
                    'success': True
                }

                return Response(
                    json_body=response_data,
                    content_type='application/json'
                )
            except RuntimeError as e:
                # Handle MCP client errors specifically
                log.error(f"MCP client error: {e}")

                # Return a proper error response to the client
                return Response(
                    json_body={
                        'results': [],
                        'success': False,
                        'error': f"Error communicating with MCP server: {str(e)}"
                    },
                    content_type='application/json'
                )
        finally:
            # Make sure to clean up the agent (which will clean up its mcp_client)
            if hasattr(agent, 'cleanup'):
                try:
                    agent.cleanup()
                except Exception as e:
                    log.error(f"Error cleaning up agent resources: {e}")
    except Exception as e:
        log.error(f"Error in search endpoint: {e}")
        return Response(
            json_body={
                'results': [],
                'success': False,
                'error': f"An unexpected error occurred: {str(e)}"
            },
            content_type='application/json',
            status=500
        )


@view_config(route_name='toggle_theme')
def toggle_theme_view(request):
    """
    Toggle theme endpoint for switching between light and dark themes
    """
    try:
        # Get the current theme from the session
        current_theme = request.session.get('theme', 'light')

        # Toggle the theme
        new_theme = 'dark' if current_theme == 'light' else 'light'

        # Save the new theme in the session
        request.session['theme'] = new_theme

        return Response(
            json_body={'theme': new_theme},
            content_type='application/json'
        )
    except Exception as e:
        log.error(f"Error in toggle_theme endpoint: {e}")
        return Response(
            json_body={'error': str(e)},
            content_type='application/json',
            status=500
        )
