import React, { lazy, Suspense } from 'react';
import MovieGridSkeleton from './MovieGridSkeleton';

const MovieGrid = lazy(() => import('./MovieGrid'));

function LazyMovieGrid(props) {
  return (
    <Suspense fallback={<MovieGridSkeleton />}>
      <MovieGrid {...props} />
    </Suspense>
  );
}

export default LazyMovieGrid;
