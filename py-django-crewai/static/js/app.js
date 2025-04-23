// Main application JavaScript for Movie Chatbot

document.addEventListener('DOMContentLoaded', function() {
    // Define the API URL
    const SEND_MESSAGE_URL = '/send-message/';

    // Main chat elements (First Run)
    const chatContainer = document.getElementById('chatContainer');
    const userInput = document.getElementById('userInput');
    const sendButton = document.getElementById('sendButton');
    const processingContainer = document.getElementById('processingContainer');
    const progressBar = document.getElementById('progressBar');
    const processingMessage = document.getElementById('processingMessage');
    const locationInput = document.getElementById('locationInput');

    // Theater elements
    const dateTabs = document.getElementById('dateTabs');
    const theatersContainer = document.getElementById('theatersContainer');
    const showtimesSection = document.getElementById('showtimesSection');

    // Initially hide date tabs until a movie is selected
    if (dateTabs) {
        dateTabs.style.display = 'none';
    }

    // Global variables
    window.userTimezone = null; // Store user timezone

    // Casual viewing elements
    const casualChatContainer = document.getElementById('casualChatContainer');
    const casualUserInput = document.getElementById('casualUserInput');
    const casualSendButton = document.getElementById('casualSendButton');
    const casualProcessingContainer = document.getElementById('casualProcessingContainer');
    const casualProgressBar = document.getElementById('casualProgressBar');
    const casualProcessingMessage = document.getElementById('casualProcessingMessage');

    // Tab elements
    const firstRunTab = document.getElementById('first-run-tab');
    const casualViewingTab = document.getElementById('casual-viewing-tab');

    // Genre select dropdown
    const genreSelect = document.getElementById('genreSelect');
    if (genreSelect) {
        genreSelect.addEventListener('change', function() {
            console.log('Genre select changed to:', this.value);
            switchGenreTab(this.value);
        });
    }

    // Set active tab based on localStorage if available
    const activeTab = localStorage.getItem('activeTab');
    if (activeTab === 'casual') {
        const casualTab = new bootstrap.Tab(casualViewingTab);
        casualTab.show();
        // Hide location field when in Casual Viewing mode
        document.querySelector('.location-input-group').style.display = 'none';
    } else {
        // Ensure location field is visible when in First Run mode (default)
        document.querySelector('.location-input-group').style.display = 'flex';
    }

    // Save active tab to localStorage and toggle location field visibility
    firstRunTab.addEventListener('shown.bs.tab', function() {
        localStorage.setItem('activeTab', 'first-run');
        // Show location field when in First Run mode
        document.querySelector('.location-input-group').style.display = 'flex';
    });

    casualViewingTab.addEventListener('shown.bs.tab', function() {
        localStorage.setItem('activeTab', 'casual');
        // Hide location field when in Casual Viewing mode
        document.querySelector('.location-input-group').style.display = 'none';
    });

    // Initial scroll to bottom of chat containers
    chatContainer.scrollTop = chatContainer.scrollHeight;
    casualChatContainer.scrollTop = casualChatContainer.scrollHeight;

    // Handle sending messages - each tab has its own conversation
    window.sendMessage = function(isFirstRun = true) {
        // Get elements based on active mode
        const activeInput = isFirstRun ? userInput : casualUserInput;
        const activeButton = isFirstRun ? sendButton : casualSendButton;
        const activeContainer = isFirstRun ? chatContainer : casualChatContainer;
        const activeProcessingContainer = isFirstRun ? processingContainer : casualProcessingContainer;
        const activeProgressBar = isFirstRun ? progressBar : casualProgressBar;
        const activeProcessingMessage = isFirstRun ? processingMessage : casualProcessingMessage;

        const message = activeInput.value.trim();

        // Only use location for First Run mode
        const location = isFirstRun ? document.getElementById('locationInput').value.trim() : "";

        if (message) {
            // Disable input and button while processing
            activeInput.disabled = true;
            activeButton.disabled = true;

            if (isFirstRun) {
                locationInput.disabled = true;
            }

            // Add user message to chat
            appendMessage(message, 'user', isFirstRun);

            // Clear input
            activeInput.value = '';

            // Show processing indicator with progress bar
            activeProcessingContainer.style.display = 'block';
            activeProgressBar.style.width = '0%';
            activeProcessingMessage.textContent = 'Processing your request...';

            // Start progress animation
            let progress = 0;
            const progressInterval = setInterval(() => {
                // Increment slowly up to 90% (we'll jump to 100% when done)
                if (progress < 90) {
                    progress += 5;
                    activeProgressBar.style.width = `${progress}%`;
                    activeProgressBar.setAttribute('aria-valuenow', progress);

                    // Update message based on progress
                    if (progress < 30) {
                        activeProcessingMessage.textContent = 'Looking for movies matching your request...';
                    } else if (progress < 60) {
                        activeProcessingMessage.textContent = 'Analyzing movie options...';
                    } else if (progress < 90) {
                        if (isFirstRun) {
                            activeProcessingMessage.textContent = 'Finding theaters near you...';
                        } else {
                            activeProcessingMessage.textContent = 'Preparing recommendations...';
                        }
                    }
                }
            }, 500);

            // Prepare request data
            const requestData = {
                message: message,
                first_run_filter: isFirstRun
            };

            // Add location if provided
            if (location) {
                requestData.location = location;
            }

            // Add timezone information if available (for showtime conversions)
            if (window.userTimezone) {
                requestData.timezone = window.userTimezone;
                console.log(`Sending timezone with request: ${window.userTimezone}`);
            }

            // Send to server
            fetch(SEND_MESSAGE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRFToken': getCookie('csrftoken')
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                // Complete the progress animation
                clearInterval(progressInterval);
                activeProgressBar.style.width = '100%';
                activeProgressBar.setAttribute('aria-valuenow', 100);
                activeProcessingMessage.textContent = 'Loading results...';

                // Slight delay to show the completed progress bar
                setTimeout(() => {
                    // Hide processing container
                    activeProcessingContainer.style.display = 'none';

                    if (data.status === 'success') {
                        // Add bot message to chat
                        appendMessage(data.message, 'bot', isFirstRun);

                        // Update recommendations if any
                        if (data.recommendations && data.recommendations.length > 0) {
                            if (isFirstRun) {
                                updateMovieGrid(data.recommendations);
                                updateShowtimesSection(data.recommendations);
                            } else {
                                updateCasualMovies(data.recommendations);
                            }
                        }
                    } else {
                        appendMessage('Sorry, there was an error processing your request. Please try again.', 'bot', isFirstRun);
                    }

                    // Re-enable all interactive elements
                    activeInput.disabled = false;
                    activeButton.disabled = false;

                    if (isFirstRun) {
                        locationInput.disabled = false;
                    }

                    // Focus input for next message
                    activeInput.focus();
                }, 500);
            })
            .catch(error => {
                // Stop the progress animation
                clearInterval(progressInterval);

                // Hide processing container
                activeProcessingContainer.style.display = 'none';

                console.error('Error:', error);
                appendMessage('Sorry, there was an error processing your request. Please try again.', 'bot', isFirstRun);

                // Re-enable all interactive elements
                activeInput.disabled = false;
                activeButton.disabled = false;

                if (isFirstRun) {
                    locationInput.disabled = false;
                }

                // Focus input for next message
                activeInput.focus();
            });
        }
    };

    // Add a message to the chat
    function appendMessage(text, sender, isFirstRun = true) {
        // Target the correct container
        const targetContainer = isFirstRun ? chatContainer : casualChatContainer;

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender === 'user' ? 'user-message' : 'bot-message'}`;

        // Apply formatting for bot messages, or set as plain text for user messages
        if (sender === 'bot') {
            messageDiv.innerHTML = formatMessageText(text);
        } else {
            messageDiv.textContent = text;
        }

        const timeSpan = document.createElement('span');
        timeSpan.className = `message-time ${sender === 'user' ? 'text-end' : ''}`;
        const now = new Date();
        timeSpan.textContent = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

        targetContainer.appendChild(messageDiv);
        targetContainer.appendChild(timeSpan);

        // Scroll to bottom of chat
        targetContainer.scrollTop = targetContainer.scrollHeight;
    }

    // Function to prepare theaters container based on the current state
    function prepareTheatersContainer(state, message = '') {
        const theatersContainer = document.getElementById('theatersContainer');
        if (!theatersContainer) return;

        switch (state) {
            case 'no-movie':
                theatersContainer.innerHTML = `
                    <p class="text-center text-muted mt-3">
                        <i class="bi bi-hand-index-thumb"></i>
                        Click on a movie above to see available theaters and showtimes
                    </p>
                `;
                break;
            case 'no-date':
                // We have a movie but need a date selection
                theatersContainer.innerHTML = `
                    <p class="text-center text-muted mt-3">
                        <i class="bi bi-calendar3"></i>
                        Please select a date to see showtimes for "${MovieManager.getSelectedMovieTitle()}"
                    </p>
                `;
                break;
            case 'no-theaters':
                theatersContainer.innerHTML = `
                    <p class="text-muted">
                        No theaters are currently showing "${MovieManager.getSelectedMovieTitle()}".
                        Please check back later or select a different movie.
                    </p>
                `;
                break;
            case 'no-showtimes':
                theatersContainer.innerHTML = `
                    <p class="text-muted">
                        No showtimes available for "${MovieManager.getSelectedMovieTitle()}"
                        ${message ? 'on ' + message : 'in the next few days'}.
                    </p>
                `;
                break;
            case 'custom':
                theatersContainer.innerHTML = message;
                break;
            default:
                theatersContainer.innerHTML = '';
        }
    }

    // Movie grid functions - using TMDB-style grid view
    window.updateMovieGrid = function(movies) {
        // First try to enhance movie posters with high-quality versions
        enhanceMoviePosters(movies, function(enhancedMovies) {
            // Use our new TMDB-style grid renderer with First Run mode
            renderTMDBGrid(enhancedMovies, 'movieGridContainer', true);

            // Also update the theaters section since we've got new movies
            updateShowtimesSection(enhancedMovies);
        });
    }

    // Update the casual movies view
    window.updateCasualMovies = function(movies) {
        // Enhance posters and then render
        enhanceMoviePosters(movies, function(enhancedMovies) {
            // Use the same renderer for consistency, but in Casual mode
            renderTMDBGrid(enhancedMovies, 'casualMovieContainer', false);
        });
    }

    // Update the showtimes section
    window.updateShowtimesSection = function(movies) {
        const theatersContainer = document.getElementById('theatersContainer');
        const dateTabsContainer = document.getElementById('dateTabs');
        const showtimesSection = document.getElementById('showtimesSection');

        console.log("========= UPDATING SHOWTIMES SECTION =========");
        console.log("Raw movies received:", movies);

        // Check for theaters in each movie - with detailed logging
        movies.forEach(movie => {
            // Convert IDs to strings for consistent handling
            if (movie.id) movie.id = String(movie.id);
            if (movie.tmdb_id) movie.tmdb_id = String(movie.tmdb_id);

            const theaterCount = movie.theaters?.length || 0;
            console.log(`Movie '${movie.title}' (ID: ${movie.id || movie.tmdb_id}) has ${theaterCount} theaters`);

            if (theaterCount > 0) {
                console.log(`First theater: ${movie.theaters[0].name} with ${movie.theaters[0].showtimes?.length || 0} showtimes`);
            }
        });

        // Count how many movies are current releases
        const currentReleaseCount = movies.filter(movie => movie.is_current_release === true).length;
        const moviesWithTheatersCount = movies.filter(movie =>
            movie.is_current_release === true &&
            movie.theaters &&
            movie.theaters.length > 0
        ).length;

        // Only display for current movies with theaters
        const currentMovies = movies.filter(movie =>
            movie.is_current_release === true &&
            movie.theaters &&
            movie.theaters.length > 0
        );

        // Store movies in MovieManager instead of global variable
        MovieManager.setMovies(currentMovies);

        // Debug log the movies we're storing
        console.log(`Stored ${currentMovies.length} movies in MovieManager:`);
        MovieManager.debugMovies();

        // Log information for debugging
        console.log(`Total movies: ${movies.length}`);
        console.log(`Current release movies: ${currentReleaseCount}`);
        console.log(`Movies with theaters: ${moviesWithTheatersCount}`);

        // Initially hide date tabs
        dateTabsContainer.style.display = 'none';

        if (currentMovies.length === 0) {
            // If no current movies with theaters, show appropriate message
            if (currentReleaseCount === 0) {
                theatersContainer.innerHTML = '<p class="text-muted">No current release movies found. Try searching for movies currently playing in theaters.</p>';
            } else {
                theatersContainer.innerHTML = '<p class="text-muted">No theaters near you are currently showing the recommended movies.</p>';
            }
            dateTabsContainer.innerHTML = '';
        } else {
            // Show instruction message to select a movie first
            theatersContainer.innerHTML = '<p class="text-center text-muted mt-3"><i class="bi bi-hand-index-thumb"></i> Click on a movie above to see available theaters and showtimes</p>';

            // Generate date tabs for current day + 3 more days (4 total)
            dateTabsContainer.innerHTML = '';
            const dates = [];
            const today = new Date();

            for (let i = 0; i < 4; i++) {
                const date = new Date(today);
                date.setDate(today.getDate() + i);
                dates.push(date);

                // Format day name and date
                const dayName = i === 0 ? 'Today' : date.toLocaleDateString('en-US', { weekday: 'short' });
                const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

                // Create tab button
                const tabButton = document.createElement('button');
                tabButton.type = 'button';
                tabButton.className = `date-tab ${i === 0 ? 'active' : ''}`;
                tabButton.setAttribute('data-date-index', i);
                tabButton.innerHTML = `
                    <div class="small">${dayName}</div>
                    <div>${dateStr}</div>
                `;

                // Add click handler to switch date
                tabButton.addEventListener('click', function() {
                    // Remove active class from all tabs
                    document.querySelectorAll('.date-tab').forEach(btn => {
                        btn.classList.remove('active');
                    });

                    // Add active class to clicked tab
                    this.classList.add('active');

                    // Update showtimes display for this date
                    displayShowtimesForDate(parseInt(this.getAttribute('data-date-index')));
                });

                dateTabsContainer.appendChild(tabButton);
            }

            // Ensure we're not showing any theaters until a movie is selected
            if (!MovieManager.getSelectedMovieId()) {
                theatersContainer.innerHTML = '<p class="text-center text-muted mt-3"><i class="bi bi-hand-index-thumb"></i> Click on a movie above to see available theaters and showtimes</p>';
            }
        }
    }

    // Display showtimes for a specific date
    function displayShowtimesForDate(dateIndex) {
        // Store this date selection in MovieManager
        MovieManager.selectDate(dateIndex);

        // Get the selected movie from MovieManager
        const movie = MovieManager.getSelectedMovie();

        // Ensure we have a selected movie
        if (!movie) {
            prepareTheatersContainer('no-movie');
            return;
        }

        console.log(`displayShowtimesForDate for movie: ${movie.title} and date index: ${dateIndex}`);

        // Debug the selected movie data
        console.log(`Processing theaters for selected movie: ${movie.title}`, movie);

        if (!movie.theaters || movie.theaters.length === 0) {
            prepareTheatersContainer('no-theaters');
            return;
        }

        // Map to hold theaters with showtimes for the selected date
        const theatersByName = new Map();

        // Get today's date and the selected date
        const today = new Date();
        const selectedDate = new Date(today);
        selectedDate.setDate(today.getDate() + dateIndex);
        const selectedDateStr = selectedDate.toISOString().split('T')[0];

        console.log(`Filtering showtimes for date: ${selectedDateStr}`);

        // Process each theater for the selected movie
        movie.theaters.forEach(theater => {
            if (!theater.name || !theater.showtimes || !Array.isArray(theater.showtimes)) {
                return; // Skip invalid theaters
            }

            // Filter showtimes for the selected date
            const showtimesForDate = theater.showtimes.filter(showtime => {
                if (!showtime.start_time) return false;
                const date = new Date(showtime.start_time);
                return date.toISOString().split('T')[0] === selectedDateStr;
            });

            if (showtimesForDate.length === 0) {
                return; // Skip theaters with no showtimes on selected date
            }

            // Create or get theater entry
            if (!theatersByName.has(theater.name)) {
                theatersByName.set(theater.name, {
                    name: theater.name,
                    address: theater.address || '',
                    distance_miles: theater.distance_miles || 0,
                    showtimesByFormat: {}
                });
            }

            const theaterEntry = theatersByName.get(theater.name);

            // Group showtimes by format
            showtimesForDate.forEach(showtime => {
                const format = showtime.format || 'Standard';
                if (!theaterEntry.showtimesByFormat[format]) {
                    theaterEntry.showtimesByFormat[format] = [];
                }
                theaterEntry.showtimesByFormat[format].push(showtime.start_time);
            });
        });

        // Convert to array and sort by distance
        const theatersArray = Array.from(theatersByName.values())
            .sort((a, b) => (a.distance_miles || 0) - (b.distance_miles || 0));

        console.log(`Found ${theatersArray.length} theaters with showtimes for ${movie.title} on the selected date`);

        // If no theaters found for the selected date
        if (theatersArray.length === 0) {
            // Format the date for display
            const dateStr = selectedDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            prepareTheatersContainer('no-showtimes', dateStr);
            return;
        }

        // Clear theaters container before adding content
        const theatersContainer = document.getElementById('theatersContainer');
        theatersContainer.innerHTML = '';

        // Display each theater with showtimes
        theatersArray.forEach(theater => {
            const theaterSection = document.createElement('div');
            theaterSection.className = 'theater-section mb-3';

            // Format distance
            const distanceStr = theater.distance_miles ?
                `${theater.distance_miles.toFixed(1)} mi · ` : '';

            // Create theater header
            const theaterHeader = document.createElement('div');
            theaterHeader.className = 'theater-header';
            theaterHeader.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">${theater.name}</h5>
                        <small>${distanceStr}${theater.address || ''}</small>
                    </div>
                </div>
            `;

            // Create theater body
            const theaterBody = document.createElement('div');
            theaterBody.className = 'theater-body';

            // For each format (Standard, IMAX, etc.)
            Object.entries(theater.showtimesByFormat).forEach(([format, times]) => {
                const formatDiv = document.createElement('div');
                formatDiv.className = 'mb-3';
                formatDiv.innerHTML = `<div class="small text-muted mb-1">${format}</div>`;

                const showtimesDiv = document.createElement('div');
                showtimesDiv.className = 'showtimes-scroll-container';

                // Sort and format times
                times
                    .map(timeStr => new Date(timeStr))
                    .sort((a, b) => a - b) // Sort chronologically
                    .forEach(date => {
                        // Format as 24-hour time (HH:MM)
                        const timeStr = date.toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false
                        });

                        // Create showtime badge with appropriate class based on format
                        const badgeClass = format.toLowerCase().includes('imax') ? 'imax' :
                                          format.toLowerCase().includes('3d') ? 'threed' : '';

                        const badge = document.createElement('span');
                        badge.className = `showtime-badge ${badgeClass}`;
                        badge.textContent = timeStr;

                        showtimesDiv.appendChild(badge);
                    });

                formatDiv.appendChild(showtimesDiv);
                theaterBody.appendChild(formatDiv);
            });

            // Add header and body to section
            theaterSection.appendChild(theaterHeader);
            theaterSection.appendChild(theaterBody);

            // Add to container
            theatersContainer.appendChild(theaterSection);
        });
    }

    // Handle movie card clicks
    window.handleMovieClick = function(movieId, movieTitle) {
        console.log(`Movie clicked: ${movieTitle} (ID: ${movieId})`);

        // Debug log to trace what's happening
        console.log(`Previous selected movie ID: ${MovieManager.getSelectedMovieId()}`);

        // If this is the same movie already selected, don't do anything
        if (MovieManager.getSelectedMovieId() === String(movieId)) {
            console.log("Same movie clicked - no update needed");
            return;
        }

        // Clear any previous selections
        document.querySelectorAll('.movie-card').forEach(card => {
            card.classList.remove('selected');
        });

        // Highlight the selected movie
        document.querySelectorAll(`.movie-card[data-movie-id="${movieId}"]`).forEach(card => {
            card.classList.add('selected');
        });

        // Reset any previous date selection
        MovieManager.resetDateSelection();

        // Select the movie in our MovieManager
        if (!MovieManager.selectMovie(movieId)) {
            console.error(`Movie with ID ${movieId} not found in MovieManager`);
            return;
        }

        const selectedMovie = MovieManager.getSelectedMovie();
        console.log(`New selected movie: ${selectedMovie.title}`, selectedMovie);

        // Check if the movie has theaters
        if (!selectedMovie.theaters || selectedMovie.theaters.length === 0) {
            // No theaters available for this movie
            prepareTheatersContainer('no-theaters');

            // Hide date tabs
            const dateTabs = document.getElementById('dateTabs');
            if (dateTabs) {
                dateTabs.style.display = 'none';
            }
            return;
        }

        // Check if the movie has any showtimes across all dates
        if (!MovieManager.hasAnyShowtimes()) {
            prepareTheatersContainer('no-showtimes');

            // Hide date tabs
            const dateTabs = document.getElementById('dateTabs');
            if (dateTabs) {
                dateTabs.style.display = 'none';
            }
            return;
        }

        // Always rebuild date tabs to ensure fresh event listeners that reference the current selected movie
        rebuildDateTabs();

        // Show the "please select a date" message instead of immediately showing theaters
        prepareTheatersContainer('no-date');
    };

    // Function to rebuild date tabs with fresh event listeners
    function rebuildDateTabs() {
        const dateTabs = document.getElementById('dateTabs');
        if (!dateTabs) return;

        // Clear existing tabs
        dateTabs.innerHTML = '';

        // Generate date tabs for current day + 3 more days (4 total)
        const today = new Date();

        for (let i = 0; i < 4; i++) {
            const date = new Date(today);
            date.setDate(today.getDate() + i);

            // Format day name and date
            const dayName = i === 0 ? 'Today' : date.toLocaleDateString('en-US', { weekday: 'short' });
            const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

            // Create tab button
            const tabButton = document.createElement('button');
            tabButton.type = 'button';
            tabButton.className = `date-tab ${i === 0 ? 'active' : ''}`;
            tabButton.setAttribute('data-date-index', i);
            tabButton.innerHTML = `
                <div class="small">${dayName}</div>
                <div>${dateStr}</div>
            `;

            // Create a closure to capture the current date index
            // This ensures each event listener gets its own copy of i
            (function(dateIndex) {
                tabButton.addEventListener('click', function() {
                    // Remove active class from all tabs
                    document.querySelectorAll('.date-tab').forEach(btn => {
                        btn.classList.remove('active');
                    });

                    // Add active class to clicked tab
                    this.classList.add('active');

                    console.log(`Date tab clicked: ${dateIndex} for movie: ${MovieManager.getSelectedMovieTitle()}`);

                    // Update showtimes display for this date
                    displayShowtimesForDate(dateIndex);
                });
            })(i);

            dateTabs.appendChild(tabButton);
        }

        // Show date tabs
        dateTabs.style.display = 'flex';
    }

    // Get CSRF token for AJAX request
    window.getCookie = function(name) {
        let cookieValue = null;
        if (document.cookie && document.cookie !== '') {
            const cookies = document.cookie.split(';');
            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i].trim();
                if (cookie.substring(0, name.length + 1) === (name + '=')) {
                    cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
                    break;
                }
            }
        }
        return cookieValue;
    }

    // Event listeners
    sendButton.addEventListener('click', () => sendMessage(true));
    casualSendButton.addEventListener('click', () => sendMessage(false));

    userInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            sendMessage(true);
            event.preventDefault();
        }
    });

    casualUserInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            sendMessage(false);
            event.preventDefault();
        }
    });

    // Try to get user location automatically
    function detectUserLocation() {
        // Show the processing indicator
        processingMessage.textContent = "Detecting your location...";
        processingContainer.style.display = 'block';
        progressBar.style.width = '50%';

        // Disable user input while detecting location
        userInput.disabled = true;
        sendButton.disabled = true;

        console.log("Starting location detection");

        // Function to hide the processing indicator
        function hideProcessing(message = null) {
            if (message) {
                processingMessage.textContent = message;
                setTimeout(() => {
                    processingContainer.style.display = 'none';
                }, 2000);
            } else {
                processingContainer.style.display = 'none';
            }
        }

        // Function to check if a location is in the US
        function isLocationInUS(country_code) {
            return country_code === 'US';
        }

        // Function to enable user input once we have a valid location
        function enableInput(hasValidLocation) {
            if (hasValidLocation) {
                // Enable the input field
                userInput.disabled = false;
                sendButton.disabled = false;
                console.log("Chat input enabled with valid location");
            } else {
                // Keep input disabled until a location is entered
                locationInput.focus();
                locationInput.placeholder = "Enter a US city and state (e.g., Seattle, Washington, United States)";
                console.log("Chat input remains disabled until location is set");
            }
        }

        // Function when we detect non-US location or can't detect location
        function handleNonUSLocation() {
            console.log("Location not in US or couldn't be determined");
            locationInput.value = ""; // Clear the value
            hideProcessing("Please enter a US city and state (e.g., Seattle, Washington, United States)");
            enableInput(false);
        }

        // Listen for changes to the location input
        locationInput.addEventListener('input', function() {
            // Enable the user input if location has been entered
            if (locationInput.value.trim().length > 0) {
                userInput.disabled = false;
                sendButton.disabled = false;
            }
        });

        // First try to use browser's geolocation API
        if (navigator.geolocation) {
            console.log("Geolocation API available, requesting position");
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    // Success - we have coordinates, now use ipapi.co directly
                    // Since browser geolocation succeeded, we could just use ipapi.co to get location info
                    // This is more reliable than trying to do reverse geocoding from coordinates
                    gatherLocationDataFromIpApi();
                },
                function(error) {
                    console.error(`Geolocation error (${error.code}): ${error.message}`);
                    // Fall back to IP-based geolocation
                    gatherLocationDataFromIpApi();
                },
                {
                    enableHighAccuracy: true, // Try for best accuracy
                    timeout: 10000, // 10 second timeout
                    maximumAge: 5 * 60 * 1000 // 5 minutes cache
                }
            );
        } else {
            // Browser doesn't support geolocation
            console.error("Geolocation not supported by this browser");
            // Fall back to IP-based geolocation
            gatherLocationDataFromIpApi();
        }

        // Function to gather location and timezone data from ipapi.co
        function gatherLocationDataFromIpApi() {
            console.log("Using ipapi.co for location and timezone detection");

            // Use ipapi.co - no API key needed
            fetch('https://ipapi.co/json/')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Received ipapi.co response:", data);

                // Check if location is in the US
                if (!isLocationInUS(data.country_code)) {
                    console.log(`Detected non-US location: ${data.country_name || 'unknown'}`);
                    handleNonUSLocation();
                    return;
                }

                // Extract city and state for US locations
                const city = data.city;
                const state = data.region;
                const country = data.country_name;

                // Capture timezone information
                if (data.timezone) {
                    window.userTimezone = data.timezone;
                    console.log(`Captured user timezone: ${window.userTimezone}`);
                } else {
                    console.warn("No timezone information in ipapi.co response");
                    window.userTimezone = "America/Los_Angeles";
                }

                // If we have all values, use the standard "City, State, Country" format
                if (city && state && country) {
                    const locationName = `${city}, ${state}, ${country}`;
                    console.log(`Setting location to: ${locationName}`);
                    locationInput.value = locationName;
                    hideProcessing(`Location detected: ${locationName}`);
                    enableInput(true);
                    return;
                }

                // If we couldn't extract both city and state, handle as non-US location
                console.error("Could not parse US city/state from response:", data);
                handleNonUSLocation();
            })
            .catch(error => {
                console.error("Error with ipapi.co:", error);
                handleNonUSLocation();
            });
        }
    }

    // Make sample question functions available globally
    window.sendSampleQuestion = function(question) {
        // Wait for DOM to be ready
        setTimeout(() => {
            const userInput = document.getElementById('userInput');
            userInput.value = question;
            // Focus the input to show the user the text was added
            userInput.focus();
            // Send the message
            window.sendMessage(true); // true for first run mode
        }, 0);
    };

    window.sendCasualSampleQuestion = function(question) {
        // Wait for DOM to be ready
        setTimeout(() => {
            const casualUserInput = document.getElementById('casualUserInput');
            casualUserInput.value = question;
            // Focus the input to show the user the text was added
            casualUserInput.focus();
            // Send the message
            window.sendMessage(false); // false for casual viewing mode
        }, 0);
    };

    // Detect location on page load
    detectUserLocation();

    // Focus input on page load
    userInput.focus();

    // Initialize genre tabs - ensure popular tab is visible on load
    switchGenreTab('popular');
});
