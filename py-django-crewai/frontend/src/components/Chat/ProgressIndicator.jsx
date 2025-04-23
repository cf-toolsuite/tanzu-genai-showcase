import React from 'react';

function ProgressIndicator({ progress, message }) {
  return (
    <div className="processing-container">
      <div className="progress mb-2">
        <div
          className="progress-bar progress-bar-striped progress-bar-animated"
          role="progressbar"
          style={{ width: `${progress}%` }}
          aria-valuenow={progress}
          aria-valuemin="0"
          aria-valuemax="100"
        />
      </div>
      <div className="text-center text-muted mb-1">
        {message}
      </div>
      <div className="text-center text-muted small">
        <i className="bi bi-hourglass-split me-1"></i>
        {progress < 100 ? 'Processing your request...' : 'Almost done!'}
      </div>
    </div>
  );
}

export default ProgressIndicator;
