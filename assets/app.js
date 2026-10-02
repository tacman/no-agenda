import '@fortawesome/fontawesome-free/css/all.min.css';
import './stimulus_bootstrap.js';
import naPlayer from './services/player.js';
import naPlayerStorage from './services/player-storage.js';
import naSettings from './services/settings.js';
import naStorage from './services/storage.js';

// Include scripts
import './scripts/clipboard.js';
import './scripts/swup.js';

// Include web components
import './lib/octopod/player.js';

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
    if (window.naDebug) {
      // Keep development requests on the live AssetMapper pipeline.
      const registration = await navigator.serviceWorker.getRegistration('/');
      await registration?.unregister();
    } else {
      await navigator.serviceWorker.register('/service-worker.js');
    }
  }
})();
