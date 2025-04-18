"""
JSON parser utilities for the movie crew.
"""
import json
import re
import logging
from typing import Any, List, Dict
import traceback

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class JsonParser:
    """Parser for JSON from agent output."""

    @staticmethod
    def _preprocess_json(output: str) -> str:
        """
        Preprocess JSON string to repair common syntax errors.

        Args:
            output: The JSON string to preprocess

        Returns:
            Preprocessed JSON string
        """
        # Only process string inputs
        if not isinstance(output, str):
            return output

        # Replace single quotes with double quotes where appropriate
        output = output.replace("'", '"')

        # Fix common issues with trailing commas in arrays and objects
        output = re.sub(r',\s*([}\]])', r'\1', output)

        # Fix missing commas between array elements
        output = re.sub(r'}\s*{', '},{', output)
        output = re.sub(r']\s*\[', r'],\[', output)  # Fixed escape sequence
        output = re.sub(r'}\s*\[', r'},\[', output)  # Fixed escape sequence
        output = re.sub(r']\s*{', r'],{', output)

        # Fix missing commas in JSON arrays and objects - more aggressive pattern
        # This handles cases like: {"a":1"b":2} -> {"a":1,"b":2}
        output = re.sub(r'([\d"}])\s*"', r'\1,"', output)

        # Fix unescaped quotes in strings
        output = re.sub(r'(?<!\\)"(?=.*":)', r'\"', output)

        # Fix mismatched brackets - ensure arrays are properly terminated
        open_brackets = output.count('[')
        close_brackets = output.count(']')
        if open_brackets > close_brackets:
            output += ']' * (open_brackets - close_brackets)

        # Fix unterminated objects
        open_braces = output.count('{')
        close_braces = output.count('}')
        if open_braces > close_braces:
            output += '}' * (open_braces - close_braces)

        # Try to fix objects missing closing brace at the end of array
        # This handles cases where the last object in an array is incomplete
        if output.endswith('"]') or output.endswith('"}]'):
            # If it looks like the last quotation mark isn't closed properly
            output = re.sub(r'"([^"]*)]$', r'"\1"}]', output)

        return output

    @staticmethod
    def _attempt_partial_parsing(output: str) -> List[Dict]:
        """
        Attempt to parse individual objects from a corrupted JSON array.

        Args:
            output: The potentially corrupted JSON array string

        Returns:
            List of successfully parsed objects
        """
        # Only try this if it looks like an array
        if not (output.startswith('[') and output.endswith(']')):
            # If it doesn't end with ']', but starts with '[', try to fix it
            if output.startswith('[') and ']' in output:
                # Take everything up to the last closing bracket
                output = output[:output.rindex(']')+1]
            else:
                return []

        # Extract the content inside the array brackets
        content = output[1:-1].strip()

        # Try different strategies to extract valid objects
        result = []

        # Strategy 1: Use regex to find all JSON objects
        object_pattern = r'{[^{}]*(?:{[^{}]*}[^{}]*)*}'
        objects = re.findall(object_pattern, content)

        # Try to parse each object with repair attempts
        for obj_str in objects:
            try:
                # First try to parse as is
                obj = json.loads(obj_str)
                result.append(obj)
            except json.JSONDecodeError as e:
                try:
                    # Try to repair the object
                    fixed_obj_str = JsonParser._repair_json_object(obj_str)
                    obj = json.loads(fixed_obj_str)
                    result.append(obj)
                except Exception:
                    # Log but continue with other objects
                    logger.debug(f"Could not repair object: {obj_str[:100]}...")
                    continue

        # Strategy 2: Split by commas and try to repair individual objects
        if not result and '},{' in content:
            # This handles arrays where objects are properly separated by commas
            parts = content.split('},{')
            for i, part in enumerate(parts):
                # Add the missing braces back
                if i == 0:
                    if not part.startswith('{'):
                        part = '{' + part
                    part = part + '}'
                elif i == len(parts) - 1:
                    if not part.endswith('}'):
                        part = part + '}'
                    part = '{' + part
                else:
                    part = '{' + part + '}'

                try:
                    # Try to parse with repairs
                    fixed_part = JsonParser._repair_json_object(part)
                    obj = json.loads(fixed_part)
                    result.append(obj)
                except json.JSONDecodeError:
                    continue

        # Log results
        if result:
            logger.info(f"Partial parsing recovered {len(result)} objects")
        else:
            logger.warning("Could not recover any objects from partial parsing")

        return result

    @staticmethod
    def _repair_json_object(obj_str: str) -> str:
        """
        Attempt to repair a malformed JSON object string.

        Args:
            obj_str: The potentially corrupted JSON object string

        Returns:
            Repaired JSON object string
        """
        # Make sure it starts and ends with curly braces
        if not obj_str.startswith('{'):
            obj_str = '{' + obj_str
        if not obj_str.endswith('}'):
            obj_str = obj_str + '}'

        # Fix missing commas between key-value pairs
        obj_str = re.sub(r'"\s*"', '","', obj_str)
        obj_str = re.sub(r'([\d"}])\s*"', r'\1,"', obj_str)

        # Fix missing quotation marks around keys
        obj_str = re.sub(r'{([^{"\':,]+):', r'{"$1":', obj_str)
        obj_str = re.sub(r',([^{"\':,]+):', r',"$1":', obj_str)

        # Balance quotes if needed
        quotes_count = obj_str.count('"')
        if quotes_count % 2 != 0:
            # Find position of unbalanced quote
            in_string = False
            for i, char in enumerate(obj_str):
                if char == '"' and (i == 0 or obj_str[i-1] != '\\'):
                    in_string = not in_string

            # If we end inside a string, add a closing quote
            if in_string:
                obj_str += '"'

        # Ensure braces are balanced
        open_braces = obj_str.count('{')
        close_braces = obj_str.count('}')

        if open_braces > close_braces:
            obj_str += '}' * (open_braces - close_braces)
        elif close_braces > open_braces:
            obj_str = '{' * (close_braces - open_braces) + obj_str

        return obj_str

    @staticmethod
    def parse_json_output(output: str) -> Any:
        """
        Parse JSON from agent output, handling various formats and error cases.

        Args:
            output: The output string to parse

        Returns:
            Parsed JSON data, or empty list if parsing fails
        """
        if not output:
            return []

        # Try to find JSON in the output
        try:
            if isinstance(output, list) or isinstance(output, dict):
                return output

            # Log original length for debugging
            original_length = len(output) if isinstance(output, str) else 0
            logger.debug(f"Original JSON string length: {original_length}")

            # Preprocess the JSON string to fix common issues
            preprocessed_output = JsonParser._preprocess_json(output)

            # First attempt: direct JSON parsing with preprocessed output
            try:
                return json.loads(preprocessed_output)
            except json.JSONDecodeError as je:
                # If the error is near the end, try to truncate and repair
                if je.pos > len(preprocessed_output) * 0.9:  # Error is in the last 10%
                    logger.info(f"JSON error detected near end at position {je.pos}, attempting repair")

                    # Try to fix the last object in an array
                    if preprocessed_output.startswith('[') and '}]' in preprocessed_output:
                        # Find the last complete object
                        last_complete = preprocessed_output.rindex('}]')
                        if last_complete > 0:
                            truncated = preprocessed_output[:last_complete + 2]  # Include the closing '}]'
                            logger.info(f"Truncated JSON from {len(preprocessed_output)} to {len(truncated)} chars")
                            return json.loads(truncated)

                # If the specific error handling didn't work, continue with regular approach
                raise
        except json.JSONDecodeError:
            try:
                # Clean up potential newlines and extra whitespace
                output = output.strip()

                # Handle the case where output is just a string representation of a list
                if output.startswith('[') and output.endswith(']'):
                    try:
                        # Handle cases with single quotes instead of double quotes
                        cleaned_output = output.replace("'", '"')
                        return json.loads(cleaned_output)
                    except Exception:
                        pass

                # Second attempt: Look for JSON-like patterns in the text
                json_match = re.search(r'\[\s*{.*}\s*\]', output, re.DOTALL)
                if json_match:
                    return json.loads(json_match.group(0))

                # Third attempt: Look for JSON surrounded by triple backticks
                json_match = re.search(r'```(?:json)?\s*(\[\s*{.*}\s*\])\s*```', output, re.DOTALL)
                if json_match:
                    return json.loads(json_match.group(1))

                # Fourth attempt: Look for JSON surrounded by backticks
                json_match = re.search(r'`(\[\s*{.*}\s*\])`', output, re.DOTALL)
                if json_match:
                    return json.loads(json_match.group(1))

                # If all else fails, try to find a JSON object rather than an array
                json_match = re.search(r'{.*}', output, re.DOTALL)
                if json_match:
                    obj = json.loads(json_match.group(0))
                    if isinstance(obj, dict):
                        return [obj]

                # Last resort: try partial parsing to salvage whatever objects we can
                if output.startswith('[') and output.endswith(']'):
                    logger.info("Attempting partial JSON parsing for malformed array")
                    partial_results = JsonParser._attempt_partial_parsing(output)
                    if partial_results:
                        logger.info(f"Recovered {len(partial_results)} objects through partial parsing")
                        return partial_results

                # If this is a very large output, log the error location
                if len(output) > 10000:
                    try:
                        # Attempt to parse to get exact error location
                        json.loads(output)
                    except json.JSONDecodeError as je:
                        error_context = output[max(0, je.pos-20):min(len(output), je.pos+20)]
                        logger.error(f"JSON parse error at position {je.pos}: '{error_context}'")

                logger.warning(f"Could not extract JSON from output: {output[:100]}...")
                return []
            except Exception as e:
                logger.error(f"Error extracting JSON: {str(e)}")
                # Log the traceback for more detailed debugging
                logger.error(f"Traceback: {traceback.format_exc()}")
                return []
