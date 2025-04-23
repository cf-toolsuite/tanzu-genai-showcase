import React, { useState, useRef, useEffect } from 'react';
import { useAppContext } from '../../context/AppContext';
import { chatApi } from '../../services/api';
import { useLocation } from '../../hooks/useLocation';
import MessageList from './MessageList';
import InputArea from './InputArea';
import ProgressIndicator from './ProgressIndicator';
import SampleInquiries from './SampleInquiries';
import LocationDisplay from './LocationDisplay';

function ChatInterface() {
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

  // Always call hooks at the top level, unconditionally
  const detectLocation = useLocation();

  const [inputValue, setInputValue] = useState('');
  const chatContainerRef = useRef(null);
  const inputRef = useRef(null);
  const sendButtonRef = useRef(null);

  // Local state for retry logic and progress message
  const [retryCount, setRetryCount] = useState(0);
  const [progressMessage, setProgressMessage] = useState('');

  // Run location detection only for First Run mode
  useEffect(() => {
    if (activeTab === 'first-run') {
      detectLocation();
    }
  }, [activeTab, detectLocation]);

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

  // Update effect for progress message
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
    // Debug log the message
    console.log('sendMessage received:', message);
    
    // Safeguard against non-string messages
    if (typeof message !== 'string') {
      console.error('Invalid message type received:', typeof message);
      return;
    }

    // Ensure message is trimmed and not empty
    const trimmedMessage = message.trim();
    
    // If we're loading or message is empty, don't proceed
    if (loading || !trimmedMessage) {
      console.log('Message rejected:', { loading, message: trimmedMessage });
      return;
    }

    console.log('Processing message in ChatInterface:', { message: trimmedMessage, activeTab });

    // Select the correct state setters based on the active tab
    const isFirstRunMode = activeTab === 'first-run';
    const setMessages = isFirstRunMode ? setFirstRunMessages : setCasualMessages;
    const currentMessages = isFirstRunMode ? firstRunMessages : casualMessages;

    // Add user message
    const userMessage = {
      sender: 'user',
      content: trimmedMessage,
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
        // Call the appropriate API based on the current tab
        if (isFirstRunMode) {
          console.log('Using First Run mode API with location:', location);
          response = await chatApi.getMoviesTheatersAndShowtimes(trimmedMessage, location);
        } else {
          console.log('Using Casual Viewing mode API');
          response = await chatApi.getMovieRecommendations(trimmedMessage);
        }
        
        console.log('API response:', response);
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
      if (isFirstRunMode && response.recommendations) {
        const movieTheaterCounts = response.recommendations.map(movie => {
          const theaterCount = movie.theaters ? movie.theaters.length : 0;
          return `${movie.title}: Available at ${theaterCount} theater${theaterCount === 1 ? '' : 's'}`;
        }).join('\n');
        
        if (movieTheaterCounts) {
          botContent = `${response.message}\n\nTheater Availability:\n${movieTheaterCounts}`;
        }
      }

      const botMessage = {
        sender: 'bot',
        content: botContent,
        created_at: new Date().toISOString()
      };

      setMessages([...currentMessages, userMessage, botMessage]);

      if (response.recommendations && response.recommendations.length > 0) {
        setRequestStage(isFirstRunMode ? 'theaters' : 'complete');

        // Update movies with a small delay
        setTimeout(() => {
          if (isFirstRunMode) {
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
                sendMessage(trimmedMessage);
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

  // Handler for sample inquiry click
  const handleSampleInquiryClick = (query) => {
    setInputValue(query);
    sendMessage(query);
  };

  // Get current messages based on active tab
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
      
      {/* Bottom row with Sample Inquiries and Location Display */}
      <div className="bottom-row">
        <div className="sample-inquiries-container">
          <SampleInquiries 
            isFirstRun={activeTab === 'first-run'} 
            onQuestionClick={handleSampleInquiryClick} 
          />
        </div>
        
        {activeTab === 'first-run' && (
          <div className="location-display-container">
            <LocationDisplay />
          </div>
        )}
      </div>
    </div>
  );
}

export default ChatInterface;
