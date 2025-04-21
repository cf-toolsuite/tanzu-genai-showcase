"""
Django settings for movie_chatbot project.
"""

import os
import json
import logging
from pathlib import Path
from dotenv import load_dotenv
import dj_database_url
import cfenv

# Configure logging early
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Force loading environment variables from .env file (for local development)
env_file = os.path.join(os.path.dirname(os.path.dirname(__file__)), '.env')
if os.path.isfile(env_file):
    logger.info(f"Loading environment variables from {env_file}")
    load_dotenv(env_file, override=True)
else:
    logger.warning(f"No .env file found at {env_file}, using existing environment variables.")

# Build paths inside the project
BASE_DIR = Path(__file__).resolve().parent.parent

# SECURITY WARNING: keep the secret key used in production secret!
SECRET_KEY = os.getenv('DJANGO_SECRET_KEY', 'django-insecure-default-dev-key-replace-in-prod')

# SECURITY WARNING: don't run with debug turned on in production!
DEBUG = os.getenv('DJANGO_DEBUG', 'False').lower() == 'true'

ALLOWED_HOSTS = os.getenv('DJANGO_ALLOWED_HOSTS', '*').split(',')

# Application definition
INSTALLED_APPS = [
    'django.contrib.admin',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    'whitenoise.runserver_nostatic',
    'chatbot',
]

MIDDLEWARE = [
    'django.middleware.security.SecurityMiddleware',
    'whitenoise.middleware.WhiteNoiseMiddleware',
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
]

ROOT_URLCONF = 'movie_chatbot.urls'

TEMPLATES = [
    {
        'BACKEND': 'django.template.backends.django.DjangoTemplates',
        'DIRS': [os.path.join(BASE_DIR, 'templates')],
        'APP_DIRS': True,
        'OPTIONS': {
            'context_processors': [
                'django.template.context_processors.debug',
                'django.template.context_processors.request',
                'django.contrib.auth.context_processors.auth',
                'django.contrib.messages.context_processors.messages',
            ],
        },
    },
]

WSGI_APPLICATION = 'movie_chatbot.wsgi.application'

# Database
# https://docs.djangoproject.com/en/4.2/ref/settings/#databases

DATABASES = {
    'default': dj_database_url.config(
        default='sqlite:///' + os.path.join(BASE_DIR, 'db.sqlite3'),
        conn_max_age=600
    )
}

# Password validation
AUTH_PASSWORD_VALIDATORS = [
    {
        'NAME': 'django.contrib.auth.password_validation.UserAttributeSimilarityValidator',
    },
    {
        'NAME': 'django.contrib.auth.password_validation.MinimumLengthValidator',
    },
    {
        'NAME': 'django.contrib.auth.password_validation.CommonPasswordValidator',
    },
    {
        'NAME': 'django.contrib.auth.password_validation.NumericPasswordValidator',
    },
]

# Internationalization
LANGUAGE_CODE = 'en-us'
TIME_ZONE = 'UTC'
USE_I18N = True
USE_TZ = True

# Static files (CSS, JavaScript, Images)
STATIC_URL = 'static/'
STATIC_ROOT = os.path.join(BASE_DIR, 'staticfiles')
STATICFILES_DIRS = [os.path.join(BASE_DIR, 'static')]
STATICFILES_STORAGE = 'whitenoise.storage.CompressedManifestStaticFilesStorage'

# Default primary key field type
DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'

# Cloud Foundry environment
cf_env = cfenv.AppEnv()

