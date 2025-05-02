"""
Theater Finder Agent for the movie crew.
"""
import logging
from typing import Dict, Any, Optional, List
from crewai import Agent
from crewai.tools import BaseTool
from langchain_openai import ChatOpenAI

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class TheaterFinderAgent:
    """Agent for finding theaters showing the recommended movies."""

    @staticmethod
    def create(llm: ChatOpenAI, tools: Optional[List[BaseTool]] = None) -> Agent:
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
                      3. If all attempts fail, return an empty array rather than giving an error""",
            verbose=True,
            llm=llm,
            tools=tools or [],
            max_iterations=2  # Limit retries to avoid infinite loops
        )
