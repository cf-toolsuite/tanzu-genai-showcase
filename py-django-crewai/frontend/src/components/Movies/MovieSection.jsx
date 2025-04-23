import React, { Suspense, useCallback } from 'react';
import { useAppContext } from '../../context/AppContext';
import LazyMovieGrid from './LazyMovieGrid';
import MovieGridSkeleton from './MovieGridSkeleton';
import GenreSelector from './GenreSelector';
import ProgressIndicator from '../Chat/ProgressIndicator';

function MovieSection({ isFirstRun }) {
  const {
    firstRunMovies,
    casualMovies,
    loading,
    progress,
    requestStage
  } = useAppContext();

  const movies = isFirstRun ? firstRunMovies : casualMovies;

  // Show loading skeleton when initially loading movies
  const isLoading = loading && (requestStage === 'searching' || requestStage === 'analyzing');

  // Show progress indicator when loading but only in certain stages
  const showProgress = loading && ['searching', 'analyzing', 'theaters'].includes(requestStage);

  return (
    <div className="content-wrapper">
      <div className="section-header d-flex justify-content-between align-items-center mb-2">
        <h4 className="mb-0 ps-0">
          {isFirstRun ? 'Now Playing' : 'Recommended Movies'}
        </h4>

        {isFirstRun && movies.length > 0 && (
          <span className="text-muted small">
            Click a movie to see showtimes
          </span>
        )}
      </div>

      <div className="movie-section">
        {showProgress && (
          <ProgressIndicator
            progress={progress}
            message={isFirstRun
              ? requestStage === 'searching'
                ? "Searching for movies in theaters..."
                : requestStage === 'analyzing'
                  ? "Analyzing movie options and preferences..."
                  : "Finding theaters near you..."
              : requestStage === 'searching'
                ? "Finding movie recommendations for you..."
                : "Preparing your personalized recommendations..."}
          />
        )}

        {isLoading ? (
          <MovieGridSkeleton />
        ) : (
          <Suspense fallback={<MovieGridSkeleton />}>
            <LazyMovieGrid
              movies={movies}
              isFirstRun={isFirstRun}
            />
          </Suspense>
        )}

        {/* Genre selection for Casual Viewing mode */}
        {!isFirstRun && !isLoading && (
          <div className="mt-3">
            <GenreSelector
              onSubgenreClick={useCallback((query) => {
                // Create a custom event that the ChatInterface will listen for
                const event = new CustomEvent('subgenreQuery', {
                  detail: { query },
                  bubbles: true
                });
                document.dispatchEvent(event);
              }, [])}
            />
          </div>
        )}
      </div>
    </div>
  );
}

export default MovieSection;