# Helper function to extract credentials from Cloud Foundry credhub references
def extract_credhub_credentials(credhub_ref, binding_guid, instance_guid):
    """
    Extract credentials from Cloud Foundry credhub reference

    Args:
        credhub_ref (str): The credhub reference string
        binding_guid (str): The binding GUID from the service
        instance_guid (str): The instance GUID from the service

    Returns:
        dict: The extracted credentials or empty dict if not found
    """
    if not credhub_ref:
        return {}

    # Parse the reference to extract components
    # Format is usually: /c/{organization-guid}/{instance-guid}/{binding-guid}/credentials
    ref_parts = credhub_ref.strip('/').split('/')

    # Check various environment variable patterns that might contain the credentials
    # Pattern 1: VCAP_SERVICES_GENAI_CREDENTIALS_{binding_guid}
    env_var_name = f"VCAP_SERVICES_GENAI_CREDENTIALS_{binding_guid}"
    credentials_json = os.getenv(env_var_name)

    if credentials_json:
        try:
            return json.loads(credentials_json)
        except json.JSONDecodeError as e:
            logger.error(f"Failed to parse JSON from {env_var_name}: {e}")

    # Pattern 2: Search for environment variables containing the binding_guid
    for env_name, env_value in os.environ.items():
        if env_name.startswith('VCAP_SERVICES_GENAI_CREDENTIALS_') and binding_guid.lower() in env_name.lower():
            try:
                return json.loads(env_value)
            except json.JSONDecodeError:
                continue

    # Pattern 3: Check for instance_guid in environment variable names
    if instance_guid:
        for env_name, env_value in os.environ.items():
            if instance_guid.lower() in env_name.lower() and 'CREDENTIALS' in env_name.upper():
                try:
                    return json.loads(env_value)
                except json.JSONDecodeError:
                    continue

    # Last attempt: Look for any VCAP_SERVICES variable that might contain credentials
    for env_name, env_value in os.environ.items():
        if env_name.startswith('VCAP_SERVICES_') and 'CREDENTIALS' in env_name.upper():
            try:
                creds = json.loads(env_value)
                if isinstance(creds, dict) and len(creds) > 0:
                    logger.info(f"Found potential credentials in {env_name}")
                    return creds
            except json.JSONDecodeError:
                continue

    # No credentials found
    logger.warning(f"Could not extract credentials for credhub-ref: {credhub_ref}")
    return {}

