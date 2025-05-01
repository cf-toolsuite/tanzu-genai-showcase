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

        # Log original string for debugging
        logger.debug(f"Preprocessing JSON string: {output[:100]}...")

        # Replace single quotes with double quotes where appropriate
        output = output.replace("'", '"')

        # Fix common issues with trailing commas in arrays and objects
        output = re.sub(r',\s*([}\]])', r'\1', output)

        # Fix missing commas between array elements
        output = re.sub(r'}\s*{', '},{', output)
        output = re.sub(r']\s*\[', r'],\[', output)
        output = re.sub(r'}\s*\[', r'},\[', output)
        output = re.sub(r']\s*{', r'],{', output)

        # Fix missing commas in JSON arrays and objects - more aggressive pattern
        # This handles cases like: {"a":1"b":2} -> {"a":1,"b":2}
        output = re.sub(r'([\d"}])\s*"', r'\1,"', output)

        # Fix unescaped quotes in strings
        output = re.sub(r'(?<!\\)"(?=.*":)', r'\"', output)

        # Fix double quotes at the beginning of keys in nested JSON strings
        # This handles cases like: {\"\"title\":\"Movie\"} -> {\"title\":\"Movie\"}
        output = re.sub(r'\\"\\"', r'\\"', output)

        # Fix missing commas between objects in nested JSON strings
        # This is for the specific case we saw in the logs
        output = re.sub(r'(})\s*({\\"\w+)', r'\1,\2', output)

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

        # Handle nested JSON strings with escaped quotes
        # This is a common issue with LLM outputs that contain JSON strings within JSON
        try:
            # First try to parse as is
            parsed = json.loads(output)

            # If successful, look for nested JSON strings that might need fixing
            if isinstance(parsed, dict) and 'arguments' in parsed:
                args = parsed['arguments']
                if isinstance(args, dict) and 'movies_json' in args:
                    movies_json = args['movies_json']
                    if isinstance(movies_json, str):
                        # Try to fix the nested JSON
                        fixed_movies_json = JsonParser._fix_nested_json(movies_json)
                        if fixed_movies_json != movies_json:
                            args['movies_json'] = fixed_movies_json
                            output = json.dumps(parsed)
                            logger.info("Fixed nested JSON in movies_json")

            # Handle array case
            elif isinstance(parsed, list):
                for item in parsed:
                    if isinstance(item, dict) and 'arguments' in item:
                        args = item['arguments']
                        if isinstance(args, dict) and 'movies_json' in args:
                            movies_json = args['movies_json']
                            if isinstance(movies_json, str):
                                # Try to fix the nested JSON
                                fixed_movies_json = JsonParser._fix_nested_json(movies_json)
                                if fixed_movies_json != movies_json:
                                    args['movies_json'] = fixed_movies_json
                                    output = json.dumps(parsed)
                                    logger.info("Fixed nested JSON in movies_json array item")
        except json.JSONDecodeError:
            # If we can't parse the outer JSON yet, we'll continue with other fixes
            pass

        return output

    @staticmethod
    def _fix_nested_json(json_str: str) -> str:
        """
        Fix common issues in nested JSON strings.

        Args:
            json_str: The nested JSON string to fix

        Returns:
            Fixed nested JSON string
        """
        # Fix double quotes at the beginning of keys
        json_str = re.sub(r'\\"\\"', r'\\"', json_str)

        # Fix missing commas between objects
        json_str = re.sub(r'(})\s*({\\"\w+)', r'\1,\2', json_str)

        # Fix missing commas after values before new keys
        json_str = re.sub(r'([\d"}])\s*(\\"\w+)', r'\1,\2', json_str)

        # Fix type field that's outside the object
        json_str = re.sub(r'(})\s*,"type":"(\w+)"', r',"type":"\2"\1', json_str)

        # Handle the specific pattern we saw in the logs
        if '"type":"str"}' in json_str and not json_str.startswith('['):
            # This might be an array of objects without proper array brackets
            parts = json_str.split('},{')
            if len(parts) > 1:
                # Reconstruct as a proper array
                fixed_parts = []
                for i, part in enumerate(parts):
                    if i == 0:
                        if not part.startswith('{'):
                            part = '{' + part
                    elif i == len(parts) - 1:
                        if not part.endswith('}'):
                            part = part + '}'
                    fixed_parts.append(part)

                json_str = '[' + ','.join(fixed_parts) + ']'
                logger.info(f"Reconstructed nested JSON as array with {len(fixed_parts)} items")

        return json_str

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
    def _handle_specific_error_patterns(output: str, error: json.JSONDecodeError) -> Any:
        """
        Handle specific JSON error patterns that we've seen in the logs.

        Args:
            output: The JSON string with the error
            error: The JSONDecodeError that occurred

        Returns:
            Parsed JSON if successful, None otherwise
        """
        error_msg = str(error)
        error_pos = error.pos
        error_context = output[max(0, error_pos-30):min(len(output), error_pos+30)]

        logger.info(f"Handling specific error pattern: {error_msg} at position {error_pos}")
        logger.info(f"Error context: '{error_context}'")

        # Case 1: Missing comma between objects in a nested JSON string
        if "Expecting ',' delimiter" in error_msg:
            # This is the specific error we saw in the logs
            if "movies_json" in output and "type" in output:
                # Try to fix the nested JSON string in movies_json
                try:
                    # Find the movies_json field
                    movies_json_match = re.search(r'"movies_json"\s*:\s*"(.*?)"(?:,|\})', output, re.DOTALL)
                    if movies_json_match:
                        # Extract the nested JSON string
                        nested_json = movies_json_match.group(1)
                        # Fix the nested JSON
                        fixed_nested_json = JsonParser._fix_nested_json(nested_json)
                        # Replace the original nested JSON with the fixed version
                        fixed_output = output.replace(nested_json, fixed_nested_json)
                        logger.info("Fixed nested JSON in movies_json field")
                        try:
                            return json.loads(fixed_output)
                        except json.JSONDecodeError:
                            # If still can't parse, continue with other approaches
                            pass
                except Exception as e:
                    logger.warning(f"Failed to fix nested JSON: {str(e)}")

            # More general case: try to insert a comma at the error position
            try:
                fixed_output = output[:error_pos] + ',' + output[error_pos:]
                logger.info(f"Inserted comma at position {error_pos}")
                return json.loads(fixed_output)
            except json.JSONDecodeError:
                # If still can't parse, continue with other approaches
                pass

        # Case 2: Unexpected character in JSON
        elif "Expecting property name enclosed in double quotes" in error_msg:
            # Try to fix unquoted property names
            try:
                # Find unquoted property names and quote them
                fixed_output = re.sub(r'([{,])\s*([a-zA-Z0-9_]+)\s*:', r'\1"\2":', output)
                logger.info("Fixed unquoted property names")
                return json.loads(fixed_output)
            except json.JSONDecodeError:
                # If still can't parse, continue with other approaches
                pass

        # Case 3: Extra data after JSON
        elif "Extra data" in error_msg:
            # Try to truncate the JSON at the error position
            try:
                truncated_output = output[:error_pos]
                # Make sure it ends with a valid JSON character
                if truncated_output.endswith('}') or truncated_output.endswith(']'):
                    logger.info(f"Truncated JSON at position {error_pos}")
                    return json.loads(truncated_output)
            except json.JSONDecodeError:
                # If still can't parse, continue with other approaches
                pass

        # No specific handler worked
        return None

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
                # Log detailed error information
                logger.error(f"JSON decode error: {str(je)}")
                error_context = preprocessed_output[max(0, je.pos-30):min(len(preprocessed_output), je.pos+30)]
                logger.error(f"Error context: '{error_context}'")

                # Try to handle specific error patterns we've seen before
                specific_handler_result = JsonParser._handle_specific_error_patterns(preprocessed_output, je)
                if specific_handler_result is not None:
                    return specific_handler_result

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
                            try:
                                return json.loads(truncated)
                            except json.JSONDecodeError:
                                # If still can't parse, continue with other approaches
                                pass

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

                # Look for JSON in markdown code blocks (common in LLM outputs)
                code_block_match = re.search(r'```(?:json)?\s*([\s\S]*?)\s*```', output)
                if code_block_match:
                    code_content = code_block_match.group(1).strip()
                    try:
                        return json.loads(code_content)
                    except json.JSONDecodeError:
                        # Try to preprocess the code block content
                        preprocessed_code = JsonParser._preprocess_json(code_content)
                        try:
                            return json.loads(preprocessed_code)
                        except json.JSONDecodeError:
                            # Continue with other approaches
                            pass

                # Second attempt: Look for JSON-like patterns in the text
                json_match = re.search(r'\[\s*{.*}\s*\]', output, re.DOTALL)
                if json_match:
                    try:
                        return json.loads(json_match.group(0))
                    except json.JSONDecodeError:
                        # Try to preprocess the matched content
                        preprocessed_match = JsonParser._preprocess_json(json_match.group(0))
                        try:
                            return json.loads(preprocessed_match)
                        except json.JSONDecodeError:
                            # Continue with other approaches
                            pass

                # Third attempt: Look for JSON surrounded by triple backticks
                json_match = re.search(r'```(?:json)?\s*(\[\s*{.*}\s*\])\s*```', output, re.DOTALL)
                if json_match:
                    try:
                        return json.loads(json_match.group(1))
                    except json.JSONDecodeError:
                        # Try to preprocess the matched content
                        preprocessed_match = JsonParser._preprocess_json(json_match.group(1))
                        try:
                            return json.loads(preprocessed_match)
                        except json.JSONDecodeError:
                            # Continue with other approaches
                            pass

                # Fourth attempt: Look for JSON surrounded by backticks
                json_match = re.search(r'`(\[\s*{.*}\s*\])`', output, re.DOTALL)
                if json_match:
                    try:
                        return json.loads(json_match.group(1))
                    except json.JSONDecodeError:
                        # Try to preprocess the matched content
                        preprocessed_match = JsonParser._preprocess_json(json_match.group(1))
                        try:
                            return json.loads(preprocessed_match)
                        except json.JSONDecodeError:
                            # Continue with other approaches
                            pass

                # If all else fails, try to find a JSON object rather than an array
                json_match = re.search(r'{.*}', output, re.DOTALL)
                if json_match:
                    try:
                        obj = json.loads(json_match.group(0))
                        if isinstance(obj, dict):
                            return [obj]
                    except json.JSONDecodeError:
                        # Try to preprocess the matched content
                        preprocessed_match = JsonParser._preprocess_json(json_match.group(0))
                        try:
                            obj = json.loads(preprocessed_match)
                            if isinstance(obj, dict):
                                return [obj]
                        except json.JSONDecodeError:
                            # Continue with other approaches
                            pass

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

                # If we've tried everything and still can't parse, return a default value
                # but log a warning with the first part of the output for debugging
                logger.warning(f"Could not extract JSON from output: {output[:100]}...")

                # For the specific case we saw in the logs, try one more approach
                if "analyze_preferences_tool" in output and "movies_json" in output:
                    logger.info("Detected analyze_preferences_tool pattern, attempting manual extraction")
                    try:
                        # Create a default structure that matches what we expect
                        default_obj = {
                            "name": "analyze_preferences_tool",
                            "arguments": {
                                "movies_json": "[]"
                            }
                        }

                        # Try to extract movie titles from the output
                        movie_titles = re.findall(r'"title"\s*:\s*"([^"]+)"', output)
                        if movie_titles:
                            # Create a simple array of movie objects
                            movies = [{"title": title, "description": ""} for title in movie_titles]
                            default_obj["arguments"]["movies_json"] = json.dumps(movies)
                            logger.info(f"Manually extracted {len(movies)} movie titles")
                            return [default_obj]
                    except Exception as e:
                        logger.error(f"Failed manual extraction: {str(e)}")

                return []
            except Exception as e:
                logger.error(f"Error extracting JSON: {str(e)}")
                # Log the traceback for more detailed debugging
                logger.error(f"Traceback: {traceback.format_exc()}")
                return []
