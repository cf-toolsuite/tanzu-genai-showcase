# movie_chatbot/settings/validation.py

import logging
from . import config_loader, external_apis, llm

logger = logging.getLogger(__name__)

def validate_required_configuration():
    """
    Validate that all required configuration values are present.
    This function should be called during application startup.

    Logs warnings for missing configuration values.
    """
    logger.info("Validating required configuration...")

    # Check for LLM API key
    if not llm.LLM_CONFIG.get('api_key'):
        logger.error("CRITICAL: LLM API key is missing. The application will not function correctly.")
    else:
        logger.info("LLM API key is present.")

    # Check for TMDB API key
    if not external_apis.TMDB_API_KEY:
        logger.error("CRITICAL: TMDB API key is missing. Movie image enhancement will be unavailable.")
    else:
        logger.info("TMDB API key is present.")

    # Check for SerpAPI key (optional but recommended)
    if not external_apis.SERPAPI_API_KEY:
        logger.warning("SerpAPI key is missing. Theater and showtime data may be limited.")
    else:
        logger.info("SerpAPI key is present.")

    # Log configuration sources
    logger.info("Configuration sources:")
    logger.info(f"  Running on Cloud Foundry: {config_loader.cf_service_config.is_running_on_cloud_foundry()}")

    # Check if config.json was loaded
    if hasattr(config_loader, 'config_json') and config_loader.config_json:
        logger.info(f"  config.json: loaded with {len(config_loader.config_json)} keys")
    else:
        logger.info("  config.json: not loaded or empty")

    # Check for .env file
    from pathlib import Path
    env_file = Path(config_loader.BASE_DIR) / '.env'
    if env_file.exists():
        logger.info("  .env file: present")
    else:
        logger.info("  .env file: not found")

    logger.info("Configuration validation complete.")
