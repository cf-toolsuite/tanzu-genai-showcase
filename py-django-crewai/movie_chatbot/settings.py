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

# LLM Configuration
# Get LLM credentials from Cloud Foundry service binding or environment variables
def get_llm_config():
    # Check if running in Cloud Foundry with bound services
    if cf_env.get_service(label='genai') or cf_env.get_service(name='my-llm-service'):
        service = cf_env.get_service(label='genai') or cf_env.get_service(name='my-llm-service')
        credentials = service.credentials

        return {
            'api_key': credentials.get('api_key') or credentials.get('apiKey'),
            'base_url': credentials.get('url') or credentials.get('baseUrl'),
            'model': credentials.get('model') or 'gpt-4o-mini'
        }

    # Fallback to environment variables for local development
    return {
        'api_key': os.getenv('OPENAI_API_KEY'),
        'base_url': os.getenv('LLM_BASE_URL'),
        'model': os.getenv('LLM_MODEL', 'gpt-4o-mini')
    }

LLM_CONFIG = get_llm_config()

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
# Default starting year for historical movie searches ("before X" queries)
DEFAULT_SEARCH_START_YEAR = int(os.getenv('DEFAULT_SEARCH_START_YEAR', '1900'))

# API Request Configuration
# Maximum seconds to wait for API responses
API_REQUEST_TIMEOUT = int(os.getenv('API_REQUEST_TIMEOUT_SECONDS', '30'))
# Maximum number of retry attempts for failed API requests
API_MAX_RETRIES = int(os.getenv('API_MAX_RETRIES', '3'))
# Exponential backoff factor between retries (in seconds)
API_RETRY_BACKOFF_FACTOR = float(os.getenv('API_RETRY_BACKOFF_FACTOR', '0.5'))

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
