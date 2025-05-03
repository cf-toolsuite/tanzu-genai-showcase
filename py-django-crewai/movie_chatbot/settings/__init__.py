# movie_chatbot/settings/__init__.py

import os
import logging
# Remove Path import if not used elsewhere here
from dotenv import load_dotenv

# --- Early Setup ---

# Configure logging very early
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# --- Import BASE_DIR first ---
# It's needed for loading .env and potentially by other modules implicitly
try:
    from .base import BASE_DIR
except ImportError as e:
     logger.error(f"Could not import BASE_DIR from .base - critical error: {e}")
     raise

# --- Environment Variables ---

# Load .env file for local development or explicit overrides
env_file = BASE_DIR / '.env' # Use the imported BASE_DIR
if env_file.is_file():
    logger.info(f"Loading environment variables from {env_file}")
    load_dotenv(dotenv_path=env_file, override=True)
else:
    logger.warning(f"No .env file found at {env_file}, using existing environment variables.")


# --- Import Settings Modules ---
# Import base settings first (already implicitly done by importing BASE_DIR,
# but explicit 'from .base import *' ensures other base settings are loaded).

try:
    from .base import * # noqa Load all other settings from base
    from .apps import * # noqa
    from .templates import * # noqa
    from .database import * # noqa
    from .static import * # noqa
    from .logging_config import * # noqa
    from .external_apis import * # noqa
    from .app_config import * # noqa
    from .llm import * # noqa
    from .feature_flags import * # noqa
except ImportError as e:
    logger.error(f"Error importing settings module: {e}", exc_info=True)
    raise

logger.info("All settings modules loaded successfully.")

# Optional: Environment-specific overrides
# if os.getenv('DJANGO_ENV') == 'production':
#     from .production import * # noqa
