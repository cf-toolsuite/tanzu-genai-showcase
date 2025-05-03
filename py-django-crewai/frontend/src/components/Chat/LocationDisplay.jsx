import React from 'react';
import { useAppContext } from '../../context/AppContext';

function LocationDisplay({ disabled }) {
  const { location, isLoadingLocation, activeTab } = useAppContext();

  // Only show for First Run mode
  if (activeTab !== 'first-run') {
    return null;
  }

  return (
    <div className="location-container">
      <div className="input-group location-input-group" style={disabled ? { opacity: 0.7 } : {}}>
        <span className="input-group-text">
          <i className={`bi bi-geo-alt${isLoadingLocation ? ' spin' : ' location-icon-active'}`}></i>
        </span>
        <input
          type="text"
          className="form-control"
          value={isLoadingLocation ? 'Detecting your location...' : (location || 'Location not available')}
          disabled
        />
      </div>
    </div>
  );
}

export default LocationDisplay;
