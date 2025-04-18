/**
 * TMDB-inspired movie grid for displaying movie recommendations
 */

// Enhanced movie grid rendering function
window.renderTMDBGrid = function(movies, containerId, isFirstRunMode = true) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Clear container but don't add an extra title (the HTML already has h4 headers)
    container.innerHTML = '';

    // If no movies to display
    if (!movies || movies.length === 0) {
        container.innerHTML += '<p class="text-muted">No movies match your criteria. Try another search.</p>';
        return;
    }

    // Debug info
    console.log(`Rendering ${movies.length} movies to ${containerId}`);

    // Create a horizontal scrollable slider container
    const movieContainer = document.createElement('div');
    movieContainer.className = 'movie-container';

    const movieSlider = document.createElement('div');
    movieSlider.className = 'movie-slider';

    // Create a card for each movie
    movies.forEach(movie => {
        // Format the release year
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
        // Check for enhanced poster URLs first
        let posterUrl = '';

        // If we have poster URLs at different sizes, use the appropriate one
        if (movie.poster_urls && movie.poster_urls.large) {
            posterUrl = movie.poster_urls.large;
        } else if (movie.poster_urls && movie.poster_urls.original) {
            posterUrl = movie.poster_urls.original;
        } else if (movie.backdrop_url) {
            // Use backdrop as fallback
            posterUrl = movie.backdrop_url;
        } else {
            // Use standard poster URL or placeholder
            posterUrl = movie.poster_url || 'https://via.placeholder.com/300x450?text=No+Poster';
        }

        // Create the movie card with data attributes and click handler
        const movieCard = document.createElement('div');
        movieCard.className = 'movie-card';
        movieCard.setAttribute('data-movie-id', movie.id || movie.tmdb_id || '');
        movieCard.setAttribute('data-movie-title', movie.title || '');
        movieCard.onclick = function() {
            window.handleMovieClick(movie.id || movie.tmdb_id || '', movie.title || '');
        };

        // Create a simple div wrapper for the poster
        const posterWrapper = document.createElement('div');

        // Add title as tooltip
        posterWrapper.title = movie.title;

        // Add the poster image with error handling
        const posterImg = document.createElement('img');
        posterImg.src = posterUrl;
        posterImg.alt = `${movie.title} poster`;
        posterImg.loading = 'lazy'; // Lazy load images

        // Add error handler to use a placeholder if image fails to load
        posterImg.onerror = function() {
            console.error(`Failed to load image for ${movie.title}`, posterUrl);
            this.src = 'https://via.placeholder.com/300x450?text=No+Poster';
        };

        // Add the image to the wrapper
        posterWrapper.appendChild(posterImg);

        // Add the wrapper to the card
        movieCard.appendChild(posterWrapper);

        // Log image URL for debugging
        console.log(`Movie: ${movie.title}, Image URL: ${posterUrl}`);

        // Add movie info section
        const infoDiv = document.createElement('div');
        infoDiv.className = 'movie-info';

        // Add title
        const titleDiv = document.createElement('div');
        titleDiv.className = 'movie-title';
        // Add year to title if available
        titleDiv.textContent = releaseYear ? `${movie.title} (${releaseYear})` : movie.title;
        infoDiv.appendChild(titleDiv);

        // Add rating stars if available
        if (movie.rating) {
            const ratingDiv = document.createElement('div');
            ratingDiv.className = 'movie-rating';

            // Calculate rating stars (0-10 scale to 0-5 scale)
            const ratingStars = Math.round(movie.rating / 2);
            ratingDiv.innerHTML = '★'.repeat(ratingStars) + '☆'.repeat(5 - ratingStars);
            infoDiv.appendChild(ratingDiv);
        }

        // Add movie explanation or description (full text, no truncation)
        if (movie.explanation || movie.overview) {
            const descDiv = document.createElement('div');
            descDiv.className = 'movie-overview';

            // Get the description text, preferring explanation over overview
            let descriptionText = movie.explanation || movie.overview || '';

            // Use the full description without truncation
            descDiv.textContent = descriptionText;
            infoDiv.appendChild(descDiv);
        }

        // Add info div to card
        movieCard.appendChild(infoDiv);

        // Add the card to the slider
        movieSlider.appendChild(movieCard);
    });

    // Add the slider to the movie container
    movieContainer.appendChild(movieSlider);

    // Add the movie container to the main container
    container.appendChild(movieContainer);
};

// Helper function to process movie posters - no API calls needed as our backend already handles this
window.enhanceMoviePosters = function(movies, callback) {
    // Validate input
    if (!movies || !Array.isArray(movies) || movies.length === 0) {
        console.warn("No movies to enhance posters for");
        callback(movies || []);
        return;
    }

    // Create a deep copy to avoid modifying the original
    let enhancedMovies;
    try {
        enhancedMovies = JSON.parse(JSON.stringify(movies));
        console.log(`Successfully cloned ${enhancedMovies.length} movies for poster enhancement`);
    } catch (e) {
        console.error("Error cloning movies:", e);
        callback(movies); // Use original if clone fails
        return;
    }

    console.log("Processing movie posters for:", enhancedMovies.map(m => m.title).join(', '));

    // Process each movie to ensure it has proper poster and ID formats
    enhancedMovies.forEach((movie, index) => {
        // Verify movie object integrity
        if (!movie) {
            console.error(`Movie at index ${index} is undefined or null`);
            return;
        }

        console.log(`Processing movie: ${movie.title || 'Unknown'}, ID: ${movie.id || movie.tmdb_id || 'None'}`);
        // Make sure both id and tmdb_id are set for compatibility
        if (movie.id && !movie.tmdb_id) {
            movie.tmdb_id = movie.id;
            console.log(`Set tmdb_id from id for movie: ${movie.title}`);
        } else if (movie.tmdb_id && !movie.id) {
            movie.id = movie.tmdb_id;
            console.log(`Set id from tmdb_id for movie: ${movie.title}`);
        }

        // If poster_url is missing but we have poster_urls, use one of those
        if (!movie.poster_url && movie.poster_urls) {
            if (movie.poster_urls.original) {
                movie.poster_url = movie.poster_urls.original;
            } else if (movie.poster_urls.large) {
                movie.poster_url = movie.poster_urls.large;
            } else if (movie.poster_urls.medium) {
                movie.poster_url = movie.poster_urls.medium;
            }
            console.log(`Used alternative poster_url for movie: ${movie.title}`);
        }

        // If poster URL contains 'w500', upgrade to original resolution
        if (movie.poster_url && movie.poster_url.includes('/w500/')) {
            movie.poster_url = movie.poster_url.replace('/w500/', '/original/');
            console.log(`Upgraded poster quality for movie: ${movie.title}`);
        }

        // If no poster_url exists, but we have a backdrop_url, use that instead
        if (!movie.poster_url && movie.backdrop_url) {
            movie.poster_url = movie.backdrop_url;
            console.log(`Used backdrop as poster for movie: ${movie.title}`);
        }

        // Ensure we have a valid poster URL for the UI
        if (!movie.poster_url) {
            movie.poster_url = 'https://via.placeholder.com/300x450?text=No+Poster';
            console.log(`Using placeholder image for movie: ${movie.title}`);
        }
    });

    // Call the callback immediately with our processed movies
    setTimeout(() => {
        console.log("Enhanced posters processing completed");
        callback(enhancedMovies);
    }, 0);
};
