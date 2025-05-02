"""
Custom event listener for CrewAI events.
"""
import logging
from typing import Optional, Dict, Any

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

from crewai.utilities.events.base_event_listener import BaseEventListener

class CustomEventListener(BaseEventListener):
    """
    Custom event listener for CrewAI events that works with CrewAI 0.114.0+.
    This class handles events from the CrewAI execution and provides
    proper error handling for tool usage events.
    """

    def __init__(self):
        """Initialize the event listener."""
        super().__init__()
        self.tool_usage_counts = {}
        self.errors = []

        # Pre-register known tool names to avoid KeyError
        self.tool_names = [
            "search_movies_tool",
            "analyze_preferences_tool",
            "find_theaters_tool"
        ]

        for tool_name in self.tool_names:
            self.tool_usage_counts[tool_name] = 0

    def setup_listeners(self, crewai_event_bus):
        """
        Set up the event listeners for the CrewAI event bus.

        Args:
            crewai_event_bus: The CrewAI event bus
        """
        from crewai.utilities.events import (
            ToolUsageStartedEvent,
            ToolUsageFinishedEvent,
            ToolUsageErrorEvent,
            CrewKickoffStartedEvent,
            CrewKickoffCompletedEvent,
            TaskStartedEvent,
            TaskCompletedEvent
        )

        @crewai_event_bus.on(ToolUsageStartedEvent)
        def on_tool_usage_started(source, event: ToolUsageStartedEvent):
            """Handle tool usage started event."""
            tool_name = getattr(event, 'tool_name', 'unknown_tool')

            logger.info(f"Tool usage started: {tool_name}")

            # Ensure tool is registered in counts
            if tool_name not in self.tool_usage_counts:
                self.tool_usage_counts[tool_name] = 0
                logger.info(f"Added new tool {tool_name} to tracking")

        @crewai_event_bus.on(ToolUsageFinishedEvent)
        def on_tool_usage_finished(source, event: ToolUsageFinishedEvent):
            """Handle tool usage finished event."""
            tool_name = getattr(event, 'tool_name', 'unknown_tool')

            # Increment tool usage counter safely
            if tool_name not in self.tool_usage_counts:
                self.tool_usage_counts[tool_name] = 0

            self.tool_usage_counts[tool_name] += 1
            logger.info(f"Tool usage completed: {tool_name}, count: {self.tool_usage_counts[tool_name]}")

        @crewai_event_bus.on(ToolUsageErrorEvent)
        def on_tool_usage_error(source, event: ToolUsageErrorEvent):
            """Handle tool usage error event."""
            tool_name = getattr(event, 'tool_name', 'unknown_tool')
            error = getattr(event, 'error', 'Unknown error')

            logger.error(f"Tool usage error: {tool_name}, error: {error}")
            self.errors.append({
                'tool_name': tool_name,
                'error': str(error)
            })

        @crewai_event_bus.on(CrewKickoffStartedEvent)
        def on_crew_kickoff_started(source, event: CrewKickoffStartedEvent):
            """Handle crew kickoff started event."""
            crew_id = getattr(event, 'id', 'unknown_crew')
            logger.info(f"Crew kickoff started: {crew_id}")

        @crewai_event_bus.on(CrewKickoffCompletedEvent)
        def on_crew_kickoff_completed(source, event: CrewKickoffCompletedEvent):
            """Handle crew kickoff completed event."""
            crew_id = getattr(event, 'id', 'unknown_crew')
            logger.info(f"Crew kickoff completed: {crew_id}")

            # Log tool usage statistics
            logger.info(f"Tool usage counts: {self.tool_usage_counts}")

            # Log any errors that occurred
            if self.errors:
                logger.warning(f"Errors during crew execution: {len(self.errors)}")
                for i, error in enumerate(self.errors):
                    logger.warning(f"Error {i+1}: Tool {error['tool_name']}: {error['error']}")
            else:
                logger.info("No errors during crew execution")

        @crewai_event_bus.on(TaskStartedEvent)
        def on_task_started(source, event: TaskStartedEvent):
            """Handle task started event."""
            task_id = getattr(event, 'id', 'unknown_task')
            task_name = getattr(source, 'name', 'unknown_task_name') if source else 'unknown_task_name'
            logger.info(f"Task started: {task_name} ({task_id})")

        @crewai_event_bus.on(TaskCompletedEvent)
        def on_task_completed(source, event: TaskCompletedEvent):
            """Handle task completed event."""
            task_id = getattr(event, 'id', 'unknown_task')
            task_name = getattr(source, 'name', 'unknown_task_name') if source else 'unknown_task_name'
            logger.info(f"Task completed: {task_name} ({task_id})")
