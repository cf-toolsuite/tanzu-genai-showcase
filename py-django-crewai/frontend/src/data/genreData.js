// Genre data with subgenres and icons
export const genreData = {
    popular: {
        title: 'Popular Genres',
        subgenres: [
            { name: 'Action', icon: 'bi-lightning-fill', query: 'action movies' },
            { name: 'Comedy', icon: 'bi-emoji-laughing', query: 'comedy movies' },
            { name: 'Sci-Fi', icon: 'bi-stars', query: 'sci-fi movies' },
            { name: 'Horror', icon: 'bi-droplet-fill', query: 'horror movies' }
        ]
    },
    action: {
        title: 'Action Subgenres',
        subgenres: [
            { name: 'Martial Arts', icon: 'bi-person-arms-up', query: 'martial arts movies' },
            { name: 'Superhero', icon: 'bi-shield-fill', query: 'superhero movies' },
            { name: 'Heist', icon: 'bi-bank', query: 'heist movies' },
            { name: 'Espionage', icon: 'bi-eye-fill', query: 'spy and espionage movies' },
            { name: 'Disaster', icon: 'bi-tsunami', query: 'disaster movies' },
            { name: 'Military', icon: 'bi-airplane-fill', query: 'military action movies' }
        ]
    },
    animation: {
        title: 'Animation Subgenres',
        subgenres: [
            { name: 'Traditional', icon: 'bi-brush', query: 'traditional animation movies' },
            { name: 'CGI', icon: 'bi-pc-display', query: 'CGI animated movies' },
            { name: 'Stop Motion', icon: 'bi-camera-reels', query: 'stop motion movies' },
            { name: 'Anime', icon: 'bi-star', query: 'anime movies' }
        ]
    },
    comedy: {
        title: 'Comedy Subgenres',
        subgenres: [
            { name: 'Rom-Com', icon: 'bi-heart-fill', query: 'romantic comedy movies' },
            { name: 'Slapstick', icon: 'bi-emoji-dizzy', query: 'slapstick comedy movies' },
            { name: 'Parody', icon: 'bi-emoji-sunglasses', query: 'parody movies' },
            { name: 'Dark Comedy', icon: 'bi-moon-fill', query: 'black comedy movies' },
            { name: 'Action Comedy', icon: 'bi-lightning', query: 'action comedy movies' }
        ]
    },
    crime: {
        title: 'Crime Subgenres',
        subgenres: [
            { name: 'Gangster', icon: 'bi-person-badge', query: 'gangster movies' },
            { name: 'Detective', icon: 'bi-search', query: 'detective movies' },
            { name: 'Film Noir', icon: 'bi-lamp', query: 'film noir movies' },
            { name: 'Heist', icon: 'bi-bank', query: 'heist movies' },
            { name: 'Legal Thriller', icon: 'bi-briefcase', query: 'courtroom movies' }
        ]
    },
    drama: {
        title: 'Drama Subgenres',
        subgenres: [
            { name: 'Historical', icon: 'bi-clock-history', query: 'historical drama movies' },
            { name: 'Coming of Age', icon: 'bi-person-fill', query: 'coming of age movies' },
            { name: 'Political', icon: 'bi-flag', query: 'political drama movies' },
            { name: 'Sports', icon: 'bi-trophy', query: 'sports drama movies' },
            { name: 'Melodrama', icon: 'bi-emoji-frown', query: 'melodrama movies' }
        ]
    },
    fantasy: {
        title: 'Fantasy Subgenres',
        subgenres: [
            { name: 'High Fantasy', icon: 'bi-castle', query: 'high fantasy movies' },
            { name: 'Urban Fantasy', icon: 'bi-buildings', query: 'urban fantasy movies' },
            { name: 'Fairy Tale', icon: 'bi-magic', query: 'fairy tale movies' },
            { name: 'Dark Fantasy', icon: 'bi-moon-stars-fill', query: 'dark fantasy movies' },
            { name: 'Sword & Sorcery', icon: 'bi-sword', query: 'sword and sorcery movies' }
        ]
    },
    horror: {
        title: 'Horror Subgenres',
        subgenres: [
            { name: 'Slasher', icon: 'bi-knife', query: 'slasher horror movies' },
            { name: 'Supernatural', icon: 'bi-ghost', query: 'supernatural horror movies' },
            { name: 'Psychological', icon: 'bi-bezier', query: 'psychological horror movies' },
            { name: 'Zombie', icon: 'bi-bandaid-fill', query: 'zombie movies' },
            { name: 'Found Footage', icon: 'bi-camera-video', query: 'found footage horror movies' },
            { name: 'Body Horror', icon: 'bi-bug', query: 'body horror movies' }
        ]
    },
    romance: {
        title: 'Romance Subgenres',
        subgenres: [
            { name: 'Romantic Drama', icon: 'bi-heart-half', query: 'romantic drama movies' },
            { name: 'Rom-Com', icon: 'bi-emoji-heart-eyes', query: 'romantic comedy movies' },
            { name: 'Period Romance', icon: 'bi-hourglass', query: 'period romance movies' },
            { name: 'Paranormal', icon: 'bi-magic', query: 'paranormal romance movies' }
        ]
    },
    scifi: {
        title: 'Sci-Fi Subgenres',
        subgenres: [
            { name: 'Space Opera', icon: 'bi-rocket', query: 'space opera movies' },
            { name: 'Cyberpunk', icon: 'bi-cpu', query: 'cyberpunk movies' },
            { name: 'Post-Apocalyptic', icon: 'bi-exclamation-triangle', query: 'post-apocalyptic movies' },
            { name: 'Time Travel', icon: 'bi-hourglass-split', query: 'time travel movies' },
            { name: 'Dystopian', icon: 'bi-building-fill-down', query: 'dystopian movies' },
            { name: 'Alien', icon: 'bi-ufo', query: 'alien invasion movies' }
        ]
    },
    thriller: {
        title: 'Thriller Subgenres',
        subgenres: [
            { name: 'Psychological', icon: 'bi-graph-up', query: 'psychological thriller movies' },
            { name: 'Crime', icon: 'bi-question-octagon', query: 'crime thriller movies' },
            { name: 'Political', icon: 'bi-building', query: 'political thriller movies' },
            { name: 'Mystery', icon: 'bi-question-circle', query: 'mystery thriller movies' },
            { name: 'Techno-thriller', icon: 'bi-cpu-fill', query: 'techno-thriller movies' }
        ]
    },
    documentary: {
        title: 'Documentary Subgenres',
        subgenres: [
            { name: 'Biographical', icon: 'bi-person-badge', query: 'biographical documentary movies' },
            { name: 'Historical', icon: 'bi-book', query: 'historical documentary movies' },
            { name: 'Nature', icon: 'bi-tree', query: 'nature documentary movies' },
            { name: 'Music', icon: 'bi-music-note', query: 'music documentary movies' },
            { name: 'Sports', icon: 'bi-trophy', query: 'sports documentary movies' },
            { name: 'True Crime', icon: 'bi-file-earmark-text', query: 'true crime documentary movies' }
        ]
    }
};
