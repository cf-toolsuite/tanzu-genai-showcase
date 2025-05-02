"""
Movie Finder Agent for the movie crew.
"""
import logging
from typing import Dict, Any, Optional, List
from crewai import Agent
from crewai.tools import BaseTool
from langchain_openai import ChatOpenAI

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class MovieFinderAgent:
    """Agent for finding movies based on user criteria."""

    @staticmethod
    def create(llm: ChatOpenAI, tools: Optional[List[BaseTool]] = None) -> Agent:
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
                      You use The Movie Database API to find the most relevant movies based on user queries.""",
            verbose=True,
            llm=llm,
            tools=tools or []
        )
