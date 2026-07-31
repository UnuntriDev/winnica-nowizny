import { initNav } from './modules/nav.js';
import { initReveal } from './modules/reveal.js';
import { initLightbox } from './modules/lightbox.js';
import { initDatepicker } from './modules/datepicker.js';
import { initReviews } from './modules/reviews.js';
import { initContactStatus } from './modules/contact-status.js';
import { initMobileContactBar } from './modules/mobile-contact-bar.js';

const modules = [
  ['nav', initNav],
  ['reveal', initReveal],
  ['lightbox', initLightbox],
  ['datepicker', initDatepicker],
  ['reviews', initReviews],
  ['contact-status', initContactStatus],
  ['mobile-contact-bar', initMobileContactBar],
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
