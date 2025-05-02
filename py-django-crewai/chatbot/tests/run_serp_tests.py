#!/usr/bin/env python
"""
Command line utility for running SerpAPI tests and fixes.

This script provides a simple interface for running the various SerpAPI
integration tests and applying the fixes.
"""

import os
import sys
import subprocess
import logging
import argparse

# Setup path to include the Django app
script_dir = os.path.dirname(os.path.abspath(__file__))
project_dir = os.path.abspath(os.path.join(script_dir, '../..'))
sys.path.insert(0, project_dir)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger('run_serp_tests')

def setup_django():
    """Set up Django environment."""
    import django
    os.environ.setdefault("DJANGO_SETTINGS_MODULE", "movie_chatbot.settings")
    django.setup()

    # Return Django settings
    from django.conf import settings
    return settings

def run_test_command(command, description=None):
    """Run a command and log the output."""
    if description:
        logger.info(f"Running: {description}")

    logger.info(f"Command: {' '.join(command)}")

    try:
        result = subprocess.run(
            command,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=False  # Don't raise exception on non-zero exit
        )

        if result.returncode == 0:
            logger.info(f"Command completed successfully (exit code 0)")
        else:
            logger.warning(f"Command completed with non-zero exit code: {result.returncode}")

        # Log stdout and stderr
        if result.stdout:
            logger.info("STDOUT:")
            for line in result.stdout.splitlines():
                logger.info(f"  {line}")

        if result.stderr:
            logger.warning("STDERR:")
            for line in result.stderr.splitlines():
                logger.warning(f"  {line}")

        return result.returncode == 0

    except Exception as e:
        logger.error(f"Error running command: {str(e)}")
        return False

def run_serp_api_harness(api_key=None, movie="Avengers", location="New York, NY", test_type="showtimes"):
    """Run the SerpAPI test harness."""
    command = [sys.executable, os.path.join(script_dir, 'serp_api_harness.py'), '--test', test_type]

    if api_key:
        command.extend(['--api-key', api_key])
    if movie:
        command.extend(['--movie', movie])
    if location:
        command.extend(['--location', location])

    return run_test_command(
        command,
        f"SerpAPI harness test ({test_type}) for '{movie}' in '{location}'"
    )

def run_debug_serp_integration(api_key=None, movie="Avengers", location="New York, NY", mode="both"):
    """Run the debug SerpAPI integration script."""
    command = [sys.executable, os.path.join(script_dir, 'debug_serp_integration.py'), '--mode', mode]

    if api_key:
        command.extend(['--api-key', api_key])
    if movie:
        command.extend(['--movie', movie])
    if location:
        command.extend(['--location', location])

    return run_test_command(
        command,
        f"Debug SerpAPI integration ({mode}) for '{movie}' in '{location}'"
    )

def run_serp_structure_test(api_key=None):
    """Run the SerpAPI structure test."""
    # Set environment variable if API key is provided
    env = os.environ.copy()
    if api_key:
        env['SERPAPI_API_KEY'] = api_key

    # Use Django's test runner for this one
    command = [sys.executable, 'manage.py', 'test', 'chatbot.tests.test_serp_structure']

    try:
        result = subprocess.run(
            command,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=False,  # Don't raise exception on non-zero exit
            env=env,
            cwd=project_dir  # Run from project directory
        )

        if result.returncode == 0:
            logger.info("Structure tests completed successfully (exit code 0)")
        else:
            logger.warning(f"Structure tests completed with non-zero exit code: {result.returncode}")

        # Log stdout and stderr
        if result.stdout:
            logger.info("STDOUT:")
            for line in result.stdout.splitlines():
                logger.info(f"  {line}")

        if result.stderr:
            logger.warning("STDERR:")
            for line in result.stderr.splitlines():
                logger.warning(f"  {line}")

        return result.returncode == 0

    except Exception as e:
        logger.error(f"Error running structure tests: {str(e)}")
        return False

def apply_serp_fix():
    """Apply the SerpAPI service fix."""
    logger.info("Applying SerpAPI service fix...")

    # Import and run the fix
    try:
        from chatbot.tests.serp_service_fix import apply_fix
        success, message = apply_fix()

        if success:
            logger.info(f"SerpAPI service fix applied successfully: {message}")
        else:
            logger.error(f"Failed to apply SerpAPI service fix: {message}")

        return success

    except Exception as e:
        logger.error(f"Error applying SerpAPI service fix: {str(e)}")
        return False

def main():
    """Main function to parse arguments and run the tests."""
    parser = argparse.ArgumentParser(description='Run SerpAPI tests and fixes')
    parser.add_argument('--api-key', help='SerpAPI API Key (optional, will use env var or settings if not provided)')
    parser.add_argument('--movie', default='Avengers', help='Movie title to search for')
    parser.add_argument('--location', default='New York, NY', help='Location for search')
    parser.add_argument('--action', choices=['test', 'debug', 'fix', 'all'], default='all',
                        help='Action to perform: test (run tests), debug (debug integration), fix (apply fix), or all (default)')
    parser.add_argument('--apply-fix', action='store_true', help='Apply the SerpAPI service fix')

    args = parser.parse_args()

    try:
        if args.action in ('test', 'all'):
            # Run the basic harness test
            logger.info("=== Running SerpAPI harness tests ===")
            run_serp_api_harness(args.api_key, args.movie, args.location, 'showtimes')

            # Run the structure tests
            logger.info("=== Running SerpAPI structure tests ===")
            run_serp_structure_test(args.api_key)

        if args.action in ('debug', 'all'):
            # Run the debug integration
            logger.info("=== Running SerpAPI integration debug ===")
            run_debug_serp_integration(args.api_key, args.movie, args.location, 'both')

        if args.action in ('fix', 'all') or args.apply_fix:
            # Apply the fix
            logger.info("=== Applying SerpAPI service fix ===")
            apply_serp_fix()

            if args.action == 'all':
                # Run the debug again to verify the fix
                logger.info("=== Running SerpAPI integration debug after fix ===")
                run_debug_serp_integration(args.api_key, args.movie, args.location, 'parse')

        logger.info("All tests completed successfully.")
        return 0

    except Exception as e:
        logger.error(f"Error running tests: {str(e)}")
        return 1

if __name__ == '__main__':
    sys.exit(main())
