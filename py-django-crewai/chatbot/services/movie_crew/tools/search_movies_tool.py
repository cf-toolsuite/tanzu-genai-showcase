"""
Tool for searching movies based on user criteria.
"""
import json
import logging
from datetime import datetime
from typing import Dict, Any
import tmdbsimple as tmdb
from crewai.tools import BaseTool
from pydantic import BaseModel, Field
from django.conf import settings

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class SearchMoviesInput(BaseModel):
    """Input schema for SearchMoviesTool."""
    query: str = Field(default="", description="The search query for movies")

class SearchMoviesTool(BaseTool):
    """Tool for searching movies based on user criteria."""

    name: str = "search_movies_tool"
    description: str = "Search for movies based on user criteria."
    args_schema: type[SearchMoviesInput] = SearchMoviesInput
    first_run_mode: bool = True  # Default to First Run mode (theater search)

    def _run(self, query: str = "") -> str:
        """
        Search for movies based on user criteria.

        Args:
            query: The search query for movies

        Returns:
            JSON string containing movie results
        """
        try:
            # Use the query parameter if provided
            search_query = query if query else ""

            # Import SerpAPI service if we're in First Run mode
            if self.first_run_mode:
                try:
                    # Import service without re-importing settings (already imported at module level)
                    from ...serp_service import SerpShowtimeService

                    # Check if SerpAPI key is available
                    if hasattr(settings, 'SERPAPI_API_KEY') and settings.SERPAPI_API_KEY:
                        logger.info("Using SerpAPI to search for movies currently in theaters")
                        serp_service = SerpShowtimeService(api_key=settings.SERPAPI_API_KEY)

                        # Extract genres or keywords from the query
                        genre_keywords = []
                        if "family" in search_query.lower():
                            genre_keywords.append("family")
                        if "action" in search_query.lower():
                            genre_keywords.append("action")
                        if "comedy" in search_query.lower():
                            genre_keywords.append("comedy")
                        if "thriller" in search_query.lower():
                            genre_keywords.append("thriller")

                        # Construct a more specific search query
                        serp_query = search_query
                        if genre_keywords:
                            serp_query = f"{' '.join(genre_keywords)} movies in theaters"
                        else:
                            serp_query = "movies currently in theaters"

                        logger.info(f"Searching SerpAPI with query: {serp_query}")

                        # Search for movies currently in theaters
                        now_playing_results = serp_service.search_movies_in_theaters(query=serp_query)

                        if now_playing_results and len(now_playing_results) > 0:
                            logger.info(f"Found {len(now_playing_results)} movies currently in theaters via SerpAPI")

                            # Process SerpAPI results
                            movies = []
                            # Limit to configured number of results
                            results_limit = getattr(settings, 'MOVIE_RESULTS_LIMIT', 5)
                            for movie in now_playing_results[:results_limit]:
                                # Create a movie dictionary from SerpAPI results
                                movies.append(self._create_movie_dict(
                                    title=movie.get('title', 'Unknown Title'),
                                    overview=movie.get('description', ''),
                                    release_date=movie.get('release_date', ''),
                                    poster_url=movie.get('thumbnail', ''),
                                    tmdb_id=movie.get('id'),  # May need to search TMDB to get this
                                    rating=movie.get('rating', 0),
                                    is_current_release=True  # SerpAPI only returns current releases
                                ))

                            # If we found movies via SerpAPI, return immediately
                            if movies:
                                return json.dumps(movies)
                except Exception as serp_error:
                    logger.error(f"Error using SerpAPI to search for movies: {str(serp_error)}")
                    # Continue with TMDB search as fallback

            # Check for currently playing movies in TMDB (as fallback or for casual viewing)
            search_for_now_playing = any(term in search_query.lower() for term in
                                       ['now playing', 'playing now', 'current', 'in theaters', 'theaters now',
                                        'showing now', 'showing at', 'playing at', 'weekend', 'this week'])

            # Always prioritize now_playing search in First Run mode
            if self.first_run_mode:
                search_for_now_playing = True
                logger.info("Forcing now_playing search in First Run mode")

            # Check if looking for specific genres
            genre_terms = {
                'action': 28,
                'adventure': 12,
                'animation': 16,
                'comedy': 35,
                'crime': 80,
                'documentary': 99,
                'drama': 18,
                'family': 10751,
                'fantasy': 14,
                'history': 36,
                'horror': 27,
                'music': 10402,
                'mystery': 9648,
                'romance': 10749,
                'sci fi': 878,
                'science fiction': 878,
                'thriller': 53,
                'war': 10752,
                'western': 37
            }

            # Extract genre IDs from the query
            genres = []
            for genre, genre_id in genre_terms.items():
                if genre in search_query.lower():
                    genres.append(genre_id)

            movies = []

            # Get current year for determining if a movie is a current release
            current_year = datetime.now().year

            # If looking for now playing movies
            if search_for_now_playing:
                try:
                    now_playing = tmdb.Movies()
                    response = now_playing.now_playing()

                    if response and 'results' in response and response['results']:
                        # Filter by genre if specified
                        results = response['results']
                        if genres:
                            results = [movie for movie in results if any(genre_id in movie.get('genre_ids', []) for genre_id in genres)]

                        # Process limited number of results
                        results_limit = getattr(settings, 'MOVIE_RESULTS_LIMIT', 5)
                        results = results[:results_limit]
                        for movie in results:
                            movie_id = movie.get('id')
                            title = movie.get('title', 'Unknown Title')
                            overview = movie.get('overview', '')
                            release_date = movie.get('release_date', '')
                            poster_path = movie.get('poster_path', '')

                            # Get full-size poster image
                            poster_url = ""
                            if poster_path:
                                poster_url = f"https://image.tmdb.org/t/p/original{poster_path}"

                                # Try to get additional images using the movie images endpoint
                                try:
                                    movie_details = tmdb.Movies(movie_id)
                                    images = movie_details.images()

                                    # If we have additional posters, use the first one at original size
                                    if images and 'posters' in images and images['posters']:
                                        best_poster = images['posters'][0]  # Use first poster (usually the best)
                                        poster_url = f"https://image.tmdb.org/t/p/original{best_poster['file_path']}"
                                        logger.info(f"Using high-quality poster from images endpoint for movie: {title}")
                                except Exception as poster_error:
                                    logger.warning(f"Could not fetch additional images, using standard poster: {str(poster_error)}")

                            # Get movie release year
                            release_year = None
                            if release_date and len(release_date) >= 4:
                                try:
                                    release_year = int(release_date[:4])
                                except:
                                    pass

                            # Mark as current release if it's from this year or last year
                            is_current_release = release_year is not None and release_year >= (current_year - 1)

                            # Create movie dictionary with both ID fields for compatibility
                            movie_dict = self._create_movie_dict(
                                title=title,
                                overview=overview,
                                release_date=release_date,
                                poster_url=poster_url,
                                tmdb_id=movie_id,
                                rating=movie.get('vote_average', 0),
                                is_current_release=is_current_release
                            )
                            
                            # Ensure both id and tmdb_id fields are present for compatibility
                            movie_dict['id'] = movie_id
                            
                            # Add additional poster size options
                            if poster_path:
                                movie_dict['poster_urls'] = {
                                    'small': f"https://image.tmdb.org/t/p/w200{poster_path}",
                                    'medium': f"https://image.tmdb.org/t/p/w500{poster_path}",
                                    'large': f"https://image.tmdb.org/t/p/w780{poster_path}",
                                    'original': f"https://image.tmdb.org/t/p/original{poster_path}"
                                }
                            
                            movies.append(movie_dict)
                except Exception as e:
                    logger.error(f"Error fetching now playing movies: {str(e)}")

            # Check for decade or year range in the query
            import re
            year_ranges = []
            
            # Check for specific decades (90s, 1990s, etc.)
            decade_patterns = [
                (r'1990s|90s|nineties', (1990, 1999)),
                (r'1980s|80s|eighties', (1980, 1989)),
                (r'1970s|70s|seventies', (1970, 1979)),
                (r'1960s|60s|sixties', (1960, 1969)),
                (r'1950s|50s|fifties', (1950, 1959)),
                (r'2000s|two thousands', (2000, 2009)),
                (r'2010s|twenty tens', (2010, 2019)),
                (r'2020s|twenty twenties', (2020, 2029))
            ]
            
            for pattern, (start_year, end_year) in decade_patterns:
                if re.search(fr'\b{pattern}\b', search_query.lower()):
                    year_ranges.append((start_year, end_year))
                    logger.info(f"Detected decade: {start_year}-{end_year} in query: {search_query}")
            
            # Check for year range patterns like "2000-2010" or "between 2000 and 2010"
            range_matches = re.findall(r'(\d{4})\s*-\s*(\d{4})', search_query)
            if range_matches:
                for start, end in range_matches:
                    year_ranges.append((int(start), int(end)))
                    logger.info(f"Detected explicit year range: {start}-{end} in query")
                    
            # Check for "between X and Y" patterns
            between_matches = re.findall(r'between\s+(\d{4})\s+and\s+(\d{4})', search_query.lower())
            if between_matches:
                for start, end in between_matches:
                    year_ranges.append((int(start), int(end)))
                    logger.info(f"Detected 'between' year range: {start}-{end} in query")
            
            # Check for from/before/after year patterns
            from_year_match = re.search(r'from\s+(\d{4})', search_query.lower())
            if from_year_match:
                year = int(from_year_match.group(1))
                year_ranges.append((year, datetime.now().year))
                logger.info(f"Detected 'from year' pattern: {year}-present in query")
                
            before_year_match = re.search(r'before\s+(\d{4})', search_query.lower())
            if before_year_match:
                year = int(before_year_match.group(1))
                default_start_year = getattr(settings, 'DEFAULT_SEARCH_START_YEAR', 1900)
                year_ranges.append((default_start_year, year))
                logger.info(f"Detected 'before year' pattern: {default_start_year}-{year} in query")
                
            after_year_match = re.search(r'after\s+(\d{4})', search_query.lower())
            if after_year_match:
                year = int(after_year_match.group(1))
                year_ranges.append((year, datetime.now().year))
                logger.info(f"Detected 'after year' pattern: {year}-present in query")
            
            # If no movies found or not looking for now playing, do a regular search
            if not movies:
                # Use title for specific searches
                search = tmdb.Search()
                
                # Base parameters
                search_params = {
                    "query": search_query,
                    "include_adult": False,
                    "language": "en-US",
                    "page": 1
                }
                
                # If we're in casual mode and have year ranges, try to use the discover API first
                if not self.first_run_mode and year_ranges:
                    try:
                        # Get results limit from the settings
                        results_limit = getattr(settings, 'MOVIE_RESULTS_LIMIT', 5)
                        logger.info(f"Using discover API for year-specific search in casual mode")
                        
                        # Use the discover API directly to get popular movies from the decade
                        discover = tmdb.Discover()
                        
                        # Use the first detected year range
                        start_year, end_year = year_ranges[0]
                        
                        # Log the exact years we're searching for
                        logger.info(f"Searching for movies released between {start_year} and {end_year}")
                        
                        discover_params = {
                            "primary_release_date.gte": f"{start_year}-01-01",
                            "primary_release_date.lte": f"{end_year}-12-31",
                            "sort_by": "vote_average.desc", # Sort by highest rated first
                            "vote_count.gte": 100  # Ensure we get well-known movies with sufficient votes
                        }
                        
                        # Add genres if specified
                        if genres:
                            discover_params["with_genres"] = ",".join(str(g) for g in genres)
                        
                        discover_response = discover.movie(**discover_params)
                        
                        if discover_response and 'results' in discover_response and discover_response['results']:
                            logger.info(f"Found {len(discover_response['results'])} movies via discover API with year range {start_year}-{end_year}")
                            results = discover_response['results']
                            
                            # Process limited number of results
                            results_limit = getattr(settings, 'MOVIE_RESULTS_LIMIT', 5)
                            results = results[:results_limit]
                            year_filtered_movies = []
                            
                            # Process discovered movies with the year range
                            for movie in results:
                                # Create and append the movie dict
                                year_filtered_movies.append(self._process_movie_result(movie, start_year, end_year))
                            
                            # If we found movies with the discover API, use them and skip regular search
                            if year_filtered_movies:
                                movies = year_filtered_movies
                                logger.info(f"Using {len(movies)} year-filtered movies from discover API")
                                return json.dumps(movies)
                    except Exception as discover_error:
                        logger.error(f"Error using discover API for year range: {str(discover_error)}")
                        # Fall back to regular search
                
                # Regular search if discover didn't yield results or wasn't used
                search_response = search.movie(**search_params)

                if search_response and 'results' in search_response and search_response['results']:
                    # Start with complete results
                    results = search_response['results']
                    
                    # Filter by genre if specified
                    if genres:
                        results = [movie for movie in results if any(genre_id in movie.get('genre_ids', []) for genre_id in genres)]
                        logger.info(f"Filtered to {len(results)} movies by genre from search results")
                    
                    # Filter by year range if specified
                    if year_ranges:
                        year_filtered_results = []
                        for movie in results:
                            release_date = movie.get('release_date', '')
                            if release_date and len(release_date) >= 4:
                                try:
                                    release_year = int(release_date[:4])
                                    # Check if the release year is in any of the specified ranges
                                    if any(start_year <= release_year <= end_year for start_year, end_year in year_ranges):
                                        year_filtered_results.append(movie)
                                except ValueError:
                                    continue
                        
                        if year_filtered_results:
                            logger.info(f"Filtered to {len(year_filtered_results)} movies by year range from search results")
                            results = year_filtered_results
                        else:
                            logger.warning(f"No movies found in the specified year range(s) from search results")
                    
                    # Sort by release date if we have year ranges (older first for nostalgia queries)
                    if year_ranges:
                        results.sort(key=lambda m: m.get('release_date', ''), reverse=False)
                    
                    # Process the first 5 results (or fewer if filtered)
                    results = results[:5]
                    for movie in results:
                        movie_id = movie.get('id')
                        title = movie.get('title', 'Unknown Title')
                        overview = movie.get('overview', '')
                        release_date = movie.get('release_date', '')
                        poster_path = movie.get('poster_path', '')
                        poster_url = f"https://image.tmdb.org/t/p/original{poster_path}" if poster_path else ""

                        # Get movie release year for determining if it's a current release
                        release_year = None
                        if release_date and len(release_date) >= 4:
                            try:
                                release_year = int(release_date[:4])
                            except:
                                pass

                        # Mark as current release if it's from this year or last year
                        current_year = datetime.now().year
                        is_current_release = release_year is not None and release_year >= (current_year - 1)

                        # Create movie dictionary with both ID fields for compatibility
                        movie_dict = self._create_movie_dict(
                            title=title,
                            overview=overview,
                            release_date=release_date,
                            poster_url=poster_url,
                            tmdb_id=movie_id,
                            rating=movie.get('vote_average', 0),
                            is_current_release=is_current_release
                        )
                        
                        # Ensure both id and tmdb_id fields are present for compatibility
                        movie_dict['id'] = movie_id
                        
                        # Add additional poster size options
                        if poster_path:
                            movie_dict['poster_urls'] = {
                                'small': f"https://image.tmdb.org/t/p/w200{poster_path}",
                                'medium': f"https://image.tmdb.org/t/p/w500{poster_path}",
                                'large': f"https://image.tmdb.org/t/p/w780{poster_path}",
                                'original': f"https://image.tmdb.org/t/p/original{poster_path}"
                            }
                        
                        movies.append(movie_dict)

            # If still no movies and we have genres, try discover API
            if not movies and genres:
                try:
                    discover = tmdb.Discover()
                    discover_response = discover.movie(with_genres=','.join(str(g) for g in genres))

                    if discover_response and 'results' in discover_response and discover_response['results']:
                        # Process limited number of results
                        results_limit = getattr(settings, 'MOVIE_RESULTS_LIMIT', 5)
                        results = discover_response['results'][:results_limit]
                        for movie in results:
                            movie_id = movie.get('id')
                            title = movie.get('title', 'Unknown Title')
                            overview = movie.get('overview', '')
                            release_date = movie.get('release_date', '')
                            poster_path = movie.get('poster_path', '')
                            poster_url = f"https://image.tmdb.org/t/p/original{poster_path}" if poster_path else ""

                            # Get movie release year for determining if it's a current release
                            release_year = None
                            if release_date and len(release_date) >= 4:
                                try:
                                    release_year = int(release_date[:4])
                                except:
                                    pass

                            # Mark as current release if it's from this year or last year
                            current_year = datetime.now().year
                            is_current_release = release_year is not None and release_year >= (current_year - 1)

                            # Create movie dictionary with both ID fields for compatibility
                            movie_dict = self._create_movie_dict(
                                title=title,
                                overview=overview,
                                release_date=release_date,
                                poster_url=poster_url,
                                tmdb_id=movie_id,
                                rating=movie.get('vote_average', 0),
                                is_current_release=is_current_release
                            )
                            
                            # Ensure both id and tmdb_id fields are present for compatibility
                            movie_dict['id'] = movie_id
                            
                            # Add additional poster size options
                            if poster_path:
                                movie_dict['poster_urls'] = {
                                    'small': f"https://image.tmdb.org/t/p/w200{poster_path}",
                                    'medium': f"https://image.tmdb.org/t/p/w500{poster_path}",
                                    'large': f"https://image.tmdb.org/t/p/w780{poster_path}",
                                    'original': f"https://image.tmdb.org/t/p/original{poster_path}"
                                }
                            
                            movies.append(movie_dict)
                except Exception as e:
                    logger.error(f"Error with discover API: {str(e)}")

            if not movies:
                logger.warning(f"No movies found for query: {search_query}")

            return json.dumps(movies)
        except Exception as e:
            logger.error(f"Error searching for movies: {str(e)}")
            return json.dumps([])

    def _process_movie_result(self, movie, start_year, end_year) -> Dict[str, Any]:
        """
        Process a movie result from the TMDB API with year range information.
        
        Args:
            movie: Movie data from TMDB API
            start_year: Start year of the decade/range
            end_year: End year of the decade/range
            
        Returns:
            Processed movie dictionary
        """
        # Get the movie ID and ensure it's set correctly
        movie_id = movie.get('id')
        if not movie_id:
            logger.error(f"Movie has no ID: {movie.get('title', 'Unknown')}")
            
        title = movie.get('title', 'Unknown Title')
        overview = movie.get('overview', '')
        release_date = movie.get('release_date', '')
        poster_path = movie.get('poster_path', '')
        poster_url = f"https://image.tmdb.org/t/p/original{poster_path}" if poster_path else ""
        
        # Add TMDB homepage for this movie
        tmdb_url = f"https://www.themoviedb.org/movie/{movie_id}"
        
        # Get movie release year
        release_year = None
        if release_date and len(release_date) >= 4:
            try:
                release_year = int(release_date[:4])
            except:
                pass
        
        # Check if movie is from requested time period
        is_from_requested_period = False
        if release_year and start_year <= release_year <= end_year:
            is_from_requested_period = True
            logger.info(f"Movie '{title}' from {release_year} is within requested period {start_year}-{end_year}")
        
        # Current year for determining if it's a current release
        current_year = datetime.now().year
        is_current_release = release_year is not None and release_year >= (current_year - 1)
        
        # Create movie dictionary
        movie_dict = self._create_movie_dict(
            title=title,
            overview=overview,
            release_date=release_date,
            poster_url=poster_url,
            tmdb_id=movie_id,
            rating=movie.get('vote_average', 0),
            is_current_release=is_current_release
        )
        
        # Add the period information
        movie_dict['is_from_requested_period'] = is_from_requested_period
        movie_dict['decade'] = f"{start_year}s" if start_year % 10 == 0 else f"{start_year}-{end_year}"
        
        # Add TMDB URL for direct linking
        movie_dict['tmdb_url'] = tmdb_url
        
        # Ensure both id and tmdb_id fields are present for compatibility
        movie_dict['id'] = movie_id
        
        # Add additional poster size options
        if poster_path:
            movie_dict['poster_urls'] = {
                'small': f"https://image.tmdb.org/t/p/w200{poster_path}",
                'medium': f"https://image.tmdb.org/t/p/w500{poster_path}",
                'large': f"https://image.tmdb.org/t/p/w780{poster_path}",
                'original': f"https://image.tmdb.org/t/p/original{poster_path}"
            }
            
        return movie_dict

    def _create_movie_dict(self, **kwargs) -> Dict[str, Any]:
        """
        Create a standardized movie dictionary with provided fields.

        Args:
            **kwargs: Movie fields including title, overview, etc.

        Returns:
            Standardized movie dictionary
        """
        # Start with default values
        movie_dict = {
            "title": "Unknown Title",
            "overview": "",
            "release_date": "",
            "poster_url": "",
            "tmdb_id": None,
            "rating": 0,
            "is_current_release": False
        }

        # Update with provided values
        movie_dict.update(kwargs)

        return movie_dict
