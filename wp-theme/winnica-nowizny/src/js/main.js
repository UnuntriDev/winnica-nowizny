import '../css/main.css';
import { initNav } from './modules/nav.js';
import { initReveal } from './modules/reveal.js';
import { initConsent } from './modules/consent.js';
import { initLightbox } from './modules/lightbox.js';
import { initMap } from './modules/map.js';
import { initDatepicker } from './modules/datepicker.js';

const modules = [
  ['nav', initNav],
  ['reveal', initReveal],
  ['consent', initConsent],
  ['lightbox', initLightbox],
  ['map', initMap],
  ['datepicker', initDatepicker],
];

document.addEventListener('DOMContentLoaded', () => {
  modules.forEach(([name, init]) => {
    try {
      init();
    } catch (error) {
      // One module throwing must not stop the ones queued behind it.
      console.error(`[winnica] module "${name}" failed to start`, error);
    }
  });
});
