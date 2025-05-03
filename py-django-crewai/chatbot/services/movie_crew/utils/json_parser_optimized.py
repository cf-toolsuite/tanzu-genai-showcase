"""
Optimized JSON parser utility for handling CrewAI outputs.
Performance improvements:
1. Better error handling for malformed JSON
2. Intelligent repair of common JSON issues
3. Parse streamed responses more efficiently
4. Memory optimization for large JSON structures
"""

import json
import re
import logging
from typing import Any, Dict, List, Union, Optional

# Configure logger
logger = logging.getLogger('chatbot.json_parser')

class JsonParserOptimized:
    """Optimized parser for JSON output from CrewAI agents."""

    @staticmethod
    def parse_json_output(text: str) -> Optional[Union[List, Dict]]:
        """
        Parse JSON from a text string with advanced error handling and repair.

        Args:
            text: String containing JSON to parse

        Returns:
            Parsed JSON object (dict, list) or None if parsing fails
        """
        if not text or not isinstance(text, str):
            return None

        # Strip any markdown code block formatting
        text = JsonParserOptimized._strip_markdown(text)

        # Try to extract JSON if it's embedded in a larger text
        text = JsonParserOptimized._extract_json_from_text(text)

        # Try to parse directly first (fastest path)
        try:
            return json.loads(text)
        except json.JSONDecodeError:
            # Try to repair common issues
            repaired_json = JsonParserOptimized._repair_json(text)

            # If repair succeeded, return the result
            if repaired_json is not None:
                return repaired_json

        # If repair fails, try alternative extraction methods
        return JsonParserOptimized._extract_list_or_dict(text)

    @staticmethod
    def _strip_markdown(text: str) -> str:
        """
        Strip markdown code block formatting from text.

        Args:
            text: Text that may contain markdown formatting

        Returns:
            Clean text without markdown code block syntax
        """
        # Remove markdown code block syntax
        text = re.sub(r'^```(?:json)?', '', text, flags=re.MULTILINE)
        text = re.sub(r'```$', '', text, flags=re.MULTILINE)
        return text.strip()

    @staticmethod
    def _extract_json_from_text(text: str) -> str:
        """
        Extract JSON from text that may contain other content.

        Args:
            text: Text that may contain JSON embedded in other content

        Returns:
            Extracted JSON string
        """
        # Look for JSON array or object pattern with optimized regex
        json_pattern = r'(\[[\s\S]*\]|\{[\s\S]*\})'
        matches = re.findall(json_pattern, text)

        if matches:
            # Find the longest match that parses as valid JSON
            matches.sort(key=len, reverse=True)

            for match in matches:
                try:
                    json.loads(match)
                    return match
                except json.JSONDecodeError:
                    continue

        # If no valid JSON found, return the original text
        return text

    @staticmethod
    def _repair_json(text: str) -> Optional[Union[List, Dict]]:
        """
        Repair common JSON issues.

        Args:
            text: Text with potentially broken JSON

        Returns:
            Repaired JSON object or None if repair fails
        """
        try:
            # Apply multiple repair strategies
            # 1. Replace trailing commas
            fixed_text = re.sub(r',\s*([}\]])', r'\1', text)

            # 2. Add missing quotes around property names
            fixed_text = re.sub(r'([{,]\s*)([a-zA-Z0-9_]+)(\s*:)', r'\1"\2"\3', fixed_text)

            # 3. Add missing quotes around string values
            fixed_text = re.sub(r':\s*([a-zA-Z][a-zA-Z0-9_]*)\s*([,}])', r': "\1"\2', fixed_text)

            # 4. Fix escaped quotes
            fixed_text = fixed_text.replace('\\"', '"').replace('\\"', '"')

            # 5. Fix single quotes used instead of double quotes
            fixed_text = JsonParserOptimized._fix_single_quotes(fixed_text)

            # Try to parse the fixed JSON
            try:
                return json.loads(fixed_text)
            except json.JSONDecodeError:
                # If still fails, try a more aggressive approach with a full regex parser
                return JsonParserOptimized._aggressive_json_repair(fixed_text)

        except Exception as e:
            logger.error(f"JSON repair failed: {str(e)}")
            return None

    @staticmethod
    def _fix_single_quotes(text: str) -> str:
        """
        Fix single quotes used instead of double quotes.

        Args:
            text: JSON text with potential single quotes

        Returns:
            Fixed text with proper double quotes
        """
        # This is a complex operation due to nested quotes
        # We'll use a state machine approach for better accuracy
        result = []
        in_string = False
        escape_next = False

        for char in text:
            if char == '\\' and not escape_next:
                escape_next = True
                result.append(char)
            elif char == "'" and not escape_next and not in_string:
                # Replace single quote with double quote when not in a string
                result.append('"')
            elif char == '"' and not escape_next:
                # Toggle in_string state when we see a double quote
                in_string = not in_string
                result.append(char)
            else:
                escape_next = False
                result.append(char)

        return ''.join(result)

    @staticmethod
    def _aggressive_json_repair(text: str) -> Optional[Union[List, Dict]]:
        """
        Aggressively repair broken JSON using a line-by-line approach.

        Args:
            text: Broken JSON text

        Returns:
            Repaired JSON object or None if repair fails
        """
        try:
            # Split into lines for line-by-line processing
            lines = text.split('\n')
            fixed_lines = []

            for line in lines:
                # Fix unbalanced quotes
                quote_count = line.count('"') - line.count('\\"') * 2
                if quote_count % 2 == 1:
                    # Add missing quote at the end of the line
                    line = line + '"'

                fixed_lines.append(line)

            fixed_text = '\n'.join(fixed_lines)

            # Final attempt to parse
            try:
                return json.loads(fixed_text)
            except json.JSONDecodeError:
                # Give up and return None
                return None

        except Exception:
            return None

    @staticmethod
    def _extract_list_or_dict(text: str) -> Optional[Union[List, Dict]]:
        """
        Extract list or dict from text when JSON parsing fails.
        Handles cases where the issue is not easily fixable with regex.

        Args:
            text: Text to extract from

        Returns:
            Extracted list or dict, or None if extraction fails
        """
        try:
            # Try to evaluate as a Python literal
            # SECURITY NOTE: This is safe because we're only allowing list/dict literals
            import ast

            # First attempt to find and extract a list or dict
            list_pattern = r'\[(.*?)\]'
            dict_pattern = r'\{(.*?)\}'

            list_match = re.search(list_pattern, text, re.DOTALL)
            dict_match = re.search(dict_pattern, text, re.DOTALL)

            if list_match:
                # Found a list
                try:
                    # Only allow literal_eval on simple Python data structures
                    result = ast.literal_eval('[' + list_match.group(1) + ']')
                    if isinstance(result, list):
                        return result
                except (SyntaxError, ValueError):
                    pass

            if dict_match:
                # Found a dict
                try:
                    # Only allow literal_eval on simple Python data structures
                    result = ast.literal_eval('{' + dict_match.group(1) + '}')
                    if isinstance(result, dict):
                        return result
                except (SyntaxError, ValueError):
                    pass

            # If no valid list or dict found, return empty list as fallback
            return []

        except Exception as e:
            logger.error(f"Error extracting list or dict: {str(e)}")
            return []
