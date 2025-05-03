import React, { lazy, Suspense } from 'react';

const ChatInterface = lazy(() => import('./ChatInterface'));

function LazyChatInterface(props) {
  // Ensure we're passing valid props
  const safeProps = {
    ...props,
    // Add any default values if needed
  };

  return (
    <Suspense
      fallback={
        <div className="processing-container">
          <div className="progress mb-2">
            <div
              className="progress-bar progress-bar-striped progress-bar-animated"
              role="progressbar"
              style={{ width: '100%' }}
            />
          </div>
          <div className="text-center text-muted">
            Loading chat interface...
          </div>
        </div>
      }
    >
      <ChatInterface {...safeProps} />
    </Suspense>
  );
}

export default LazyChatInterface;
