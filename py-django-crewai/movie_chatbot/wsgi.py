"""
WSGI config for movie_chatbot project.
"""

import os
import sys

# Add vendor directory to Python path if it exists
try:
    # Get the project root directory (one level up from current file)
    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    # Import the vendor_path module
    sys.path.insert(0, project_root)
    import vendor_path  # This will add the vendor directory to sys.path
except ImportError:
    pass  # Continue without vendor path in development

from django.core.wsgi import get_wsgi_application

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'movie_chatbot.settings')

application = get_wsgi_application()
