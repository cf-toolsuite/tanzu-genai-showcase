import React, { useRef, useEffect } from 'react';
import { useAppContext } from '../../context/AppContext';
import MovieCard from './MovieCard';

function MovieGrid({ movies, isFirstRun }) {
  const { selectedMovieId, selectMovie } = useAppContext();
  const gridRef = useRef(null);

  if (!movies || movies.length === 0) {
    return (
      <div className="p-3">
        <p className="text-muted">No movies match your criteria. Try another search.</p>
      </div>
    );
  }

  return (
    <div className="movie-grid-container position-relative">
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
    </div>
  );
}

export default MovieGrid;
