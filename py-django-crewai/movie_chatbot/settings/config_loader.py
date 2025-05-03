# movie_chatbot/settings/config_loader.py

import os
import json
import logging
from pathlib import Path
from . import cf_service_config

logger = logging.getLogger(__name__)

# --- Base Directory ---
BASE_DIR = Path(__file__).resolve().parent.parent.parent

# --- Load config.json if it exists ---
config_json = {}
config_json_path = BASE_DIR / 'config.json'
if config_json_path.exists():
    try:
        with open(config_json_path, 'r') as f:
            config_json = json.load(f)
        logger.info(f"Loaded configuration from {config_json_path}")
    except Exception as e:
        logger.error(f"Error loading config.json: {e}")
else:
    logger.debug(f"No config.json found at {config_json_path}")

def get_config(key, default=None, service_name='movie-chatbot-config'):
    """
    Get configuration value with priority:
    1. Service binding
    2. Environment variable
    3. config.json
    4. Default value

    Args:
        key: Configuration key to look for
        default: Default value if key is not found
        service_name: Name of the service to look for credentials

    Returns:
        The configuration value or default if not found
    """
    # 1. Check service binding
    value = cf_service_config.get_service_credential(service_name, key)
    if value is not None:
        return value

    # 2. Check environment variable
    value = os.getenv(key)
    if value is not None:
        return value

    # 3. Check config.json
    value = config_json.get(key)
    if value is not None:
        return value

    # 4. Return default
    return default

def get_required_config(key, service_name='movie-chatbot-config'):
    """
    Get required configuration value with priority:
    1. Service binding
    2. Environment variable
    3. config.json

    Raises:
        ValueError: If the configuration value is not found

    Returns:
        The configuration value
    """
    value = get_config(key, None, service_name)
    if value is None:
        raise ValueError(f"Required configuration '{key}' not found in service bindings, environment variables, or config.json")
    return value

def get_int_config(key, default=None, service_name='movie-chatbot-config'):
    """Get configuration value as integer"""
    value = get_config(key, default, service_name)
    if value is not None:
        try:
            return int(value)
        except (ValueError, TypeError):
            logger.warning(f"Could not convert '{key}' value to int: {value}")
    return default

def get_float_config(key, default=None, service_name='movie-chatbot-config'):
    """Get configuration value as float"""
    value = get_config(key, default, service_name)
    if value is not None:
        try:
            return float(value)
        except (ValueError, TypeError):
            logger.warning(f"Could not convert '{key}' value to float: {value}")
    return default

def get_bool_config(key, default=None, service_name='movie-chatbot-config'):
    """Get configuration value as boolean"""
    value = get_config(key, default, service_name)
    if value is not None:
        if isinstance(value, bool):
            return value
        if isinstance(value, str):
            return value.lower() in ('true', 'yes', '1', 't', 'y')
    return default
