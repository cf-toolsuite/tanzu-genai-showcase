# movie_chatbot/settings/logging_config.py

import os
from .base import BASE_DIR, DEBUG # Import BASE_DIR and DEBUG

# --- Enhanced Logging Configuration ---

# Assumes ColorizeFilter is defined appropriately elsewhere, e.g., movie_chatbot.log_config
# If not, you might need to define a simple placeholder or remove the filter/formatter using it.
# Example placeholder if the class doesn't exist:
# class ColorizeFilter:
#     def filter(self, record): return True

# Make sure the path to ColorizeFilter is correct or adjust as needed
LOGGING_FILTER_PATH = 'movie_chatbot.log_config.ColorizeFilter'
try:
    # Attempt to import to check if it exists, but don't actually use the import here
    from importlib import import_module
    module_path, class_name = LOGGING_FILTER_PATH.rsplit('.', 1)
    module = import_module(module_path)
    getattr(module, class_name)
    filter_exists = True
except (ImportError, AttributeError):
    filter_exists = False
    print(f"Warning: Log filter '{LOGGING_FILTER_PATH}' not found. Dev console logs may not be colored.")


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
            # Use a simpler format if the color filter is missing
            'format': ('\x1b[38;5;111m\u2502 {asctime} \u2502\x1b[0m \x1b[38;5;{color}m{levelname:<8}\x1b[0m \x1b[38;5;247m{module}.{funcName}:{lineno}\x1b[0m {message}'
                       if filter_exists else '[{asctime}] {levelname:<8} {module}.{funcName}:{lineno} {message}'),
            'style': '{',
        },
    },
    'filters': {
        # Only include the filter if it exists
        'colorize': {
            '()': LOGGING_FILTER_PATH,
        } if filter_exists else {},
    },
    'handlers': {
        'console': {
            'level': 'DEBUG' if DEBUG else 'INFO',
            'class': 'logging.StreamHandler',
            'formatter': 'dev_friendly',
            # Only apply the filter if it exists
            'filters': ['colorize'] if filter_exists else [],
        },
        'file': {
            'level': 'DEBUG',
            'class': 'logging.handlers.RotatingFileHandler',
            # Use BASE_DIR for log file path
            'filename': os.path.join(BASE_DIR, 'logs', 'chatbot.log'),
            'formatter': 'detailed',
            'maxBytes': 10 * 1024 * 1024,  # 10 MB
            'backupCount': 5,
        },
        'json_file': {
            'level': 'DEBUG',
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': os.path.join(BASE_DIR, 'logs', 'chatbot.json.log'),
            'formatter': 'json',
            'maxBytes': 10 * 1024 * 1024,  # 10 MB
            'backupCount': 5,
        },
        'error_file': {
            'level': 'ERROR',
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': os.path.join(BASE_DIR, 'logs', 'error.log'),
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
        'chatbot': { # Your main app logger
            'handlers': ['console', 'file', 'json_file', 'error_file'],
            'level': 'DEBUG',
            'propagate': False, # Prevent double logging to root
        },
        # Add specific loggers if needed, inheriting handlers or defining specific ones
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
         'cfenv': { # Control logging from cfenv library if needed
            'handlers': ['console', 'file'],
            'level': 'INFO', # Or DEBUG for more detail
            'propagate': False,
        },
    },
    'root': {
        # Catch-all logger
        'handlers': ['console', 'file', 'error_file'],
        'level': 'INFO', # Set root level higher to avoid noise from libraries
    },
}

# Ensure the log directory exists
LOG_DIR = os.path.join(BASE_DIR, 'logs')
os.makedirs(LOG_DIR, exist_ok=True)
