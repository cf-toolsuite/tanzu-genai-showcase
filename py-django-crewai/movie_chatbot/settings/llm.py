# movie_chatbot/settings/llm.py

import os
import json
import logging
import cfenv
from .base import DEBUG
from . import config_loader, cf_service_config

logger = logging.getLogger(__name__)

# --- GenAI Service Processor ---
class GenAIChatProcessor:
    def accept(self, service):
        """Determine if this service is a GenAI service with chat capabilities"""
        if not service:
            return False

        # Check if service has genai tag or label starts with genai
        is_genai_service = False
        if hasattr(service, 'tags') and service.tags:
            is_genai_service = 'genai' in [tag.lower() for tag in service.tags]

        if not is_genai_service and hasattr(service, 'label'):
            is_genai_service = service.label and service.label.lower().startswith('genai')

        if not is_genai_service:
            return False

        # Check for chat capabilities
        credentials = service.credentials
        if (credentials and isinstance(credentials, dict) and
            'model_capabilities' in credentials and
            isinstance(credentials['model_capabilities'], list)):
            return 'chat' in credentials['model_capabilities']

        return False

    def process(self, credentials):
        """Extract specific properties from credentials"""
        if not credentials or not isinstance(credentials, dict):
            return None

        # Direct field extraction like the Java implementation
        config = {}

        if 'api_base' in credentials:
            config['base_url'] = credentials['api_base']

        if 'api_key' in credentials:
            config['api_key'] = credentials['api_key']

        if 'model_name' in credentials:
            if 'model_provider' in credentials:
                # Include provider as prefix if available
                config['model'] = f"{credentials['model_provider']}/{credentials['model_name']}"
                config['provider'] = credentials['model_provider']
            else:
                # Default to OpenAI provider if not specified
                config['model'] = "openai/" + credentials['model_name']
                config['provider'] = "openai"

        return config

# --- LLM Configuration Functions ---
def get_llm_config():
    """
    Get LLM configuration with priority:
    1. GenAI service binding
    2. User-provided service
    3. Environment variables
    4. config.json

    Returns:
        Dictionary with LLM configuration
    """
    # Initialize processor for GenAI services
    processor = GenAIChatProcessor()
    cf_env = cfenv.AppEnv()

    # 1. Try to find GenAI service
    for service in cf_env.services:
        if processor.accept(service):
            logger.info(f"LLM Config: Found GenAI chat service: {service.name}")
            config = processor.process(service.credentials)
            if config:
                logger.info(f"LLM Config: Successfully extracted configuration from GenAI service")
                return config

    # 2. Fallback: Try finding by specific labels
    try:
        genai_service = cf_env.get_service(label='genai')
        if genai_service:
            logger.info(f"LLM Config: Found service binding with label 'genai': {genai_service.name}")
            config = processor.process(genai_service.credentials)
            if config:
                logger.info(f"LLM Config: Successfully extracted configuration from 'genai' service")
                return config
    except Exception as e:
        logger.debug(f"LLM Config: Error getting service with label 'genai': {e}")

    # 3. Fallback: Try finding by name
    try:
        genai_service = cf_env.get_service(name='movie-chatbot-llm')
        if genai_service:
            logger.info(f"LLM Config: Found service binding with name 'movie-chatbot-llm'")
            config = processor.process(genai_service.credentials)
            if config:
                logger.info(f"LLM Config: Successfully extracted configuration from 'movie-chatbot-llm' service")
                return config
    except Exception as e:
        logger.debug(f"LLM Config: Error getting service with name 'movie-chatbot-llm': {e}")

    # 4. Fallback to environment variables or config.json
    model_str = config_loader.get_config('LLM_MODEL', 'gpt-4o-mini')
    provider = None

    # Extract provider from model string if present (format: "provider/model")
    if '/' in model_str:
        parts = model_str.split('/', 1)
        if len(parts) == 2:
            provider, model_name = parts
            logger.info(f"Detected provider in model string: {provider}/{model_name}")
    else:
        # Check if there's an explicit provider setting
        provider = config_loader.get_config('LLM_PROVIDER', 'openai')
        logger.info(f"Using provider from configuration: {provider}")

    config = {
        'api_key': config_loader.get_config('OPENAI_API_KEY') or config_loader.get_config('LLM_API_KEY'),
        'base_url': config_loader.get_config('LLM_BASE_URL') or config_loader.get_config('OPENAI_BASE_URL'),
        'model': model_str
    }

    # Only add provider if explicitly set
    if provider:
        config['provider'] = provider
        logger.info(f"Added provider '{provider}' to LLM configuration")

    # Log configuration status
    if not config['api_key']:
        logger.error("LLM Config: CRITICAL - Could not find API key.")
    if not config['base_url']:
        logger.warning("LLM Config: Could not find base URL.")
    if config['api_key'] or config['base_url']:
        logger.info("LLM Config: Using configuration from environment variables or config.json")

    return config

def get_llm_api_key():
    """Get LLM API key"""
    return LLM_CONFIG.get('api_key')

def get_llm_base_url():
    """Get LLM base URL"""
    return LLM_CONFIG.get('base_url')

def get_llm_model():
    """Get LLM model"""
    return LLM_CONFIG.get('model')

def get_llm_provider():
    """Get LLM provider"""
    return LLM_CONFIG.get('provider')

# --- Assign LLM Configuration ---
LLM_CONFIG = get_llm_config()

# Run diagnostics
if DEBUG:
    cf_service_config.diagnose_cf_environment()
