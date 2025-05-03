"""
Custom event listener for CrewAI events with optimized logging.
Performance improvements:
1. Reduced logging verbosity for production
2. Better filtering of events
3. Optimized log format for easier debugging
4. Memory efficient event processing
"""

import logging
from typing import Optional, Dict, Any
import time

# Configure logger
logger = logging.getLogger('chatbot.movie_crew')

# Handle different versions of CrewAI library
try:
    # Try to import from the expected path (newer versions)
    from crewai.utilities.events.event_listener import EventListener
    from crewai.utilities.events.event_names import EventNames
    logger.info("Using newer CrewAI events API")
except ImportError:
    try:
        # Try to import from alternate path (older versions)
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
    except ImportError:
        # Fallback for versions without event listener support
        logger.warning("CrewAI event listener not found. Using fallback implementation.")
        # Create minimal implementations for compatibility
        class EventListener:
            def on_event(self, event_name: str, data: Optional[Dict[str, Any]] = None) -> None:
                logger.debug(f"Event: {event_name}")

        class EventNames:
            AGENT_STARTED = "agent_started"
            AGENT_FINISHED = "agent_finished"
            TASK_STARTED = "task_started"
            TASK_FINISHED = "task_finished"
            TOOL_STARTED = "tool_started"
            TOOL_FINISHED = "tool_finished"
            CREW_STARTED = "crew_started"
            CREW_FINISHED = "crew_finished"

class CustomEventListener(EventListener):
    """
    Custom event listener for CrewAI events with optimized logging.
    Handles different CrewAI versions and provides performance metrics.
    This class reduces the verbosity of CrewAI logs while still providing
    useful information for debugging.
    """

    def __init__(self):
        """Initialize the custom event listener with optimized settings."""
        super().__init__()
        self.start_times = {}
        self.tool_usage_counts = {}
        self.agent_task_mapping = {}
        self.task_durations = {}

    def on_event(self, event_name: str, data: Optional[Dict[str, Any]] = None) -> None:
        """
        Handle CrewAI events with optimized logging.

        Args:
            event_name: Name of the event
            data: Event data
        """
        # Skip if no data
        if not data:
            return

        # Process based on event type
        if event_name == EventNames.AGENT_STARTED:
            self._handle_agent_started(data)
        elif event_name == EventNames.AGENT_FINISHED:
            self._handle_agent_finished(data)
        elif event_name == EventNames.TASK_STARTED:
            self._handle_task_started(data)
        elif event_name == EventNames.TASK_FINISHED:
            self._handle_task_finished(data)
        elif event_name == EventNames.TOOL_STARTED:
            self._handle_tool_started(data)
        elif event_name == EventNames.TOOL_FINISHED:
            self._handle_tool_finished(data)
        elif event_name == EventNames.CREW_STARTED:
            self._handle_crew_started(data)
        elif event_name == EventNames.CREW_FINISHED:
            self._handle_crew_finished(data)

    def _handle_agent_started(self, data: Dict[str, Any]) -> None:
        """Handle agent started event."""
        agent_name = data.get('agent_name', 'Unknown Agent')
        logger.info(f"Agent started: {agent_name}")

    def _handle_agent_finished(self, data: Dict[str, Any]) -> None:
        """Handle agent finished event."""
        agent_name = data.get('agent_name', 'Unknown Agent')
        logger.info(f"Agent finished: {agent_name}")

    def _handle_task_started(self, data: Dict[str, Any]) -> None:
        """Handle task started event."""
        task_id = data.get('task_id', 'unknown_task')
        task_description = data.get('task_description', 'Unknown Task')
        agent_name = data.get('agent_name', 'Unknown Agent')

        # Store task start time for duration calculation
        import time
        self.start_times[task_id] = time.time()

        # Store agent-task mapping
        self.agent_task_mapping[task_id] = agent_name

        # Log task start with minimal info
        logger.info(f"Task started: '{task_description}' (Agent: {agent_name})")

    def _handle_task_finished(self, data: Dict[str, Any]) -> None:
        """Handle task finished event."""
        task_id = data.get('task_id', 'unknown_task')
        task_description = data.get('task_description', 'Unknown Task')

        # Calculate task duration
        import time
        if task_id in self.start_times:
            duration = time.time() - self.start_times[task_id]
            self.task_durations[task_id] = duration

            # Log task completion with duration
            logger.info(f"Task finished: '{task_description}' in {duration:.2f}s")

            # Clean up
            del self.start_times[task_id]
        else:
            logger.info(f"Task finished: '{task_description}'")

    def _handle_tool_started(self, data: Dict[str, Any]) -> None:
        """Handle tool started event."""
        tool_name = data.get('tool_name', 'Unknown Tool')

        # Count tool usage
        if tool_name not in self.tool_usage_counts:
            self.tool_usage_counts[tool_name] = 0
        self.tool_usage_counts[tool_name] += 1

        # Only log at debug level to reduce verbosity
        logger.debug(f"Tool started: {tool_name}")

    def _handle_tool_finished(self, data: Dict[str, Any]) -> None:
        """Handle tool finished event."""
        tool_name = data.get('tool_name', 'Unknown Tool')

        # Only log at debug level to reduce verbosity
        logger.debug(f"Tool finished: {tool_name}")

    def _handle_crew_started(self, data: Dict[str, Any]) -> None:
        """Handle crew started event."""
        logger.info("CrewAI workflow started")

    def _handle_crew_finished(self, data: Dict[str, Any]) -> None:
        """Handle crew finished event with performance summary."""
        # Log performance summary
        logger.info("CrewAI workflow completed")

        # Log task durations
        if self.task_durations:
            logger.info("Task performance summary:")
            for task_id, duration in self.task_durations.items():
                agent_name = self.agent_task_mapping.get(task_id, 'Unknown Agent')
                logger.info(f"  - Task {task_id}: {duration:.2f}s (Agent: {agent_name})")

        # Log tool usage
        if self.tool_usage_counts:
            logger.info("Tool usage summary:")
            for tool_name, count in self.tool_usage_counts.items():
                logger.info(f"  - {tool_name}: {count} calls")

        # Reset counters for next run
        self.start_times = {}
        self.tool_usage_counts = {}
        self.agent_task_mapping = {}
        self.task_durations = {}
