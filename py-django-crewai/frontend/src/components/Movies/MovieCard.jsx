import React from 'react';
import { useAppContext } from '../../context/AppContext';

function MovieCard({ movie, isSelected, onClick, isFirstRun }) {
  // Check if the application is in a processing state
  const { checkIsProcessing } = useAppContext();
  const isProcessing = checkIsProcessing();
  // Format the release year if available
  let releaseYear = '';
  if (movie.release_date) {
    try {
      const releaseDate = new Date(movie.release_date);
      releaseYear = releaseDate.getFullYear();
    } catch (e) {
      console.error("Error parsing release date", e);
    }
  }

  // Get the best poster URL available or use a placeholder
  let posterUrl = '';
  if (movie.poster_urls && movie.poster_urls.large) {
    posterUrl = movie.poster_urls.large;
  } else if (movie.poster_urls && movie.poster_urls.original) {
    posterUrl = movie.poster_urls.original;
  } else if (movie.backdrop_url) {
    posterUrl = movie.backdrop_url;
  } else {
    posterUrl = movie.poster_url || 'https://via.placeholder.com/300x450?text=No+Poster';
  }

  // Calculate rating stars (0-10 scale to 0-5 scale)
  const ratingStars = movie.rating ? Math.round(movie.rating / 2) : 0;

  // Get theater count for first run movies
  // If theaters is undefined (not yet fetched), show "Checking theaters..."
  // If theaters is an empty array (fetched but none found), show "No theaters found"
  const theaterCount = isFirstRun && movie.theaters ? movie.theaters.length : 0;
  const theaterStatus = isFirstRun ? (
    movie.theaters === undefined ? 'checking' :
    movie.theaters && movie.theaters.length === 0 ? 'none' :
    'found'
  ) : 'not-applicable';

  return (
    <div
      className={`movie-card ${isSelected ? 'selected' : ''}`}
      data-movie-id={movie.id}
      data-movie-title={movie.title}
      onClick={isProcessing ? undefined : onClick}
      style={isProcessing ? { cursor: 'not-allowed', opacity: isSelected ? 1 : 0.7 } : {}}
      title={isProcessing ? "Can't select movie while processing a request" : movie.title}
    >
      {/* Current Release Badge */}
      {isFirstRun && movie.is_current_release && (
        <div className="current-release-badge">
          <span className="badge bg-danger">
            <i className="bi bi-star-fill me-1"></i>Now Playing
          </span>
        </div>
      )}

      {/* Poster Image */}
      <img
        className="movie-poster"
        src={posterUrl}
        alt={`${movie.title} poster`}
        loading="lazy"
        onError={(e) => {
          e.target.src = 'https://via.placeholder.com/300x450?text=No+Poster';
        }}
      />

      {/* Movie Information Overlay */}
      <div className="movie-info">
        <h5 className="movie-title">
          {releaseYear ? `${movie.title} (${releaseYear})` : movie.title}
        </h5>

        {/* Rating Stars */}
        {movie.rating && (
          <div className="movie-rating text-warning">
            {'★'.repeat(ratingStars) + '☆'.repeat(5 - ratingStars)}
          </div>
        )}

        {/* Theater Count for First Run Movies */}
        {isFirstRun && (
          <div className="mt-2 small text-muted">
            {theaterStatus === 'found' && theaterCount > 0 ? (
              `Available at ${theaterCount} theater${theaterCount === 1 ? '' : 's'}`
            ) : theaterStatus === 'checking' ? (
              <span><i className="bi bi-hourglass-split me-1"></i>Checking theaters...</span>
            ) : (
              <span><i className="bi bi-x-circle me-1"></i>No theaters found nearby</span>
            )}
          </div>
        )}

        {/* Movie Description */}
        {(movie.explanation || movie.overview) && (
          <div className="movie-overview">
            {movie.explanation || movie.overview}
          </div>
        )}
      </div>
    </div>
  );
}

export default MovieCard;
