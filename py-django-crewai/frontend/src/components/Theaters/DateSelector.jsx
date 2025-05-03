import React from 'react';
import { useAppContext } from '../../context/AppContext';

function DateSelector() {
  const { selectedDateIndex, setSelectedDateIndex, checkIsProcessing } = useAppContext();

  // Check if the application is in a processing state
  const isProcessing = checkIsProcessing();

  // Generate dates for the next 4 days
  const dates = Array.from({ length: 4 }, (_, i) => {
    const date = new Date();
    date.setDate(date.getDate() + i);
    return {
      index: i,
      dayName: i === 0 ? 'Today' : date.toLocaleDateString('en-US', { weekday: 'short' }),
      dateStr: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
      fullDate: date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
    };
  });

  return (
    <div className="date-selector-container">
      <div className="date-selector">
        {dates.map(({ index, dayName, dateStr, fullDate }) => (
          <button
            key={index}
            type="button"
            className={`date-button ${index === selectedDateIndex ? 'active' : ''}`}
            data-date-index={index}
            onClick={() => {
              if (!isProcessing) {
                setSelectedDateIndex(index);
              }
            }}
            disabled={isProcessing}
            style={isProcessing ? { opacity: 0.7, cursor: 'not-allowed' } : {}}
            title={isProcessing ? "Can't change date while processing a request" : fullDate}
          >
            <div className="date-button-content">
              <div className="day-name">{dayName}</div>
              <div className="date-str">{dateStr}</div>
            </div>
            {index === selectedDateIndex && (
              <div className="selected-indicator">
                <i className="bi bi-check2"></i>
              </div>
            )}
          </button>
        ))}
      </div>
    </div>
  );
}

export default DateSelector;
