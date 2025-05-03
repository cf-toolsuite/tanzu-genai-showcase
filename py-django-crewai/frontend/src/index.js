import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/app.css';

// Initialize the React application when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('react-app');

  if (container) {
    const root = createRoot(container);
    root.render(<App />);
    console.log('Movie Chatbot React app initialized successfully');
  } else {
    console.error('Could not find #react-app element to mount React application');
  }
});
