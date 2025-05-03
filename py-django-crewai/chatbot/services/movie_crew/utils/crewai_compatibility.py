"""
Compatibility utilities for different versions of CrewAI.
This module provides compatibility functions and classes for working with
different versions of the CrewAI library.
"""

import logging
import importlib
from typing import Any, Dict, Optional, Tuple, Type

# Configure logger
logger = logging.getLogger('chatbot.crewai_compatibility')

# Version-dependent imports
class CrewAIImports:
    """Helper class to manage CrewAI imports across different versions."""

    @staticmethod
    def get_event_listener() -> Tuple[Any, Any, bool]:
        """
        Get EventListener and EventNames classes if available.

        Returns:
            Tuple containing:
            - EventListener class
            - EventNames class or equivalent
            - Boolean indicating if using the new API
        """
        # Try newer API first
        try:
            from crewai.utilities.events.event_listener import EventListener
            from crewai.utilities.events.event_names import EventNames
            logger.info("Using newer CrewAI events API")
            return EventListener, EventNames, True
        except ImportError:
            # Try older API
            try:
                from crewai.utilities import EventListener

                # Create an EventNames equivalent for older versions
                class EventNames:
                    AGENT_STARTED = "agent_started"
                    AGENT_FINISHED = "agent_finished"
                    TASK_STARTED = "task_started"
                    TASK_FINISHED = "task_finished"
                    TOOL_STARTED = "tool_started"
                    TOOL_FINISHED = "tool_finished"
                    CREW_STARTED = "crew_started"
                    CREW_FINISHED = "crew_finished"

                logger.info("Using older CrewAI events API")
                return EventListener, EventNames, False
            except ImportError:
                # Create fallback implementation
                logger.warning("CrewAI event listener not found. Using fallback implementation.")

                class MinimalEventListener:
                    """Minimal event listener implementation for compatibility."""
                    def on_event(self, event_name: str, data: Optional[Dict[str, Any]] = None) -> None:
                        logger.debug(f"Event: {event_name}")

                class MinimalEventNames:
                    """Minimal event names implementation for compatibility."""
                    AGENT_STARTED = "agent_started"
                    AGENT_FINISHED = "agent_finished"
                    TASK_STARTED = "task_started"
                    TASK_FINISHED = "task_finished"
                    TOOL_STARTED = "tool_started"
                    TOOL_FINISHED = "tool_finished"
                    CREW_STARTED = "crew_started"
                    CREW_FINISHED = "crew_finished"

                return MinimalEventListener, MinimalEventNames, False

    @staticmethod
    def get_crew_class() -> Any:
        """
        Get Crew class with version compatibility.

        Returns:
            Crew class from the appropriate location
        """
        try:
            from crewai import Crew
            return Crew
        except ImportError:
            logger.error("Could not import Crew class from CrewAI")
            return None

    @staticmethod
    def get_agent_class() -> Any:
        """
        Get Agent class with version compatibility.

        Returns:
            Agent class from the appropriate location
        """
        try:
            from crewai import Agent
            return Agent
        except ImportError:
            logger.error("Could not import Agent class from CrewAI")
            return None

    @staticmethod
    def get_task_class() -> Any:
        """
        Get Task class with version compatibility.

        Returns:
            Task class from the appropriate location
        """
        try:
            from crewai import Task
            return Task
        except ImportError:
            logger.error("Could not import Task class from CrewAI")
            return None

# Get CrewAI version
def get_crewai_version() -> str:
    """
    Get CrewAI version string.

    Returns:
        Version string or "unknown"
    """
    try:
        import crewai
        return getattr(crewai, "__version__", "unknown")
    except ImportError:
        return "not installed"

# Check if CrewAI is installed
def is_crewai_installed() -> bool:
    """
    Check if CrewAI is installed.

    Returns:
        True if CrewAI is installed, False otherwise
    """
    try:
        import crewai
        return True
    except ImportError:
        return False

# Version-dependent configuration
def get_recommended_config() -> Dict[str, Any]:
    """
    Get recommended configuration based on CrewAI version.

    Returns:
        Dictionary with recommended configuration settings
    """
    version = get_crewai_version()

    # Base config (works for all versions)
    config = {
        "memory": True,
        "verbose": False,
        "max_rpm": 10
    }

    # Version-specific adjustments
    try:
        if version != "unknown" and version != "not installed":
            parts = version.split(".")
            if len(parts) >= 3:
                major, minor, patch = map(int, parts)

                # Add version-specific configuration
                if major >= 0 and minor >= 30:
                    # For newer versions
                    config["cache"] = True
                    config["parallel"] = True
                elif major >= 0 and minor >= 20:
                    # For slightly older versions
                    config["cache"] = True
                # If very old version, stick with base config
    except Exception as e:
        # If we can't parse the version, use base config
        logger.warning(f"Could not parse CrewAI version for config: {e}")

    return config