# LLM Configuration
# Get LLM credentials from Cloud Foundry service binding or environment variables
def get_llm_config():
    # Check if running in Cloud Foundry with bound services
    genai_service = cf_env.get_service(label='genai')

    if genai_service:
        logger.info(f"Found GenAI service binding: {genai_service.name}")

        # Check if we need to handle credhub reference
        credentials = genai_service.credentials

        # Handle credhub reference if present
        if 'credhub-ref' in credentials:
            logger.info(f"Detected credhub reference in credentials: {credentials['credhub-ref']}")
            binding_guid = genai_service.get('binding_guid', '')
            instance_guid = genai_service.get('instance_guid', '')

            # Use our helper function to extract credentials
            credhub_credentials = extract_credhub_credentials(
                credentials['credhub-ref'],
                binding_guid,
                instance_guid
            )

            # Only update credentials if we found something
            if credhub_credentials:
                logger.info(f"Successfully extracted credentials from credhub reference")
                credentials = credhub_credentials
            else:
                logger.warning(f"Failed to extract credentials from credhub reference, using original credentials")

            # Log available credential keys (but not values)
            logger.info(f"Available credential keys: {list(credentials.keys())}")

            # The credentials might need JSON parsing if they are stored as strings
            for key in ['credentials', 'creds', 'config']:
                if key in credentials and isinstance(credentials[key], str):
                    try:
                        credentials[key] = json.loads(credentials[key])
                        logger.info(f"Parsed nested JSON in '{key}' field")
                    except json.JSONDecodeError:
                        pass

        # Extract configuration from credentials - handle nested structures
        api_key = None
        base_url = None
        model = None

        # Try to find credentials in possibly nested structure
        cred_sources = [credentials]

        # Check if credentials are nested inside another field
        for field in ['credentials', 'creds', 'config']:
            if field in credentials and isinstance(credentials[field], dict):
                cred_sources.append(credentials[field])

        # Try to find API key in various possible fields
        for src in cred_sources:
            # Common API key field names
            for key_field in ['api_key', 'apiKey', 'key', 'api-key', 'apikey', 'access_token', 'token']:
                if key_field in src and src[key_field]:
                    api_key = src[key_field]
                    logger.info(f"Found API key in field '{key_field}'")
                    break

            if api_key:  # If we found an API key, stop searching
                break

        # Try to find base URL in various possible fields
        for src in cred_sources:
            # Common base URL field names
            for url_field in ['url', 'baseUrl', 'base_url', 'endpoint', 'api_url', 'uri', 'host']:
                if url_field in src and src[url_field]:
                    base_url = src[url_field]
                    logger.info(f"Found base URL in field '{url_field}'")
                    break

            if base_url:  # If we found a base URL, stop searching
                break

        # Try to find model in various possible fields
        for src in cred_sources:
            # Common model field names
            for model_field in ['model', 'model_name', 'deployment', 'deployment_name', 'deployment_id']:
                if model_field in src and src[model_field]:
                    model = src[model_field]
                    logger.info(f"Found model in field '{model_field}'")
                    break

            if model:  # If we found a model, stop searching
                break

        # Use environment variables as fallback
        if not api_key:
            api_key = os.getenv('OPENAI_API_KEY') or os.getenv('LLM_API_KEY')

        if not base_url:
            base_url = os.getenv('LLM_BASE_URL') or os.getenv('OPENAI_BASE_URL')

        if not model:
            model = os.getenv('LLM_MODEL', 'gpt-4o-mini')

        # If we still don't have required values, log warnings
        if not api_key:
            logger.warning("Could not find API key in service credentials or environment")

        if not base_url:
            logger.warning("Could not find base URL in service credentials or environment")

        return {
            'api_key': api_key,
            'base_url': base_url,
            'model': model
        }

    # Alternative service name check for movie-chatbot-llm service
    movie_llm_service = cf_env.get_service(name='movie-chatbot-llm')
    if movie_llm_service:
        logger.info(f"Found movie-chatbot-llm service binding")
        # Use the same credential extraction logic for this service
        credentials = movie_llm_service.credentials

        # Handle credhub reference if present
        if 'credhub-ref' in credentials:
            logger.info(f"Detected credhub reference in movie-chatbot-llm credentials")
            binding_guid = movie_llm_service.get('binding_guid', '')
            instance_guid = movie_llm_service.get('instance_guid', '')

            credhub_credentials = extract_credhub_credentials(
                credentials['credhub-ref'],
                binding_guid,
                instance_guid
            )

            if credhub_credentials:
                logger.info(f"Successfully extracted credentials from credhub reference")
                credentials = credhub_credentials

        # Use the same credential extraction logic
        api_key = None
        base_url = None
        model = None

        # Try to find credentials in possibly nested structure
        cred_sources = [credentials]

        # Check if credentials are nested inside another field
        for field in ['credentials', 'creds', 'config']:
            if field in credentials and isinstance(credentials[field], dict):
                cred_sources.append(credentials[field])

        # Extract API key
        for src in cred_sources:
            for key_field in ['api_key', 'apiKey', 'key', 'api-key', 'apikey', 'access_token', 'token']:
                if key_field in src and src[key_field]:
                    api_key = src[key_field]
                    break
            if api_key:
                break

        # Extract base URL
        for src in cred_sources:
            for url_field in ['url', 'baseUrl', 'base_url', 'endpoint', 'api_url', 'uri', 'host']:
                if url_field in src and src[url_field]:
                    base_url = src[url_field]
                    break
            if base_url:
                break

        # Extract model
        for src in cred_sources:
            for model_field in ['model', 'model_name', 'deployment', 'deployment_name', 'deployment_id']:
                if model_field in src and src[model_field]:
                    model = src[model_field]
                    break
            if model:
                break

        # Fallback to environment variables
        if not api_key:
            api_key = os.getenv('OPENAI_API_KEY') or os.getenv('LLM_API_KEY')
        if not base_url:
            base_url = os.getenv('LLM_BASE_URL') or os.getenv('OPENAI_BASE_URL')
        if not model:
            model = os.getenv('LLM_MODEL', 'gpt-4o-mini')

        return {
            'api_key': api_key,
            'base_url': base_url,
            'model': model
        }

    # Fallback to environment variables for local development
    logger.info("No GenAI service binding found, using environment variables")
    return {
        'api_key': os.getenv('OPENAI_API_KEY'),
        'base_url': os.getenv('LLM_BASE_URL'),
        'model': os.getenv('LLM_MODEL', 'gpt-4o-mini')
    }

