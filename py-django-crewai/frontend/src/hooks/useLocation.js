import { useEffect, useCallback } from 'react';
import { useAppContext } from '../context/AppContext';

export function useLocation() {
  const { setLocation } = useAppContext();

  // Function to gather location data from ipapi.co
  const detectLocation = useCallback(() => {
    const gatherLocationDataFromIpApi = () => {
      console.log("Using ipapi.co for location detection");

      // Use ipapi.co - no API key needed
      fetch('https://ipapi.co/json/')
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          console.log("Received ipapi.co response:", data);

          // Check if location is in the US
          if (data.country_code !== 'US') {
            console.log(`Detected non-US location: ${data.country_name || 'unknown'}`);
            handleNonUSLocation();
            return;
          }

          // Extract city and state for US locations
          const city = data.city;
          const state = data.region;
          const country = data.country_name;

          // If we have all values, use the standard "City, State, Country" format
          if (city && state && country) {
            const locationName = `${city}, ${state}, ${country}`;
            console.log(`Setting location to: ${locationName}`);
            setLocation(locationName);
            return;
          }

          // If we couldn't extract both city and state, handle as non-US location
          console.error("Could not parse US city/state from response:", data);
          handleNonUSLocation();
        })
        .catch(error => {
          console.error("Error with ipapi.co:", error);
          handleNonUSLocation();
        });
    };

    // Function when we detect non-US location or can't detect location
    const handleNonUSLocation = () => {
      console.log("Location not in US or couldn't be determined");
      setLocation(''); // Clear the value
    };

    // First try to use browser's geolocation API
    if (navigator.geolocation) {
      console.log("Geolocation API available, requesting position");
      navigator.geolocation.getCurrentPosition(
        function(position) {
          // Success - we have coordinates, now use ipapi.co directly
          gatherLocationDataFromIpApi();
        },
        function(error) {
          console.error(`Geolocation error (${error.code}): ${error.message}`);
          // Fall back to IP-based geolocation
          gatherLocationDataFromIpApi();
        },
        {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 5 * 60 * 1000
        }
      );
    } else {
      // Browser doesn't support geolocation
      console.error("Geolocation not supported by this browser");
      // Fall back to IP-based geolocation
      gatherLocationDataFromIpApi();
    }
  }, [setLocation]);

  // Run location detection once on mount
  useEffect(() => {
    detectLocation();
  }, []); // Empty dependency array means this runs once on mount

  // Return the detectLocation function so it can be called manually
  return detectLocation;
}
