import React, { useRef, useState, useEffect } from 'react';
import { useAppContext } from '../../context/AppContext';
import MovieCard from './MovieCard';

function MovieGrid({ movies, isFirstRun }) {
  const { selectedMovieId, selectMovie } = useAppContext();
  const gridRef = useRef(null);
  const [showLeftArrow, setShowLeftArrow] = useState(false);
  const [showRightArrow, setShowRightArrow] = useState(true);

  // Function to check if arrows should be shown
  const checkScrollPosition = () => {
    if (!gridRef.current) return;

    const { scrollLeft, scrollWidth, clientWidth } = gridRef.current;
    const isAtStart = scrollLeft <= 0;
    const isAtEnd = Math.abs(scrollLeft + clientWidth - scrollWidth) < 10;

    // Show left arrow only if we're not at the start
    setShowLeftArrow(!isAtStart);
    // Show right arrow only if we're not at the end
    setShowRightArrow(!isAtEnd);
  };

  // Set up scroll event listener
  useEffect(() => {
    const gridElement = gridRef.current;
    if (gridElement) {
      gridElement.addEventListener('scroll', checkScrollPosition);
      // Initial check
      checkScrollPosition();
      return () => gridElement.removeEventListener('scroll', checkScrollPosition);
    }
  }, []);

  // When movies change, check again
  useEffect(() => {
    checkScrollPosition();
  }, [movies]);

  // Scroll functions
  const scrollLeft = () => {
    if (gridRef.current) {
      gridRef.current.scrollBy({ left: -360, behavior: 'smooth' });
    }
  };

  const scrollRight = () => {
    if (gridRef.current) {
      gridRef.current.scrollBy({ left: 360, behavior: 'smooth' });
    }
  };

  if (!movies || movies.length === 0) {
    return (
      <div className="p-3">
        <p className="text-muted">No movies match your criteria. Try another search.</p>
      </div>
    );
  }

  return (
    <div className="movie-grid-container position-relative">
      {showLeftArrow && (
        <button
          className="scroll-arrow scroll-left"
          onClick={scrollLeft}
          aria-label="Scroll left"
        >
          <i className="bi bi-chevron-left"></i>
        </button>
      )}

      <div className="movie-grid" ref={gridRef}>
        {movies.map(movie => (
          <MovieCard
            key={movie.id}
            movie={movie}
            isSelected={movie.id === selectedMovieId}
            onClick={() => selectMovie(movie.id)}
            isFirstRun={isFirstRun}
          />
        ))}
      </div>

      {showRightArrow && (
        <button
          className="scroll-arrow scroll-right"
          onClick={scrollRight}
          aria-label="Scroll right"
        >
          <i className="bi bi-chevron-right"></i>
        </button>
      )}
    </div>
  );
}

export default MovieGrid;
