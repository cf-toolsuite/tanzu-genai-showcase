import React from 'react';

function MessageList({ messages }) {
  // Format message text with special handling for bot messages
  const formatMessageText = (text) => {
    // If content is not a string (e.g., a React element), return it as is
    if (typeof text !== 'string') {
      return text;
    }
    
    // Replace ** with bold tags
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Replace URLs with clickable links
    text = text.replace(
      /(https?:\/\/[^\s]+)/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    );

    // Replace newlines with line breaks
    text = text.replace(/\n/g, '<br />');

    return text;
  };

  // Format time to 12-hour format
  const formatTime = (isoString) => {
    try {
      const date = new Date(isoString);
      return date.toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit'
      });
    } catch (e) {
      console.error('Error formatting time:', e);
      return '';
    }
  };

  return (
    <>
      {messages.map((message, index) => (
        <React.Fragment key={index}>
          <div className={`message ${message.sender}`}>
            {message.sender === 'bot' ? (
              typeof message.content === 'string' ? (
                <div dangerouslySetInnerHTML={{
                  __html: formatMessageText(message.content)
                }} />
              ) : (
                message.content
              )
            ) : (
              message.content
            )}
          </div>
          <span className={`message-time ${message.sender === 'user' ? 'text-end' : ''}`}>
            {formatTime(message.created_at)}
          </span>
        </React.Fragment>
      ))}
    </>
  );
}

export default MessageList;
