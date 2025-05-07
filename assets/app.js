import naPlayer from './services/player.js';
import naPlayerStorage from './services/player-storage.js';
import naSettings from './services/settings.js';
import naStorage from './services/storage.js';

// Include scripts
import './scripts/clipboard.js';
import './scripts/stimulus.js';
import './scripts/swup.js';

// Include web components
import '@octopodcasting/player.js';

// Include CSS
import './app.scss';

// Include images
import './images/adam-curry.jpeg';
import './images/app-icon.png';
import './images/favicon-32.png';
import './images/john-c-dvorak.jpeg';
import './images/placeholder_large.jpg';
import './images/placeholder_small.jpg';
import './images/podcastindex.svg';
import './images/website-icon-128.png';
import './images/website-icon-192.png';
import './images/website-icon-512.png';
import './images/website-logo.svg';

// Bootstrap application
naStorage.initialize();
naPlayer.initialize();
naPlayerStorage.initialize();

naSettings.subscribe('websiteTheme', (value) => {
  if (value === 'dark') {
    document.documentElement.classList.remove('na-light');
    document.documentElement.classList.add('na-dark');
  } else if (value === 'light') {
    document.documentElement.classList.add('na-light');
    document.documentElement.classList.remove('na-dark');
  } else {
    document.documentElement.classList.remove('na-light');
    document.documentElement.classList.remove('na-dark');
  }
});

// Register service worker
(async () => {
  if ('serviceWorker' in navigator) {
    await navigator.serviceWorker.register('/service-worker.js');
  }
})();
