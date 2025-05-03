# movie_chatbot/settings/static.py

import os
from .base import BASE_DIR # Import BASE_DIR

# --- Static files (CSS, JavaScript, Images) ---
# https://docs.djangoproject.com/en/4.2/howto/static-files/
# https://whitenoise.readthedocs.io/

STATIC_URL = 'static/'

# Directory where collectstatic will gather static files for deployment
STATIC_ROOT = os.path.join(BASE_DIR, 'staticfiles')

# Directories where Django will look for static files in addition to app 'static/' dirs
STATICFILES_DIRS = [os.path.join(BASE_DIR, 'static')]

# WhiteNoise storage backend for compression and manifest generation
STATICFILES_STORAGE = 'whitenoise.storage.CompressedManifestStaticFilesStorage'