# Add diagnostic function for GenAI service binding
def diagnose_genai_service_binding():
    """Print diagnostic information about GenAI service binding"""
    try:
        # Check if we have a genai service
        genai_service = cf_env.get_service(label='genai')
        if genai_service:
            logger.info(f"Found GenAI service: {genai_service.name}")
            logger.info(f"  Plan: {genai_service.get('plan', 'unknown')}")
            logger.info(f"  Tags: {genai_service.get('tags', [])}")
            logger.info(f"  Instance GUID: {genai_service.get('instance_guid', 'unknown')}")
            logger.info(f"  Binding GUID: {genai_service.get('binding_guid', 'unknown')}")

            # Log credential keys (not values)
            credentials = genai_service.credentials
            if credentials:
                logger.info(f"  Credential keys: {list(credentials.keys())}")

                # Check for credhub reference
                if 'credhub-ref' in credentials:
                    logger.info(f"  Using credhub reference: {credentials['credhub-ref']}")

                    # Check for credential environment variables
                    binding_guid = genai_service.get('binding_guid', '')
                    matching_env_vars = [env for env in os.environ.keys()
                                        if env.startswith('VCAP_SERVICES_GENAI_CREDENTIALS_')
                                        and binding_guid.lower() in env.lower()]
                    logger.info(f"  Found {len(matching_env_vars)} matching environment variables: {matching_env_vars}")
            else:
                logger.warning("  No credentials found in service binding")
        else:
            logger.warning("No GenAI service found in bound services")

        # Log all available services for context
        all_services = cf_env.services
        if all_services:
            logger.info(f"All bound services: {[s.get('name') for s in all_services]}")
        else:
            logger.info("No bound services found")

    except Exception as e:
        logger.error(f"Error in diagnose_genai_service_binding: {e}", exc_info=True)

# Get LLM configuration
LLM_CONFIG = get_llm_config()

# Run diagnostics if in debug mode
if DEBUG:
    diagnose_genai_service_binding()

# The Movie Database API Key (for movie data)
TMDB_API_KEY = os.getenv('TMDB_API_KEY')

# SerpAPI Configuration for movie showtimes
SERPAPI_API_KEY = os.getenv('SERPAPI_API_KEY')

# Movie recommendation app configuration
# Number of movie results to return from search/discover APIs
MOVIE_RESULTS_LIMIT = int(os.getenv('MOVIE_RESULTS_LIMIT', '5'))
# Maximum number of recommended movies to return to the user
MAX_RECOMMENDATIONS = int(os.getenv('MAX_RECOMMENDATIONS', '3'))
# Radius in miles to search for theaters
THEATER_SEARCH_RADIUS_MILES = int(os.getenv('THEATER_SEARCH_RADIUS_MILES', '15'))
# Maximum showtimes per theater to limit data size
MAX_SHOWTIMES_PER_THEATER = int(os.getenv('MAX_SHOWTIMES_PER_THEATER', '10'))
# Maximum theaters to return in total
MAX_THEATERS = int(os.getenv('MAX_THEATERS', '5'))
# Default starting year for historical movie searches ("before X" queries)
DEFAULT_SEARCH_START_YEAR = int(os.getenv('DEFAULT_SEARCH_START_YEAR', '1900'))

# API Request Configuration
# Maximum seconds to wait for API responses
API_REQUEST_TIMEOUT = int(os.getenv('API_REQUEST_TIMEOUT_SECONDS', '30'))
# Maximum number of retry attempts for failed API requests
API_MAX_RETRIES = int(os.getenv('API_MAX_RETRIES', '3'))
# Exponential backoff factor between retries (in seconds)
API_RETRY_BACKOFF_FACTOR = float(os.getenv('API_RETRY_BACKOFF_FACTOR', '0.5'))

