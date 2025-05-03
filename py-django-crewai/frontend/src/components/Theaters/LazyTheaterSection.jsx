import React, { lazy, Suspense } from 'react';
import { ProgressIndicator } from '../Chat/ProgressIndicator';

const TheaterSection = lazy(() => import('./TheaterSection'));

function LazyTheaterSection(props) {
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
            Loading theaters...
          </div>
        </div>
      }
    >
      <TheaterSection {...props} />
    </Suspense>
  );
}

export default LazyTheaterSection;
