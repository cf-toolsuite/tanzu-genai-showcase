import React, { useState, useRef, useEffect } from 'react';
import { useAppContext } from '../../context/AppContext';
import { chatApi } from '../../services/api';
import { useLocation } from '../../hooks/useLocation';
import MessageList from './MessageList';
import InputArea from './InputArea';
import ProgressIndicator from './ProgressIndicator';

function ChatInterface() {
  // Initialize location detection for First Run mode
  const detectLocation = activeTab === 'first-run' ? useLocation() : null;

  const {
    firstRunMessages, setFirstRunMessages,
    casualMessages, setCasualMessages,
    setFirstRunMovies, setCasualMovies,
    loading, setLoading,
    progress, setProgress,
    location,
    requestStage, setRequestStage,
    activeTab
  } = useAppContext();

  const [inputValue, setInputValue] = useState('');
  const chatContainerRef = useRef(null);
  const inputRef = useRef(null);
  const sendButtonRef = useRef(null);

  // Local state for retry logic and progress message
  const [retryCount, setRetryCount] = useState(0);
  const [progressMessage, setProgressMessage] = useState('');

  // Effect to set up event listeners for external triggers
  useEffect(() => {
    const handleSubgenreQuery = (event) => {
      const { query } = event.detail;
      setInputValue(query);
      setTimeout(() => sendMessage(query), 0);
    };

    document.addEventListener('subgenreQuery', handleSubgenreQuery);
    return () => document.removeEventListener('subgenreQuery', handleSubgenreQuery);
  }, []);

  // Scroll chat to bottom when messages change
  useEffect(() => {
    if (chatContainerRef.current) {
      chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
    }
  }, [firstRunMessages, casualMessages]);

  // Get appropriate message based on stage and progress
  const getProgressMessage = (stage, currentProgress) => {
    let message = '';

    switch (stage) {
      case 'sending':
        message = 'Sending your request...';
        break;
      case 'searching':
        message = 'Searching for movies matching your criteria...';
        break;
      case 'analyzing':
        message = 'Analyzing movie options and preferences...';
        break;
      case 'theaters':
        message = activeTab === 'first-run'
          ? 'Finding theaters and showtimes near you...'
          : 'Preparing your recommendations...';
        break;
      case 'complete':
        message = 'Loading results...';
        break;
      default:
        // Fallback to progress-based messages
        if (currentProgress < 30) {
          message = 'Looking for movies matching your request...';
        } else if (currentProgress < 60) {
          message = 'Analyzing movie options...';
        } else if (currentProgress < 90) {
          message = activeTab === 'first-run'
            ? 'Finding theaters near you...'
            : 'Preparing recommendations...';
        } else {
          message = 'Loading results...';
        }
    }

    return message;
  };

  // Update effect for progress message (using memoized dependencies to avoid infinite loops)
  useEffect(() => {
    const newMessage = getProgressMessage(requestStage, progress);
    setProgressMessage(newMessage);
  }, [requestStage, progress, activeTab]);

  // Progress simulation with stage-based messages
  const startProgressSimulation = () => {
    setProgress(0);
    return setInterval(() => {
      setProgress(prev => {
        if (prev >= 90) return prev;
        return prev + 5;
      });
    }, 500);
  };

  // Handle sending a message
  const sendMessage = async (message) => {
    // If we're loading or no message, don't proceed
    if (loading || !message) {
      console.log('Message rejected:', { loading, message });
      return;
    }

    console.log('Processing message in ChatInterface:', { message, activeTab });

    const setMessages = activeTab === 'first-run' ? setFirstRunMessages : setCasualMessages;
    const currentMessages = activeTab === 'first-run' ? firstRunMessages : casualMessages;

    // Add user message
    const userMessage = {
      sender: 'user',
      content: message,
      created_at: new Date().toISOString()
    };

    setMessages([...currentMessages, userMessage]);
    setInputValue('');

    // Start loading state
    setLoading(true);
    setRequestStage('sending');
    setRetryCount(0);

    const progressInterval = startProgressSimulation();

    try {
      // Update stages as the request progresses
      setRequestStage('searching');
      await new Promise(resolve => setTimeout(resolve, 1000));

      setRequestStage('analyzing');
      let response;

      try {
        if (activeTab === 'first-run') {
          response = await chatApi.getMoviesTheatersAndShowtimes(message, location);
        } else {
          response = await chatApi.getMovieRecommendations(message);
        }
      } catch (error) {
        console.error('API call failed:', error);
        throw {
          status: 'error',
          message: 'Failed to get movie recommendations. Please try again.'
        };
      }

      if (!response || response.status !== 'success') {
        throw {
          status: 'error',
          message: response?.message || 'Failed to process your request. Please try again.'
        };
      }

      // Format bot response with theater counts for first run movies
      let botContent = response.message;
      if (activeTab === 'first-run' && response.recommendations) {
        const movieTheaterCounts = response.recommendations.map(movie => {
          const theaterCount = movie.theaters ? movie.theaters.length : 0;
          return `${movie.title}: Available at ${theaterCount} theater${theaterCount === 1 ? '' : 's'}`;
        }).join('\n');
        botContent = `${response.message}\n\nTheater Availability:\n${movieTheaterCounts}`;
      }

      const botMessage = {
        sender: 'bot',
        content: botContent,
        created_at: new Date().toISOString()
      };

      setMessages([...currentMessages, userMessage, botMessage]);

      if (response.recommendations && response.recommendations.length > 0) {
        setRequestStage(activeTab === 'first-run' ? 'theaters' : 'complete');

        // Update movies with a small delay
        setTimeout(() => {
          if (activeTab === 'first-run') {
            setFirstRunMovies(response.recommendations);
          } else {
            setCasualMovies(response.recommendations);
          }
        }, 100);
      }
    } catch (error) {
      console.error('Error sending message:', error);

      // Add error message with retry option
      const errorContent = (
        <div>
          <p>{error.message || 'Sorry, there was an error processing your request.'}</p>
          <button
            className="btn btn-sm btn-outline-danger mt-2"
            onClick={() => {
              setMessages([...currentMessages, userMessage]);
              setRetryCount(prev => prev + 1);
              setTimeout(() => {
                sendMessage(message);
              }, Math.min(1000 * Math.pow(2, retryCount), 10000));
            }}
          >
            <i className="bi bi-arrow-repeat me-1"></i>
            Retry
          </button>
        </div>
      );

      const errorMessage = {
        sender: 'bot',
        content: errorContent,
        created_at: new Date().toISOString()
      };

      setMessages([...currentMessages, userMessage, errorMessage]);
    } finally {
      clearInterval(progressInterval);
      setProgress(100);

      setTimeout(() => {
        setLoading(false);
        setProgress(0);
        setRequestStage('idle');
      }, 500);
    }
  };

  const currentMessages = activeTab === 'first-run' ? firstRunMessages : casualMessages;

  return (
    <div className="content-wrapper">
      <div className="chat-container" ref={chatContainerRef}>
        <MessageList messages={currentMessages} />
      </div>

      {loading && (
        <ProgressIndicator
          progress={progress}
          message={progressMessage}
        />
      )}

      <InputArea
        ref={inputRef}
        value={inputValue}
        onChange={setInputValue}
        onSend={sendMessage}
        disabled={loading}
        placeholder={activeTab === 'first-run'
          ? "Ask me about movies in theaters..."
          : "Ask me for movie recommendations..."}
        sendButtonRef={sendButtonRef}
        id={activeTab === 'first-run' ? 'userInput' : 'casualUserInput'}
        sendButtonId={activeTab === 'first-run' ? 'sendButton' : 'casualSendButton'}
      />
    </div>
  );
}

export default ChatInterface;
