# movie_chatbot/settings/database.py

import os
import dj_database_url
from .base import BASE_DIR # Import BASE_DIR

# --- Database ---
# https://docs.djangoproject.com/en/4.2/ref/settings/#databases

DATABASES = {
    'default': dj_database_url.config(
        # Correct default path using BASE_DIR
        default='sqlite:///' + os.path.join(BASE_DIR, 'db.sqlite3'),
        conn_max_age=600
    )
}
