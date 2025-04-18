"""
Logging configuration for the movie_chatbot project.
"""

import logging
import re

class ColorizeFilter(logging.Filter):
    """
    A filter that adds color codes to the log record based on the level.
    """

    COLORS = {
        'DEBUG': '39',     # Light blue
        'INFO': '34',      # Blue
        'WARNING': '33',   # Yellow
        'ERROR': '31',     # Red
        'CRITICAL': '41',  # Red background
    }

    def filter(self, record):
        # Add the color attribute to the record
        record.color = self.COLORS.get(record.levelname, '37')  # Default to white
        return True
