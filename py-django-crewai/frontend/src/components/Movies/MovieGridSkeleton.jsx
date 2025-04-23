import React from 'react';

function MovieGridSkeleton() {
  // Create an array of 6 skeleton items
  const skeletonItems = Array.from({ length: 6 }, (_, index) => (
    <div key={index} className="movie-card skeleton">
      <div className="movie-poster skeleton-poster">
        <div className="skeleton-animation"></div>
      </div>
      <div className="movie-info">
        <div className="skeleton-title">
          <div className="skeleton-animation"></div>
        </div>
        <div className="skeleton-rating">
          <div className="skeleton-animation"></div>
        </div>
      </div>
    </div>
  ));

  return (
    <div className="movie-grid-container position-relative">
      <div className="movie-grid">
        {skeletonItems}
      </div>
    </div>
  );
}

export default MovieGridSkeleton;
