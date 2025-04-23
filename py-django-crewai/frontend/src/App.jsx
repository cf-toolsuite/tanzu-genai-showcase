import React, { Suspense } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AppProvider, useAppContext } from './context/AppContext';
import './styles/skeleton.css';
import './styles/app.css';

// Lazy load components
const ChatInterface = React.lazy(() => import('./components/Chat/LazyChatInterface'));
const MovieSection = React.lazy(() => import('./components/Movies/MovieSection'));
const TheaterSection = React.lazy(() => import('./components/Theaters/LazyTheaterSection'));

// Loading fallback component
const LoadingFallback = () => (
  <div className="processing-container">
    <div className="progress mb-2">
      <div
        className="progress-bar progress-bar-striped progress-bar-animated"
        role="progressbar"
        style={{ width: '100%' }}
      />
    </div>
    <div className="text-center text-muted">
      Loading...
    </div>
  </div>
);

// Create a client for React Query
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      staleTime: 5 * 60 * 1000, // 5 minutes
    },
  },
});

// Main App component
function AppContent() {
  const { activeTab, switchTab } = useAppContext();

  return (
    <>
      <div className="header">
        <div className="container">
          <div className="row align-items-center">
            <div className="col">
              <span className="red-carpet-logo">REDCARPET</span>
              <span className="ms-3 d-none d-md-inline">Recommendations and showtimes for movie enthusiasts</span>
            </div>
            <div className="col-auto">
              <a href="/reset/" className="btn btn-outline-light btn-sm">
                <i className="bi bi-arrow-repeat me-1"></i>New Chat
              </a>
            </div>
          </div>
        </div>
      </div>
      <div className="main-container">
      <div className="row mb-4">
        <div className="col-12">
          <ul className="nav nav-tabs">
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'first-run' ? 'active' : ''}`}
                onClick={() => switchTab('first-run')}
              >
                <i className="bi bi-film me-2"></i>First Run Movies
              </button>
            </li>
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'casual-viewing' ? 'active' : ''}`}
                onClick={() => switchTab('casual-viewing')}
              >
                <i className="bi bi-collection-play me-2"></i>Casual Viewing
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div className="tab-content mt-4">
        {/* First Run Tab (Movies in Theaters) */}
        <div className={`tab-pane fade ${activeTab === 'first-run' ? 'show active' : ''}`}>
          <div className="row">
            <div className="col-lg-8 mb-4 mb-lg-0">
              <Suspense fallback={<LoadingFallback />}>
                <ChatInterface />
              </Suspense>
            </div>

            <div className="col-lg-4">
              <div className="d-flex flex-column">
                <Suspense fallback={<LoadingFallback />}>
                  <MovieSection isFirstRun={true} />
                </Suspense>
                <Suspense fallback={<LoadingFallback />}>
                  <TheaterSection />
                </Suspense>
              </div>
            </div>
          </div>
        </div>

        {/* Casual Viewing Tab */}
        <div className={`tab-pane fade ${activeTab === 'casual-viewing' ? 'show active' : ''}`}>
          <div className="row">
            <div className="col-lg-8 mb-4 mb-lg-0">
              <Suspense fallback={<LoadingFallback />}>
                <ChatInterface />
              </Suspense>
            </div>

            <div className="col-lg-4">
              <Suspense fallback={<LoadingFallback />}>
                <MovieSection isFirstRun={false} />
              </Suspense>
            </div>
          </div>
        </div>
      </div>
    </div>
    </>
  );
}

// Root component with providers
function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AppProvider>
        <AppContent />
      </AppProvider>
    </QueryClientProvider>
  );
}

export default App;
