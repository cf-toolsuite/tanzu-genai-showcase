import React, { useState } from 'react';
import { genreData } from '../../data/genreData';

function GenreSelector({ onSubgenreClick, disabled }) {
  const [selectedGenre, setSelectedGenre] = useState('popular');

  // Handle genre selection change
  const handleGenreChange = (event) => {
    const genreId = event.target.value;
    setSelectedGenre(genreId);
  };

  // Get current genre data
  const currentGenre = genreData[selectedGenre] || genreData.popular;

  return (
    <div className="mb-4">
      <h4 className="mb-3">Browse Categories</h4>

      {/* Genre Navigation */}
      <div className="genre-nav mb-3">
        <select
          className="form-select bg-dark text-light border-secondary"
          value={selectedGenre}
          onChange={handleGenreChange}
          disabled={disabled}
          style={disabled ? { opacity: 0.7, cursor: 'not-allowed' } : {}}
        >
          <option value="popular">Popular Genres</option>
          <option value="action">Action</option>
          <option value="animation">Animation</option>
          <option value="comedy">Comedy</option>
          <option value="crime">Crime</option>
          <option value="drama">Drama</option>
          <option value="fantasy">Fantasy</option>
          <option value="horror">Horror</option>
          <option value="romance">Romance</option>
          <option value="scifi">Science Fiction</option>
          <option value="thriller">Thriller</option>
          <option value="documentary">Documentary</option>
        </select>
      </div>

      {/* Subgenre Buttons */}
      <div className="row g-2">
        {currentGenre.subgenres.map((subgenre, index) => (
          <div key={index} className="col-6">
            <button
              className="btn btn-red-carpet w-100 mb-2"
              onClick={() => {
                if (!disabled) {
                  onSubgenreClick(`Show me ${subgenre.query}`);
                }
              }}
              disabled={disabled}
              style={disabled ? { opacity: 0.7, cursor: 'not-allowed' } : {}}
            >
              {subgenre.icon && (
                <i className={`bi ${subgenre.icon} me-2`} />
              )}
              {subgenre.name}
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}

export default GenreSelector;
