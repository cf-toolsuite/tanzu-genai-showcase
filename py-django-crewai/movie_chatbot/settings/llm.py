# movie_chatbot/settings/llm.py

import os
import json
import logging
import cfenv
from .base import DEBUG

logger = logging.getLogger(__name__)

# --- Cloud Foundry Environment Setup ---
cf_env = cfenv.AppEnv()
logger.info("Initialized cfenv.AppEnv() for LLM config.")

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
            config['model'] = "openai/" + credentials['model_name']

        return config

# --- LLM Configuration Function ---
def get_llm_config():
    processor = GenAIChatProcessor()

    # Try to find GenAI service
    for service in cf_env.services:
        if processor.accept(service):
            logger.info(f"LLM Config: Found GenAI chat service: {service.name}")
            config = processor.process(service.credentials)
            if config:
                logger.info(f"LLM Config: Successfully extracted configuration")
                return config

    # Fallback: Try finding by specific labels
    try:
        genai_service = cf_env.get_service(label='genai')
        if genai_service:
            logger.info(f"LLM Config: Found service binding with label 'genai': {genai_service.name}")
            config = processor.process(genai_service.credentials)
            if config:
                return config
    except Exception as e:
        logger.error(f"LLM Config: Error getting service with label 'genai': {e}", exc_info=True)

    # Fallback: Try finding by name
    try:
        genai_service = cf_env.get_service(name='movie-chatbot-llm')
        if genai_service:
            logger.info(f"LLM Config: Found service binding with name 'movie-chatbot-llm'")
            config = processor.process(genai_service.credentials)
            if config:
                return config
    except Exception as e:
        logger.error(f"LLM Config: Error getting service with name 'movie-chatbot-llm': {e}", exc_info=True)

    # Fallback to environment variables
    config = {
        'api_key': os.getenv('OPENAI_API_KEY') or os.getenv('LLM_API_KEY'),
        'base_url': os.getenv('LLM_BASE_URL') or os.getenv('OPENAI_BASE_URL'),
        'model': os.getenv('LLM_MODEL', 'gpt-4o-mini')
    }

    # Log configuration status
    if not config['api_key']:
        logger.error("LLM Config: CRITICAL - Could not find API key.")
    if not config['base_url']:
        logger.warning("LLM Config: Could not find base URL.")
    if config['api_key'] or config['base_url']:
        logger.info("LLM Config: Using configuration from environment variables.")

    return config

# --- Cloud Foundry Diagnostics Function ---
def diagnose_cf_environment():
    """Print diagnostic information about CF environment and services"""
    logger.info("--- CF Environment Diagnostics ---")
    # Basic check using cfenv attributes
    is_cf = hasattr(cf_env, 'app') and cf_env.app is not None
    logger.info(f"Running in Cloud Foundry (detected by cfenv): {is_cf}")

    if is_cf:
        try:
            logger.info(f"App Name: {cf_env.name}, Instance Index: {cf_env.index}")
            # Access space/org info if available (might require VCAP_APPLICATION)
            vcap_app = cf_env.app if hasattr(cf_env, 'app') else {}
            logger.info(f"Space: {vcap_app.get('space_name', 'N/A')}, Org: {vcap_app.get('organization_name', 'N/A')}")

            all_services = cf_env.services
            if all_services:
                logger.info(f"Bound Services ({len(all_services)}):")
                for service in all_services:
                    # Use cfenv's service object attributes with safe access
                    service_info = []
                    if hasattr(service, 'name'):
                        service_info.append(f"Name: {service.name}")
                    if hasattr(service, 'label'):
                        service_info.append(f"Label: {service.label}")
                    elif hasattr(service, 'tags') and service.tags:
                        service_info.append(f"Tags: {', '.join(service.tags)}")
                    if hasattr(service, 'plan'):
                        service_info.append(f"Plan: {service.plan}")

                    logger.info(f"  - {', '.join(service_info)}")

                    if hasattr(service, 'credentials') and service.credentials:
                        if isinstance(service.credentials, dict):
                            logger.info(f"    Credential Keys: {list(service.credentials.keys())}")
                            if 'credhub-ref' in service.credentials:
                                logger.info(f"    Contains credhub-ref: {service.credentials['credhub-ref']}")

                            # Check for model_capabilities specifically
                            if 'model_capabilities' in service.credentials:
                                logger.info(f"    Model Capabilities: {service.credentials['model_capabilities']}")
                        else:
                            logger.info(f"    Credentials type: {type(service.credentials)}")
                    else:
                        logger.info("    Credentials not available or empty.")
            else:
                logger.info("No bound services found via cfenv.")
        except Exception as e:
            logger.error(f"Error during CF diagnostics: {e}", exc_info=True)
    else:
        logger.info("Not running in Cloud Foundry (or cfenv couldn't detect it).")
    logger.info("--- End CF Environment Diagnostics ---")

# --- Assign LLM Configuration ---
LLM_CONFIG = get_llm_config()

# Run diagnostics
if DEBUG:
    diagnose_cf_environment()