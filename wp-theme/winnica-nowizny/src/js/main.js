import '../css/main.css';
import { initNav } from './modules/nav.js';
import { initReveal } from './modules/reveal.js';
import { initConsent } from './modules/consent.js';
import { initLightbox } from './modules/lightbox.js';
import { initMap } from './modules/map.js';

document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initReveal();
  initConsent();
  initLightbox();
  initMap();
});
