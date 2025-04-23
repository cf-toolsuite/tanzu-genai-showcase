/**
 * MovieManager Module
 * Responsible for managing movie data and selection state
 * Uses module pattern to encapsulate state and provide a clean interface
 */
const MovieManager = (function() {
    // Private state
    const _movies = new Map(); // Store movies by ID
    let _selectedMovieId = null;
    let _selectedDateIndex = null;

    return {
        // Add or update movies in the collection
        setMovies: function(movieArray) {
            // Clear existing movies
            _movies.clear();
            _selectedMovieId = null;

            // Process and store each movie with standardized IDs
            movieArray.forEach(movie => {
                // Ensure IDs are strings and consistent
                const movieId = String(movie.id || movie.tmdb_id || '');
                if (movieId) {
                    // Create a new movie object with standardized properties
                    const standardizedMovie = {
                        ...movie,
                        id: movieId,
                        tmdb_id: movieId
                    };

                    // Store in our map
                    _movies.set(movieId, standardizedMovie);
                }
            });

            console.log(`MovieManager: Processed ${_movies.size} movies`);
            return Array.from(_movies.values());
        },

        // Retrieve a movie by ID (reliable lookup)
        getMovie: function(movieId) {
            if (!movieId) return null;
            const stringId = String(movieId);
            return _movies.get(stringId) || null;
        },

        // Get all movies
        getAllMovies: function() {
            return Array.from(_movies.values());
        },

        // Select a movie by ID
        selectMovie: function(movieId) {
            const stringId = String(movieId);
            if (_movies.has(stringId)) {
                _selectedMovieId = stringId;
                console.log(`MovieManager: Selected movie with ID ${stringId}`);
                return true;
            }
            console.warn(`MovieManager: Failed to select movie with ID ${stringId} - not found`);
            return false;
        },

        // Get the currently selected movie
        getSelectedMovie: function() {
            return _selectedMovieId ? _movies.get(_selectedMovieId) : null;
        },

        // Get the selected movie ID
        getSelectedMovieId: function() {
            return _selectedMovieId;
        },

        // Get the selected movie title
        getSelectedMovieTitle: function() {
            const movie = this.getSelectedMovie();
            return movie ? movie.title : null;
        },

        // Check if a movie with the given ID exists
        hasMovie: function(movieId) {
            return _movies.has(String(movieId));
        },

        // Debug function to log all stored movies
        debugMovies: function() {
            console.log("MovieManager: All stored movies");
            _movies.forEach((movie, id) => {
                console.log(`- ${movie.title} (ID: ${id})`);
            });
        },

        // Date selection methods
        selectDate: function(dateIndex) {
            _selectedDateIndex = dateIndex;
            console.log(`MovieManager: Selected date index ${dateIndex}`);
            return true;
        },

        getSelectedDateIndex: function() {
            return _selectedDateIndex;
        },

        // Reset methods
        resetDateSelection: function() {
            _selectedDateIndex = null;
            console.log("MovieManager: Date selection reset");
        },

        // Reset all selections
        resetAll: function() {
            _selectedMovieId = null;
            _selectedDateIndex = null;
            console.log("MovieManager: All selections reset");
        },

        // Validation methods for showtimes
        hasShowtimesForDate: function(dateIndex) {
            const movie = this.getSelectedMovie();
            if (!movie || !movie.theaters) return false;

            // Generate the date string for comparison
            const today = new Date();
            const targetDate = new Date(today);
            targetDate.setDate(today.getDate() + dateIndex);
            const targetDateStr = targetDate.toISOString().split('T')[0];

            // Check if any theater has showtimes for this date
            return movie.theaters.some(theater => {
                if (!theater.showtimes || !Array.isArray(theater.showtimes)) return false;
                return theater.showtimes.some(showtime => {
                    if (!showtime.start_time) return false;
                    const date = new Date(showtime.start_time);
                    return date.toISOString().split('T')[0] === targetDateStr;
                });
            });
        },

        // Check if movie has any showtimes across all dates (next 4 days)
        hasAnyShowtimes: function() {
            // Check for the next 4 days
            for (let i = 0; i < 4; i++) {
                if (this.hasShowtimesForDate(i)) return true;
            }
            return false;
        }
    };
})();
