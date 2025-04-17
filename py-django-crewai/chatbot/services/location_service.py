"""
Location and geocoding services for the movie chatbot.
"""

import logging
import math
import requests
from geopy.geocoders import Nominatim
from geopy.distance import geodesic
from typing import Dict, List, Tuple, Optional, Any
from django.conf import settings

from .api_utils import APIRequestHandler

# Configure logger
logger = logging.getLogger('chatbot.location_service')

class LocationService:
    """Service for geocoding and finding nearby theaters."""

    def __init__(self, user_agent: str = "movie_chatbot_geocoder", timeout: int = None):
        """Initialize the location service.

        Args:
            user_agent: User agent string for Nominatim (required by their ToS)
            timeout: Timeout for Nominatim requests in seconds (defaults to settings.API_REQUEST_TIMEOUT)
        """
        # Use timeout from parameters or settings
        timeout = timeout or getattr(settings, 'API_REQUEST_TIMEOUT', 30)
        logger.info(f"Initializing LocationService with timeout={timeout}s")
        self.geolocator = Nominatim(user_agent=user_agent, timeout=timeout)

    def geocode_location(self, location_str: str) -> Optional[Dict[str, Any]]:
        """Convert a location string to coordinates.

        Args:
            location_str: A string representing a location (e.g., "New York", "123 Main St")

        Returns:
            Dict with latitude, longitude, and display_name if successful, None otherwise
        """
        try:
            if not location_str or location_str.lower() == "unknown":
                logger.warning("No valid location provided for geocoding")
                return None

            # Clean up the location string
            location_str = location_str.strip()

            # Geocode the location with retry mechanism
            def make_geocode_request(*args, **kwargs):
                return self.geolocator.geocode(location_str, exactly_one=True)

            location = APIRequestHandler.make_request(make_geocode_request)

            if location:
                return {
                    "latitude": location.latitude,
                    "longitude": location.longitude,
                    "display_name": location.address
                }
            else:
                logger.warning(f"Could not geocode location: {location_str}")
                return None

        except Exception as e:
            logger.error(f"Error geocoding location '{location_str}': {str(e)}")
            return None

    def get_location_from_ip(self, ip_address: str) -> Optional[Dict[str, Any]]:
        """Get user location from IP address.

        Args:
            ip_address: The user's IP address

        Returns:
            Dict with latitude, longitude, and display_name if successful, None otherwise
        """
        try:
            # Check if it's a local/private IP (don't attempt to geolocate)
            if ip_address in ('127.0.0.1', 'localhost', '::1') or ip_address.startswith(('192.168.', '10.', '172.16.')):
                logger.info(f"Local IP detected ({ip_address}), skipping geolocation")
                return None

            # Use ipinfo.io for geolocation with retry mechanism
            def make_ip_request(*args, **kwargs):
                response = requests.get(f"https://ipinfo.io/{ip_address}/json")
                response.raise_for_status()
                return response.json()

            try:
                data = APIRequestHandler.make_request(make_ip_request)

                # Check if we got location data
                if 'loc' in data and data['loc']:
                    # Format is "latitude,longitude"
                    lat, lon = data['loc'].split(',')
                    city = data.get('city', '')
                    region = data.get('region', '')
                    country = data.get('country', '')

                    # Build a display name
                    display_parts = []
                    if city:
                        display_parts.append(city)
                    if region:
                        display_parts.append(region)
                    if country:
                        display_parts.append(country)

                    display_name = ', '.join(display_parts)

                    # Extract timezone information if available
                    timezone = data.get('timezone')
                    utc_offset = data.get('utc_offset')

                    return {
                        "latitude": float(lat),
                        "longitude": float(lon),
                        "display_name": display_name,
                        "timezone": timezone,
                        "utc_offset": utc_offset
                    }
                else:
                    logger.warning(f"Location data not found in IP info response for: {ip_address}")
            except Exception as e:
                logger.warning(f"Could not get location from IP: {ip_address}, error: {str(e)}")

            return None

        except Exception as e:
            logger.error(f"Error getting location from IP '{ip_address}': {str(e)}")
            return None

    def search_theaters(self, latitude: float, longitude: float, radius_miles: float = 20) -> List[Dict[str, Any]]:
        """Search for movie theaters within a specified radius.

        Args:
            latitude: Latitude coordinate
            longitude: Longitude coordinate
            radius_miles: Search radius in miles (default: 20)

        Returns:
            List of theater dictionaries with name, address, and coordinates
        """
        try:
            # Convert radius to meters for Overpass API
            radius_meters = radius_miles * 1609.34

            # Build Overpass API query for movie theaters
            # amenity=cinema is the OSM tag for movie theaters
            overpass_url = "https://overpass-api.de/api/interpreter"
            overpass_query = f"""
            [out:json];
            (
              node["amenity"="cinema"](around:{radius_meters},{latitude},{longitude});
              way["amenity"="cinema"](around:{radius_meters},{latitude},{longitude});
              relation["amenity"="cinema"](around:{radius_meters},{latitude},{longitude});
            );
            out center;
            """

            # Execute query with retry mechanism
            def make_overpass_request(*args, **kwargs):
                response = requests.post(overpass_url, data=overpass_query)
                response.raise_for_status()
                return response.json()

            try:
                data = APIRequestHandler.make_request(make_overpass_request)
            except Exception as e:
                logger.error(f"Error querying Overpass API: {str(e)}")
                return []

            theaters = []
            for element in data.get('elements', []):
                try:
                    # Get theater data, handling both nodes and ways/relations with center
                    if element['type'] == 'node':
                        theater_lat = element.get('lat')
                        theater_lon = element.get('lon')
                    else:  # way or relation
                        if 'center' in element:
                            theater_lat = element['center'].get('lat')
                            theater_lon = element['center'].get('lon')
                        else:
                            continue  # Skip if no coordinates

                    # Get theater name and address
                    tags = element.get('tags', {})
                    name = tags.get('name', 'Unknown Theater')

                    # Build address components
                    address_parts = []
                    if 'addr:housenumber' in tags and 'addr:street' in tags:
                        address_parts.append(f"{tags['addr:housenumber']} {tags['addr:street']}")
                    elif 'addr:street' in tags:
                        address_parts.append(tags['addr:street'])

                    if 'addr:city' in tags:
                        address_parts.append(tags['addr:city'])
                    if 'addr:state' in tags:
                        address_parts.append(tags['addr:state'])
                    if 'addr:postcode' in tags:
                        address_parts.append(tags['addr:postcode'])

                    # If no structured address, try with addr:full or check for description
                    if not address_parts:
                        if 'addr:full' in tags:
                            address = tags['addr:full']
                        else:
                            address = "No address available"
                    else:
                        address = ", ".join(address_parts)

                    # Calculate distance
                    origin = (latitude, longitude)
                    theater_coords = (theater_lat, theater_lon)
                    distance_miles = geodesic(origin, theater_coords).miles

                    theaters.append({
                        "name": name,
                        "address": address,
                        "latitude": theater_lat,
                        "longitude": theater_lon,
                        "distance_miles": round(distance_miles, 1)
                    })

                except Exception as detail_e:
                    logger.error(f"Error processing theater data: {str(detail_e)}")
                    continue

            # Sort theaters by distance
            theaters.sort(key=lambda x: x.get('distance_miles', float('inf')))

            logger.info(f"Found {len(theaters)} theaters within {radius_miles} miles")
            return theaters

        except Exception as e:
            logger.error(f"Error searching for theaters: {str(e)}")
            return []


    def calculate_distance(self, lat1: float, lon1: float, lat2: float, lon2: float) -> float:
        """Calculate distance between coordinates in miles."""
        origin = (lat1, lon1)
        destination = (lat2, lon2)
        return geodesic(origin, destination).miles
