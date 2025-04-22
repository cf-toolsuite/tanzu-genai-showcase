import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

// Mount React app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('react-app');
  if (container) {
    const root = createRoot(container);
    root.render(<App />);
  }
});
