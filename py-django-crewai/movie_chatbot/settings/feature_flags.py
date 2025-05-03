"""
Feature flags configuration for the Movie Chatbot application.
This module defines feature flags that can be toggled via environment variables.
"""

import os
import logging

logger = logging.getLogger('chatbot')

# Define feature flags with environment variable controls
FEATURES = {
    # Feature flag for First Run mode (theater search)
    # Defaults to True (enabled) if not specified, for backwards compatibility
    'ENABLE_FIRST_RUN_MODE': os.getenv('ENABLE_FIRST_RUN_MODE', 'true').lower() == 'true',
}

# Log the status of feature flags during startup
logger.info(f"Feature flags configuration: {FEATURES}")
