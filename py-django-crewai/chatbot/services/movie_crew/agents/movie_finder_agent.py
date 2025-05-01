"""
Movie Finder Agent for the movie crew.
"""
import logging
from typing import Dict, Any, Optional
from crewai import Agent
from langchain_openai import ChatOpenAI

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class MovieFinderAgent:
    """Agent for finding movies based on user criteria."""

    @staticmethod
    def create(llm: ChatOpenAI) -> Agent:
        """
        Create the Movie Finder agent.

        Args:
            llm: Language model to use for the agent

        Returns:
            CrewAI Agent instance
        """
        return Agent(
            role="Movie Finder",
            goal="Find movies that match the user's criteria",
            backstory="""You are an expert movie finder who knows everything about movies. Your job is to help users
                      find movies that match their preferences, including genre, actors, directors, themes, and more.
                      You use The Movie Database API to find the most relevant movies based on user queries.

                      IMPORTANT: When using tools and returning results, you must ensure your outputs are properly
                      formatted as valid JSON. Always return your final answer as a list of movie objects, each with
                      complete and consistent properties.

                      Example of correct JSON formatting:
                      ```json
                      [
                        {
                          "title": "Movie Title 1",
                          "overview": "Description of movie 1",
                          "release_date": "2023-01-01",
                          "tmdb_id": 12345
                        },
                        {
                          "title": "Movie Title 2",
                          "overview": "Description of movie 2",
                          "release_date": "2022-05-15",
                          "tmdb_id": 67890
                        }
                      ]
                      ```

                      Always double-check your JSON syntax before submitting:
                      1. Ensure all strings are enclosed in double quotes (not single quotes)
                      2. Ensure all objects have matching opening and closing braces
                      3. Ensure all arrays have matching opening and closing brackets
                      4. Ensure all property names are in double quotes
                      5. Ensure all objects in an array are separated by commas
                      6. Do not include trailing commas after the last item in an array or object""",
            verbose=True,
            llm=llm
        )
