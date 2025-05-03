"""
Recommendation Agent for the movie crew.
"""
import logging
from typing import Dict, Any, Optional, List
from crewai import Agent
from crewai.tools import BaseTool
from langchain_openai import ChatOpenAI

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class RecommendationAgent:
    """Agent for analyzing user preferences and recommending movies."""

    @staticmethod
    def create(llm: ChatOpenAI, tools: Optional[List[BaseTool]] = None) -> Agent:
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
                      users understand why they might enjoy each movie.""",
            verbose=True,
            llm=llm,
            tools=tools or []
        )
