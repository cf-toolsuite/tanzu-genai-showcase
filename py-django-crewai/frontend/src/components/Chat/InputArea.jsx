import React, { forwardRef, useRef } from 'react';
import { useAppContext } from '../../context/AppContext';
import SampleInquiries from './SampleInquiries';
import { useLocation } from '../../hooks/useLocation';
import ProgressIndicator from './ProgressIndicator';

const InputArea = forwardRef(({
  value,
  onChange,
  onSend,
  disabled,
  placeholder,
  sendButtonRef,
  id,
  sendButtonId
}, ref) => {
  const { location, setLocation, isLoadingLocation } = useAppContext();
  const locationInputRef = useRef(null);
  const detectLocation = useLocation();

  // Clean and validate input
  const validateAndCleanInput = (input) => {
    if (typeof input !== 'string') {
      console.warn('Invalid input type:', typeof input);
      return '';
    }
    return input.trim();
  };

  // Handle input key press (Enter to send)
  const handleKeyDown = (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      const cleanedValue = validateAndCleanInput(value);
      if (cleanedValue) {
        console.log('Sending message (Enter):', cleanedValue);
        onSend(cleanedValue);
      }
    }
  };

  const handleSendClick = () => {
    const cleanedValue = validateAndCleanInput(value);
    if (cleanedValue) {
      console.log('Sending message (Click):', cleanedValue);
      onSend(cleanedValue);
    }
  };

  // Handle sample question click
  const handleQuestionClick = (query) => {
    const cleanedQuery = validateAndCleanInput(query);
    if (cleanedQuery) {
      console.log('Sending sample query:', cleanedQuery);
      onSend(cleanedQuery);
    }
  };

  // Handle location icon click
  const handleLocationIconClick = () => {
    // Only do something if there's no location value
    if (!location.trim()) {
      detectLocation();
    }
  };

  return (
    <>
      <div className="input-group">
        <input
          ref={ref}
          id={id}
          type="text"
          className="form-control"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          autoComplete="off"
        />
        <button
          ref={sendButtonRef}
          id={sendButtonId}
          className="btn btn-red-carpet"
          onClick={handleSendClick}
          disabled={disabled}
        >
          <i className="bi bi-send-fill"></i>
        </button>
      </div>

      <div className="mt-3 d-flex justify-content-between align-items-center">
        <SampleInquiries
          isFirstRun={id === 'userInput'}
          onQuestionClick={handleQuestionClick}
        />
        {id === 'userInput' && (
          <div className="location-container ms-3">
            {isLoadingLocation && (
              <div className="location-loading-container mb-2">
                <ProgressIndicator
                  progress={50}
                  message="Detecting your location..."
                />
              </div>
            )}
            <div className="input-group location-input-group">
              <span
                className={`input-group-text ${location.trim() ? 'location-icon-active' : 'location-icon-clickable'}`}
                onClick={!location.trim() && !isLoadingLocation ? handleLocationIconClick : undefined}
                style={{ cursor: !location.trim() && !isLoadingLocation ? 'pointer' : 'default' }}
                title={!location.trim() ? (isLoadingLocation ? 'Detecting location...' : 'Obtain current location') : ''}
              >
                {isLoadingLocation ? (
                  <i className="bi bi-arrow-repeat spin"></i>
                ) : (
                  <i className="bi bi-geo-alt-fill"></i>
                )}
              </span>
              <input
                ref={locationInputRef}
                type="text"
                id="locationInput"
                className="form-control"
                value={location}
                onChange={(e) => setLocation(e.target.value)}
                placeholder={isLoadingLocation ? "Detecting location..." : "Location..."}
                disabled={disabled || isLoadingLocation}
                autoComplete="off"
              />
            </div>
          </div>
        )}
      </div>
    </>
  );
});

export default InputArea;
