import React, { forwardRef } from 'react';

const InputArea = forwardRef(({
  value = '',
  onChange,
  onSend,
  disabled = false,
  placeholder = 'Type your message...',
  sendButtonRef,
  id = 'userInput',
  sendButtonId = 'sendButton'
}, ref) => {

  const handleInputChange = (e) => {
    onChange(e.target.value);
  };

  const handleSendClick = () => {
    // Ensure value is a string and then trim it
    const stringValue = typeof value === 'string' ? value : '';
    const trimmedInput = stringValue.trim();

    // Check if the trimmed input is not empty and not currently disabled
    if (trimmedInput && !disabled) {
      // Send the trimmed input
      onSend(trimmedInput);
    }
  };

  const handleKeyPress = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault(); // Prevent newline on Enter
      handleSendClick();
    }
  };

  // Helper function to safely check if the button should be disabled
  const isButtonDisabled = () => {
    try {
      // First make sure value is a string
      const stringValue = typeof value === 'string' ? value : '';
      // Then check if it's empty after trimming
      return disabled || !stringValue.trim();
    } catch (e) {
      // If any error occurs, default to disabled state for safety
      console.error("Error checking button disabled state:", e);
      return true;
    }
  };

  return (
    <div className="input-area">
      <div className="input-group">
        <textarea
          ref={ref}
          id={id}
          className="form-control"
          rows="1"
          placeholder={placeholder}
          value={value || ''} // Ensure value is never undefined
          onChange={handleInputChange}
          onKeyDown={handleKeyPress}
          disabled={disabled}
        />
        <button
          ref={sendButtonRef}
          id={sendButtonId}
          className="btn btn-red-carpet" // Updated to use the custom red carpet styling
          onClick={handleSendClick}
          disabled={isButtonDisabled()}
        >
          {disabled ? (
            <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          ) : (
            <i className="bi bi-send"></i>
          )}
        </button>
      </div>
    </div>
  );
});

export default InputArea;
