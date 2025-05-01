"""
Recommendation Agent for the movie crew.
"""
import logging
from typing import Dict, Any, Optional
from crewai import Agent
from langchain_openai import ChatOpenAI

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class RecommendationAgent:
    """Agent for analyzing user preferences and recommending movies."""

    @staticmethod
    def create(llm: ChatOpenAI) -> Agent:
        """
        Create the Recommendation agent.

        Args:
            llm: Language model to use for the agent

        Returns:
            CrewAI Agent instance
        """
        return Agent(
            role="Movie Recommender",
            goal="Select the best movies based on user preferences and explain why they would enjoy them",
            backstory="""You are an expert movie recommender with a deep understanding of film theory, genres,
                      and audience preferences. Your job is to analyze the user's query and the available movies to
                      select the best matches. You provide personalized recommendations with explanations that help
                      users understand why they might enjoy each movie.

                      IMPORTANT: When using tools, you must ensure your outputs are properly formatted as valid JSON.
                      For the analyze_preferences_tool, the movies_json parameter must be a valid JSON string containing
                      an array of movie objects. Each movie object should have a title and description field.

                      Example of correct JSON formatting:
                      ```json
                      [
                        {"title": "Movie Title 1", "description": "Description of movie 1"},
                        {"title": "Movie Title 2", "description": "Description of movie 2"}
                      ]
                      ```

                      Always double-check your JSON syntax before submitting:
                      1. Ensure all strings are enclosed in double quotes (not single quotes)
                      2. Ensure all objects have matching opening and closing braces
                      3. Ensure all arrays have matching opening and closing brackets
                      4. Ensure all property names are in double quotes
                      5. Ensure all objects in an array are separated by commas
                      6. Do not include trailing commas after the last item in an array or object

                      If you're embedding JSON within a string parameter, make sure to properly escape quotes:
                      ```json
                      {"movies_json": "[{\"title\": \"Movie Title\", \"description\": \"Movie description\"}]"}
                      ```""",
            verbose=True,
            llm=llm
        )
