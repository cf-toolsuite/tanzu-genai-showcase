import React from 'react';
import { useAppContext } from '../../context/AppContext';

function TheaterList({ theaters }) {
  // Check if the application is in a processing state
  const { checkIsProcessing } = useAppContext();
  const isProcessing = checkIsProcessing();
  // Format time to 12-hour format with AM/PM
  const formatTime = (isoString) => {
    try {
      const date = new Date(isoString);
      return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
    } catch (e) {
      console.error('Error formatting time:', e);
      return '';
    }
  };

  // Format distance with appropriate unit
  const formatDistance = (miles) => {
    if (!miles) return '';
    return miles < 0.1
      ? 'Less than 0.1 mi'
      : `${miles.toFixed(1)} mi`;
  };

  return (
    <div className="theater-list">
      {theaters.map((theater, index) => (
        <div key={index} className="theater-item">
          {/* Theater Header */}
          <div className="theater-header">
            <div>
              <div className="theater-name">
                {theater.name}
                {theater.distance_miles && (
                  <span className="distance-badge">
                    {formatDistance(theater.distance_miles)}
                  </span>
                )}
              </div>
              <div className="theater-address">
                {theater.address || ''}
              </div>
            </div>
            {theater.amenities && theater.amenities.length > 0 && (
              <div className="theater-amenities">
                {theater.amenities.map((amenity, i) => (
                  <span key={i} className="amenity-badge">
                    {amenity}
                  </span>
                ))}
              </div>
            )}
          </div>

          {/* Theater Showtimes */}
          <div className="theater-showtimes">
            {/* For each format (Standard, IMAX, etc.) */}
            {Object.entries(theater.showtimesByFormat).map(([format, times], formatIndex) => (
              <div key={formatIndex} className="showtime-format">
                <div className="format-label">
                  <i className={`bi ${
                    format.toLowerCase().includes('imax') ? 'bi-badge-hd-fill' :
                    format.toLowerCase().includes('3d') ? 'bi-badge-3d' :
                    'bi-film'
                  }`}></i>
                  {format}
                </div>
                <div className="showtime-buttons">
                  {/* Sort times chronologically */}
                  {times
                    .map(timeStr => new Date(timeStr))
                    .sort((a, b) => a - b)
                    .map((date, timeIndex) => {
                      // Create showtime badge with appropriate class based on format
                      const badgeClass = format.toLowerCase().includes('imax') ? 'imax' :
                                       format.toLowerCase().includes('3d') ? 'threed' : '';

                      return (
                        <button
                          key={timeIndex}
                          className={`btn btn-sm btn-outline-light ${badgeClass}`}
                          title={isProcessing
                            ? "Can't book tickets while processing a request"
                            : `Book tickets for ${format} showing at ${formatTime(date)}`}
                          disabled={isProcessing}
                          style={isProcessing ? { opacity: 0.7, cursor: 'not-allowed' } : {}}
                        >
                          {formatTime(date)}
                        </button>
                      );
                    })}
                </div>
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

export default TheaterList;
