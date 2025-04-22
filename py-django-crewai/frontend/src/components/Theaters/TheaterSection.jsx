import React, { useMemo } from 'react';
import { useAppContext } from '../../context/AppContext';
import DateSelector from './DateSelector';
import TheaterList from './TheaterList';

function TheaterSection() {
  const {
    firstRunMovies,
    selectedMovieId,
    selectedDateIndex,
    isLoadingTheaters,
    theaterError,
    fetchTheatersForMovie
  } = useAppContext();

  // Find selected movie
  const selectedMovie = useMemo(() => {
    if (!selectedMovieId) return null;
    return firstRunMovies.find(movie => movie.id === selectedMovieId);
  }, [firstRunMovies, selectedMovieId]);

  // If no movie is selected or not in first run mode
  if (!selectedMovie) {
    return (
      <div className="content-wrapper">
        <div className="section-header d-flex justify-content-between align-items-center">
          <h4 className="mb-0">Nearby Theaters</h4>
        </div>
        <div className="theaters-outer-wrapper">
          <div className="text-center text-muted mt-4 py-4">
            <i className="bi bi-film me-2 fs-4"></i>
            <p className="mt-3">Select a movie above to see available theaters and showtimes</p>
          </div>
        </div>
      </div>
    );
  }

  // If loading theaters
  if (isLoadingTheaters) {
    return (
      <div className="content-wrapper">
        <div className="section-header d-flex justify-content-between align-items-center">
          <h4 className="mb-0">Nearby Theaters</h4>
          <span className="text-muted small">Loading theaters...</span>
        </div>
        <div className="theaters-outer-wrapper">
          <div className="text-center py-4">
            <div className="spinner-border text-danger" role="status">
              <span className="visually-hidden">Loading...</span>
            </div>
            <p className="mt-3 text-muted">Finding theaters showing "{selectedMovie.title}"</p>
          </div>
        </div>
      </div>
    );
  }

  // If there was an error loading theaters
  if (theaterError) {
    return (
      <div className="content-wrapper">
        <div className="section-header d-flex justify-content-between align-items-center">
          <h4 className="mb-0">Nearby Theaters</h4>
          <button
            className="btn btn-sm btn-outline-danger"
            onClick={() => fetchTheatersForMovie(selectedMovieId)}
          >
            <i className="bi bi-arrow-repeat me-1"></i>
            Retry
          </button>
        </div>
        <div className="theaters-outer-wrapper">
          <div className="alert alert-danger mt-3" role="alert">
            <i className="bi bi-exclamation-triangle-fill me-2"></i>
            {theaterError}
          </div>
        </div>
      </div>
    );
  }

  // If movie has no theaters
  if (!selectedMovie.theaters || selectedMovie.theaters.length === 0) {
    return (
      <div className="content-wrapper">
        <div className="section-header d-flex justify-content-between align-items-center">
          <h4 className="mb-0">Nearby Theaters</h4>
        </div>
        <div className="theaters-outer-wrapper">
          <div className="text-center text-muted mt-4 py-4">
            <i className="bi bi-calendar-x me-2 fs-4"></i>
            <p className="mt-3">
              No theaters are currently showing "{selectedMovie.title}".
              <br />Please check back later or select a different movie.
            </p>
          </div>
        </div>
      </div>
    );
  }

  // Get today's date and the selected date
  const today = new Date();
  const selectedDate = new Date(today);
  selectedDate.setDate(today.getDate() + selectedDateIndex);
  const selectedDateStr = selectedDate.toISOString().split('T')[0];

  // Find theaters with showtimes for the selected date
  const theatersForDate = useMemo(() => {
    if (!selectedMovie.theaters) return [];

    return selectedMovie.theaters
      .map(theater => {
        // Filter showtimes for the selected date
        const showtimesForDate = theater.showtimes.filter(showtime => {
          if (!showtime.start_time) return false;
          const date = new Date(showtime.start_time);
          return date.toISOString().split('T')[0] === selectedDateStr;
        });

        if (showtimesForDate.length === 0) return null;

        // Group showtimes by format
        const showtimesByFormat = {};
        showtimesForDate.forEach(showtime => {
          const format = showtime.format || 'Standard';
          if (!showtimesByFormat[format]) {
            showtimesByFormat[format] = [];
          }
          showtimesByFormat[format].push(showtime.start_time);
        });

        return {
          ...theater,
          showtimesByFormat
        };
      })
      .filter(Boolean) // Remove null theaters
      .sort((a, b) => (a.distance_miles || 0) - (b.distance_miles || 0));
  }, [selectedMovie, selectedDateStr]);

  return (
    <div className="content-wrapper">
      <div className="section-header d-flex justify-content-between align-items-center">
        <h4 className="mb-0">Nearby Theaters</h4>
        {selectedMovie?.theaters?.length > 0 && (
          <span className="text-muted small">
            {selectedMovie.theaters.length} theater{selectedMovie.theaters.length !== 1 ? 's' : ''} found
          </span>
        )}
      </div>

      <div className="theaters-outer-wrapper">
        <DateSelector />

        {theatersForDate.length === 0 ? (
          <div className="text-center text-muted mt-4 py-4">
            <i className="bi bi-calendar-x me-2 fs-4"></i>
            <p className="mt-3">
              No showtimes available for "{selectedMovie.title}"
              <br />on {selectedDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}.
            </p>
          </div>
        ) : (
          <TheaterList theaters={theatersForDate} />
        )}
      </div>
    </div>
  );
}

export default TheaterSection;