# SerpAPI Request Configuration
# Base delay between theater requests for different movies (seconds)
SERPAPI_REQUEST_BASE_DELAY = float(os.getenv('SERPAPI_REQUEST_BASE_DELAY', '8.0'))
# Additional delay per movie processed (seconds)
SERPAPI_PER_MOVIE_DELAY = float(os.getenv('SERPAPI_PER_MOVIE_DELAY', '3.0'))
# Maximum retries for SerpAPI requests
SERPAPI_MAX_RETRIES = int(os.getenv('SERPAPI_MAX_RETRIES', '3'))
# Base delay for exponential backoff during retries (seconds)
SERPAPI_BASE_RETRY_DELAY = float(os.getenv('SERPAPI_BASE_RETRY_DELAY', '5.0'))
# Multiplier for exponential backoff during retries
SERPAPI_RETRY_MULTIPLIER = float(os.getenv('SERPAPI_RETRY_MULTIPLIER', '2.0'))

# Enhanced Logging Configuration
LOGGING = {
    'version': 1,
    'disable_existing_loggers': False,
    'formatters': {
        'verbose': {
            'format': '{levelname} {asctime} {module} {process:d} {thread:d} {message}',
            'style': '{',
        },
        'simple': {
            'format': '{levelname} {message}',
            'style': '{',
        },
        'detailed': {
            'format': '[{asctime}] {levelname} {module}.{funcName} Line {lineno}: {message}',
            'style': '{',
        },
        'json': {
            'format': '{{"time": "{asctime}", "level": "{levelname}", "module": "{module}", "function": "{funcName}", "line": {lineno}, "message": "{message}"}}',
            'style': '{',
        },
        'dev_friendly': {
            'format': '\x1b[38;5;111m\u2502 {asctime} \u2502\x1b[0m \x1b[38;5;{color}m{levelname:<8}\x1b[0m \x1b[38;5;247m{module}.{funcName}:{lineno}\x1b[0m {message}',
            'style': '{',
        },
    },
    'filters': {
        'colorize': {
            '()': 'movie_chatbot.log_config.ColorizeFilter',
        },
    },
    'handlers': {
        'console': {
            'level': 'DEBUG' if DEBUG else 'INFO',
            'class': 'logging.StreamHandler',
            'formatter': 'dev_friendly',
            'filters': ['colorize'],
        },
        'file': {
            'level': 'DEBUG',
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': os.path.join(BASE_DIR, 'chatbot.log'),
            'formatter': 'detailed',
            'maxBytes': 10 * 1024 * 1024,  # 10 MB
            'backupCount': 5,
        },
        'json_file': {
            'level': 'DEBUG',
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': os.path.join(BASE_DIR, 'chatbot.json.log'),
            'formatter': 'json',
            'maxBytes': 10 * 1024 * 1024,  # 10 MB
            'backupCount': 5,
        },
        'error_file': {
            'level': 'ERROR',
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': os.path.join(BASE_DIR, 'error.log'),
            'formatter': 'detailed',
            'maxBytes': 10 * 1024 * 1024,  # 10 MB
            'backupCount': 5,
        },
    },
    'loggers': {
        'django': {
            'handlers': ['console', 'file'],
            'level': 'INFO',
            'propagate': True,
        },
        'chatbot': {
            'handlers': ['console', 'file', 'json_file', 'error_file'],
            'level': 'DEBUG',
            'propagate': False,
        },
        'chatbot.movie_crew': {
            'handlers': ['console', 'file', 'json_file', 'error_file'],
            'level': 'DEBUG',
            'propagate': False,
        },
        'chatbot.views': {
            'handlers': ['console', 'file', 'json_file', 'error_file'],
            'level': 'DEBUG',
            'propagate': False,
        },
    },
    'root': {
        'handlers': ['console', 'file', 'error_file'],
        'level': 'INFO',
    },
}
