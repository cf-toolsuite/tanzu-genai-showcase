import React, { forwardRef, useRef } from 'react';
import { useAppContext } from '../../context/AppContext';
import SampleInquiries from './SampleInquiries';
import { useLocation } from '../../hooks/useLocation';

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
  const { location, setLocation } = useAppContext();
  const locationInputRef = useRef(null);
  const detectLocation = useLocation();

  // Handle input key press (Enter to send)
  const handleKeyDown = (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      onSend();
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
          onClick={onSend}
          disabled={disabled}
        >
          <i className="bi bi-send-fill"></i>
        </button>
      </div>

      <div className="mt-3 d-flex justify-content-between align-items-center">
        <SampleInquiries
          isFirstRun={id === 'userInput'}
          onQuestionClick={onSend}
        />
        {id === 'userInput' && (
          <div className="location-container ms-3">
            <div className="input-group location-input-group">
              <span 
                className={`input-group-text ${location.trim() ? 'location-icon-active' : 'location-icon-clickable'}`}
                onClick={!location.trim() ? handleLocationIconClick : undefined}
                style={{ cursor: !location.trim() ? 'pointer' : 'default' }}
                title={!location.trim() ? 'Obtain current location' : ''}
              >
                <i className="bi bi-geo-alt-fill"></i>
              </span>
              <input
                ref={locationInputRef}
                type="text"
                id="locationInput"
                className="form-control"
                value={location}
                onChange={(e) => setLocation(e.target.value)}
                placeholder="Location..."
                disabled={disabled}
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
