/**
 * Debug utility for TMDB IDs
 */

// Add this to the head of your HTML document
// <script src="/static/js/debug-tmdb.js"></script>

// Store the original renderTMDBGrid function
const originalRenderTMDBGrid = window.renderTMDBGrid;

// Override the function to include debugging
window.renderTMDBGrid = function(movies, containerId, isFirstRunMode = true) {
    // Log detailed movie information for debugging
    console.log('=============== TMDB DEBUG ===============');
    console.log(`Rendering ${movies.length} movies to ${containerId}`);

    // Log each movie's ID and URL
    movies.forEach((movie, index) => {
        console.log(`Movie ${index + 1}: ${movie.title}`);
        console.log(`  - TMDB ID: ${movie.tmdb_id}`);
        console.log(`  - ID: ${movie.id}`);
        console.log(`  - TMDB URL: ${movie.tmdb_url || 'No URL'}`);
        console.log(`  - Poster URL: ${movie.poster_url || 'No poster'}`);
    });
    console.log('=========================================');

    // Call the original function
    return originalRenderTMDBGrid(movies, containerId, isFirstRunMode);
};

// Override enhanceMoviePosters to fix missing IDs
const originalEnhanceMoviePosters = window.enhanceMoviePosters;

window.enhanceMoviePosters = function(movies, callback) {
    console.log('Enhancing movie posters with ID verification');

    // Ensure every movie has valid IDs before enhancement
    movies.forEach((movie, index) => {
        // If we have an ID but no TMDB ID, use ID as TMDB ID
        if (movie.id && !movie.tmdb_id) {
            console.log(`[DEBUG] Setting tmdb_id from id for ${movie.title}`);
            movie.tmdb_id = movie.id;
        }

        // If we have TMDB ID but no ID, use TMDB ID as ID
        if (movie.tmdb_id && !movie.id) {
            console.log(`[DEBUG] Setting id from tmdb_id for ${movie.title}`);
            movie.id = movie.tmdb_id;
        }

        // Add TMDB URL if missing
        if (movie.tmdb_id && !movie.tmdb_url) {
            movie.tmdb_url = `https://www.themoviedb.org/movie/${movie.tmdb_id}`;
            console.log(`[DEBUG] Added TMDB URL for ${movie.title}: ${movie.tmdb_url}`);
        }

        // Verify the TMDB ID looks numeric - if not, log an error
        if (movie.tmdb_id && isNaN(movie.tmdb_id)) {
            console.error(`[ERROR] Invalid TMDB ID for ${movie.title}: ${movie.tmdb_id}`);
        }
    });

    // Call original function
    return originalEnhanceMoviePosters(movies, callback);
};
