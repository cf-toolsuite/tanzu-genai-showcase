#!/usr/bin/env python
"""Django's command-line utility for administrative tasks."""
import os
import sys

# Add vendor directory to Python path if it exists
try:
    import vendor_path  # This will add the vendor directory to sys.path
except ImportError:
    pass  # Continue without vendor path in development


def main():
    """Run administrative tasks."""
    os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'movie_chatbot.settings')
    try:
        from django.core.management import execute_from_command_line
    except ImportError as exc:
        raise ImportError(
            "Couldn't import Django. Are you sure it's installed?"
        ) from exc
    execute_from_command_line(sys.argv)


if __name__ == '__main__':
    main()
