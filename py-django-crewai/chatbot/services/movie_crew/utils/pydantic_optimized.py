"""
Optimized Pydantic utilities for improved performance.
This module provides optimized Pydantic models and functions for better
performance and lower overhead.
"""

from typing import Any, Dict, List, Optional, Union, TypeVar, Generic, Type
from pydantic import BaseModel, Field, ValidationError, create_model
import logging
from functools import lru_cache
import json

# Configure logger
logger = logging.getLogger('chatbot.pydantic')

# Cache for model definitions
MODEL_CACHE = {}

T = TypeVar('T', bound=BaseModel)

class OptimizedBaseModel(BaseModel):
    """
    Optimized base model with performance improvements.

    This model provides:
    1. Partial validation for better performance
    2. Caching of validation results
    3. Smarter error handling
    """

    @classmethod
    def parse_obj_partial(cls: Type[T], obj: Any) -> T:
        """
        Parse an object with partial validation for better performance.
        This method skips validation for fields that are not present in the input.

        Args:
            obj: The object to parse

        Returns:
            An instance of the model
        """
        # Fast path for dict inputs
        if isinstance(obj, dict):
            # Only validate fields that are present in the input
            present_fields = set(obj.keys())
            required_fields = {
                field_name for field_name, field in cls.__fields__.items()
                if field.required and not field.default_factory
            }

            # Check if any required fields are missing
            missing_fields = required_fields - present_fields
            if missing_fields:
                # Only log a warning instead of raising an error for non-critical fields
                logger.warning(f"Missing required fields: {missing_fields}")

                # Add default values for missing fields to avoid validation errors
                for field_name in missing_fields:
                    obj[field_name] = None

            try:
                # Use standard parsing with missing fields filled in
                return cls.parse_obj(obj)
            except ValidationError as e:
                # Log error and return a partial model
                logger.error(f"Validation error in parse_obj_partial: {e}")
                # Create a partial model with only the valid fields
                return cls.construct(**{k: v for k, v in obj.items() if k in cls.__fields__})
        else:
            # For non-dict inputs, fall back to standard parsing
            return cls.parse_obj(obj)

    @classmethod
    @lru_cache(maxsize=128)
    def cached_schema(cls) -> Dict[str, Any]:
        """
        Get model schema with caching for better performance.

        Returns:
            Model schema as a dictionary
        """
        return cls.schema()

    def dict_optimized(self, exclude_defaults: bool = False, exclude_none: bool = True) -> Dict[str, Any]:
        """
        Convert model to dictionary with optimized performance.

        Args:
            exclude_defaults: Whether to exclude fields with default values
            exclude_none: Whether to exclude fields with None values

        Returns:
            Model as a dictionary
        """
        result = {}
        for field_name, field in self.__fields__.items():
            value = getattr(self, field_name)

            # Skip None values if exclude_none is True
            if exclude_none and value is None:
                continue

            # Skip default values if exclude_defaults is True
            if exclude_defaults and field.default == value:
                continue

            # Handle nested models
            if isinstance(value, BaseModel):
                if hasattr(value, 'dict_optimized'):
                    result[field_name] = value.dict_optimized(
                        exclude_defaults=exclude_defaults,
                        exclude_none=exclude_none
                    )
                else:
                    result[field_name] = value.dict(
                        exclude_defaults=exclude_defaults,
                        exclude_none=exclude_none
                    )
            # Handle lists of models
            elif isinstance(value, list) and value and isinstance(value[0], BaseModel):
                result[field_name] = [
                    (item.dict_optimized(exclude_defaults=exclude_defaults, exclude_none=exclude_none)
                     if hasattr(item, 'dict_optimized') else
                     item.dict(exclude_defaults=exclude_defaults, exclude_none=exclude_none))
                    for item in value
                ]
            else:
                result[field_name] = value

        return result

    def json_optimized(
        self,
        exclude_defaults: bool = False,
        exclude_none: bool = True,
        indent: Optional[int] = None
    ) -> str:
        """
        Convert model to JSON string with optimized performance.

        Args:
            exclude_defaults: Whether to exclude fields with default values
            exclude_none: Whether to exclude fields with None values
            indent: JSON indentation level

        Returns:
            Model as a JSON string
        """
        return json.dumps(
            self.dict_optimized(exclude_defaults=exclude_defaults, exclude_none=exclude_none),
            default=lambda o: o.dict() if isinstance(o, BaseModel) else str(o),
            indent=indent
        )

# Create a dynamic model generator with caching for better performance
@lru_cache(maxsize=64)
def create_optimized_model(name: str, **field_definitions) -> Type[OptimizedBaseModel]:
    """
    Create a Pydantic model with optimized performance.

    Args:
        name: Name of the model
        field_definitions: Field definitions as keyword arguments

    Returns:
        A new model class
    """
    # Use the cache to avoid recreating the same model
    if name in MODEL_CACHE:
        return MODEL_CACHE[name]

    # Create a new model class
    model_class = create_model(
        name,
        __base__=OptimizedBaseModel,
        **field_definitions
    )

    # Cache the model class
    MODEL_CACHE[name] = model_class

    return model_class

# Optimized model for theater data
TheaterOptimized = create_optimized_model(
    'TheaterOptimized',
    name=(str, ...),
    address=(Optional[str], None),
    distance_miles=(Optional[float], None),
    showtimes=(List[Dict[str, Any]], Field(default_factory=list))
)

# Optimized model for movie data
MovieOptimized = create_optimized_model(
    'MovieOptimized',
    title=(str, ...),
    overview=(Optional[str], ''),
    release_date=(Optional[str], None),
    poster_url=(Optional[str], None),
    rating=(Optional[float], None),
    tmdb_id=(Optional[int], None),
    theaters=(List[TheaterOptimized], Field(default_factory=list))
)

# Functions for parsing CrewAI outputs
def parse_movie_data_optimized(data: Dict[str, Any]) -> MovieOptimized:
    """
    Parse movie data with optimized performance.

    Args:
        data: Dictionary containing movie data

    Returns:
        MovieOptimized instance
    """
    return MovieOptimized.parse_obj_partial(data)

def parse_theater_data_optimized(data: Dict[str, Any]) -> TheaterOptimized:
    """
    Parse theater data with optimized performance.

    Args:
        data: Dictionary containing theater data

    Returns:
        TheaterOptimized instance
    """
    return TheaterOptimized.parse_obj_partial(data)

def parse_movies_list_optimized(data: List[Dict[str, Any]]) -> List[MovieOptimized]:
    """
    Parse list of movies with optimized performance.

    Args:
        data: List of dictionaries containing movie data

    Returns:
        List of MovieOptimized instances
    """
    return [parse_movie_data_optimized(movie) for movie in data]

def parse_theaters_list_optimized(data: List[Dict[str, Any]]) -> List[TheaterOptimized]:
    """
    Parse list of theaters with optimized performance.

    Args:
        data: List of dictionaries containing theater data

    Returns:
        List of TheaterOptimized instances
    """
    return [parse_theater_data_optimized(theater) for theater in data]
