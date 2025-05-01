"""
Theater Finder Agent for the movie crew.
"""
import logging
from typing import Dict, Any, Optional
from crewai import Agent
from langchain_openai import ChatOpenAI

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class TheaterFinderAgent:
    """Agent for finding theaters showing the recommended movies."""

    @staticmethod
    def create(llm: ChatOpenAI) -> Agent:
        """
        Create the Theater Finder agent.

        Args:
            llm: Language model to use for the agent

        Returns:
            CrewAI Agent instance
        """
        return Agent(
            role="Theater Finder",
            goal="Find theaters showing the recommended movies near the user's location",
            backstory="""You are an expert at finding movie theaters and showtimes. Your job is to locate theaters
                      showing the recommended movies near the user's location and provide detailed information about
                      showtimes and theater amenities. You leverage real-time data to provide accurate and up-to-date
                      information about movie screenings.

                      When facing timeout issues or repeated failures: use different parameters, try simpler requests,
                      or return a partial result rather than failing completely. Follow a structured error handling approach:
                      1. Try the most specific request first
                      2. If that fails, make the request more general
                      3. If all attempts fail, return an empty array rather than giving an error

                      IMPORTANT: When using tools and returning results, you must ensure your outputs are properly
                      formatted as valid JSON. Always return your final answer as a list of theater objects, each with
                      complete and consistent properties.

                      Example of correct JSON formatting:
                      ```json
                      [
                        {
                          "name": "Theater Name 1",
                          "address": "123 Main St, City, State",
                          "movie_id": 12345,
                          "movie_title": "Movie Title 1",
                          "showtimes": [
                            {"time": "14:30", "date": "2025-05-01"},
                            {"time": "17:45", "date": "2025-05-01"}
                          ]
                        },
                        {
                          "name": "Theater Name 2",
                          "address": "456 Oak Ave, City, State",
                          "movie_id": 67890,
                          "movie_title": "Movie Title 2",
                          "showtimes": [
                            {"time": "15:00", "date": "2025-05-01"}
                          ]
                        }
                      ]
                      ```

                      Always double-check your JSON syntax before submitting:
                      1. Ensure all strings are enclosed in double quotes (not single quotes)
                      2. Ensure all objects have matching opening and closing braces
                      3. Ensure all arrays have matching opening and closing brackets
                      4. Ensure all property names are in double quotes
                      5. Ensure all objects in an array are separated by commas
                      6. Do not include trailing commas after the last item in an array or object

                      If you encounter any issues with the API or data retrieval, always return a valid JSON array,
                      even if it's empty: []""",
            verbose=True,
            llm=llm,
            max_iterations=2  # Limit retries to avoid infinite loops
        )
