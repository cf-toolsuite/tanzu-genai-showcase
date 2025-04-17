"""
Test script for TMDB API integration.
"""
import os
import sys
import json
from django.conf import settings

# Add the parent directory to sys.path to allow importing from chatbot
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from services.tmdb_service import TMDBService

def test_tmdb_service():
    """Test the TMDB service with a sample movie."""
    print("Testing TMDB Service Integration...")

    # Get API key from settings or environment
    tmdb_api_key = getattr(settings, 'TMDB_API_KEY', None) or os.environ.get('TMDB_API_KEY')

    if not tmdb_api_key:
        print("Error: No TMDB API key found. Set TMDB_API_KEY in .env or settings.")
        return False

    # Initialize the service
    service = TMDBService(api_key=tmdb_api_key)

    # Test movie search
    print("\nTesting movie search...")
    try:
        search_results = service.search_movies("Avengers", page=1)
        if search_results and 'results' in search_results and search_results['results']:
            print(f"✅ Found {len(search_results['results'])} Avengers movies")

            # Take the first result for further testing
            first_movie = search_results['results'][0]
            movie_id = first_movie['id']
            movie_title = first_movie['title']
            print(f"🎬 Selected movie: {movie_title} (ID: {movie_id})")
        else:
            print("❌ No search results found")
            return False
    except Exception as e:
        print(f"❌ Error searching for movies: {str(e)}")
        return False

    # Test movie details
    print("\nTesting movie details...")
    try:
        movie_details = service.get_movie_details(movie_id)
        if movie_details and 'title' in movie_details:
            print(f"✅ Found details for {movie_details['title']}")

            # Print some movie info
            release_date = movie_details.get('release_date', 'Unknown')
            popularity = movie_details.get('popularity', 'Unknown')
            print(f"📅 Release date: {release_date}")
            print(f"📈 Popularity: {popularity}")
        else:
            print("❌ No movie details found")
    except Exception as e:
        print(f"❌ Error getting movie details: {str(e)}")

    # Test movie images
    print("\nTesting movie images...")
    try:
        movie_images = service.get_movie_images(movie_id)
        if movie_images and ('posters' in movie_images or 'backdrops' in movie_images):
            poster_count = len(movie_images.get('posters', []))
            backdrop_count = len(movie_images.get('backdrops', []))
            print(f"✅ Found {poster_count} posters and {backdrop_count} backdrops")

            # Get best poster URL
            best_poster = service.get_best_poster_url(movie_id, size='original')
            if best_poster:
                print(f"🖼️ Best poster URL: {best_poster}")
            else:
                print("⚠️ No poster URL found")
        else:
            print("❌ No movie images found")
    except Exception as e:
        print(f"❌ Error getting movie images: {str(e)}")

    # Test enhance movie data
    print("\nTesting movie data enhancement...")
    try:
        # Create sample movie data
        sample_movie = {
            "title": movie_title,
            "tmdb_id": movie_id,
            "overview": "Sample overview",
            "release_date": "2022-01-01"
        }

        # Enhance the movie data
        enhanced_movie = service.enhance_movie_data(sample_movie)

        # Check if enhancement was successful
        if enhanced_movie.get('poster_url') and enhanced_movie.get('poster_url') != sample_movie.get('poster_url'):
            print(f"✅ Successfully enhanced movie with poster URL: {enhanced_movie['poster_url']}")

            # Print additional fields added by enhancement
            additional_fields = [field for field in enhanced_movie if field not in sample_movie]
            print(f"➕ Added fields: {', '.join(additional_fields)}")

            # Print poster sizes if available
            if 'poster_urls' in enhanced_movie:
                print(f"🖼️ Available poster sizes: {', '.join(enhanced_movie['poster_urls'].keys())}")
        else:
            print("⚠️ Movie enhancement didn't add poster URL")
    except Exception as e:
        print(f"❌ Error enhancing movie data: {str(e)}")

    print("\nTMDB Service Integration Test Complete!")
    return True

if __name__ == "__main__":
    # This script can be run directly for testing
    success = test_tmdb_service()
    print(f"\nTest {'passed' if success else 'failed'}")
