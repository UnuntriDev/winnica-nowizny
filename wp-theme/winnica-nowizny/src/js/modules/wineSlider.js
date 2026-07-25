// Wine collection slider. The track scrolls natively (touch + wheel) with CSS
// scroll-snap; this adds arrow buttons, mouse drag and keyboard navigation as
// progressive enhancement. No dependencies.
export function initWineSlider() {
  document.querySelectorAll('[data-wine-slider]').forEach(setupSlider);
}

function setupSlider(root) {
  const track = root.querySelector('.wine-track');
  if (!track) return;

  const header = root.previousElementSibling;
  const prev = header ? header.querySelector('[data-dir="prev"]') : null;
  const next = header ? header.querySelector('[data-dir="next"]') : null;

  const stepSize = () => {
    const slide = track.querySelector('.wine-slide');
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    return slide ? slide.getBoundingClientRect().width + gap : track.clientWidth * 0.8;
  };

  const scrollByStep = (dir) => {
    track.scrollBy({ left: dir * stepSize(), behavior: 'smooth' });
  };

  const updateControls = () => {
    const max = track.scrollWidth - track.clientWidth - 1;
    if (prev) prev.toggleAttribute('disabled', track.scrollLeft <= 0);
    if (next) next.toggleAttribute('disabled', track.scrollLeft >= max);
  };

  if (prev) {
    prev.removeAttribute('tabindex');
    prev.addEventListener('click', () => scrollByStep(-1));
  }
  if (next) {
    next.removeAttribute('tabindex');
    next.addEventListener('click', () => scrollByStep(1));
  }

  track.addEventListener('scroll', updateControls, { passive: true });
  window.addEventListener('resize', updateControls);

  track.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowRight') {
      event.preventDefault();
      scrollByStep(1);
    } else if (event.key === 'ArrowLeft') {
      event.preventDefault();
      scrollByStep(-1);
    }
  });

  // Mouse drag-to-scroll (touch already scrolls natively).
  let dragging = false;
  let startX = 0;
  let startLeft = 0;

  track.addEventListener('pointerdown', (event) => {
    if (event.pointerType !== 'mouse') return;
    dragging = true;
    startX = event.clientX;
    startLeft = track.scrollLeft;
    track.classList.add('is-grabbing');
  });

  track.addEventListener('pointermove', (event) => {
    if (!dragging) return;
    track.scrollLeft = startLeft - (event.clientX - startX);
  });

  const endDrag = () => {
    if (!dragging) return;
    dragging = false;
    track.classList.remove('is-grabbing');
  };

  track.addEventListener('pointerup', endDrag);
  track.addEventListener('pointercancel', endDrag);
  track.addEventListener('pointerleave', endDrag);

  updateControls();
}
