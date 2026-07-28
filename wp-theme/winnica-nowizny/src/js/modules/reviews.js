/**
 * Reviews slider.
 *
 * The track scrolls on its own: scroll-snap does the positioning, a finger or a
 * trackpad does the moving. This module only adds what a mouse alone cannot do,
 * which is a pair of arrows, and it adds them at runtime so a visitor without
 * JavaScript never sees controls that would do nothing.
 */

const setup = (root) => {
  const track = root.querySelector('[data-reviews-track]');
  const nav = root.querySelector('[data-reviews-nav]');
  const prev = root.querySelector('[data-reviews-prev]');
  const next = root.querySelector('[data-reviews-next]');
  if (!track || !nav || !prev || !next) return;

  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');

  // Where the last click asked the track to end up, or null when it is wherever
  // the visitor left it. See step() for why the intent has to be remembered.
  let target = null;

  // One card plus one gap. Read live rather than cached: the card width is a
  // percentage of the track, so it changes with every resize and breakpoint.
  const stepWidth = () => {
    const card = track.querySelector('.review-slide');
    if (!card) return track.clientWidth;
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    return card.getBoundingClientRect().width + gap;
  };

  const sync = () => {
    // Sub-pixel widths mean scrollLeft rarely lands exactly on 0 or on the end,
    // so both edges get a pixel of slack; without it the arrow at the far end
    // stays enabled and clicking it does nothing.
    const furthest = track.scrollWidth - track.clientWidth;

    nav.hidden = furthest <= 1;
    prev.disabled = track.scrollLeft <= 1;
    next.disabled = track.scrollLeft >= furthest - 1;

    if (target !== null && Math.abs(track.scrollLeft - target) <= 1) target = null;
  };

  const step = (direction) => {
    const width = stepWidth();
    const furthest = track.scrollWidth - track.clientWidth;

    // Counting from scrollLeft would swallow clicks: while a smooth scroll is
    // still running, scrollLeft is a frame of the animation rather than where
    // the previous click was headed, so three quick clicks moved barely two
    // cards. Each click steps on from the last requested position instead.
    const from = target === null ? track.scrollLeft : target;
    const wanted = (Math.round(from / width) + direction) * width;

    target = Math.max(0, Math.min(furthest, wanted));
    track.scrollTo({ left: target, behavior: motion.matches ? 'auto' : 'smooth' });
  };

  // A hand on the track overrides whatever the arrows were aiming for.
  const release = () => { target = null; };

  // ── Drag ──
  // Only for mouse and pen. Touch already drags the track natively, and hooking
  // pointer events there would fight the browser's own scrolling.
  const dragSetup = () => {
    if (!window.matchMedia('(pointer: fine)').matches) return;

    let pointer = null;
    let originX = 0;
    let originScroll = 0;
    let dragging = false;

    track.classList.add('has-drag');

    track.addEventListener('pointerdown', (event) => {
      if (event.button !== 0 || event.target.closest('a, button')) return;
      pointer = event.pointerId;
      originX = event.clientX;
      originScroll = track.scrollLeft;
      dragging = false;
    });

    track.addEventListener('pointermove', (event) => {
      if (event.pointerId !== pointer) return;
      const travelled = event.clientX - originX;

      // Below the threshold this is still a click, so selecting a sentence to
      // copy keeps working; past it the gesture commits to being a drag.
      if (!dragging) {
        if (Math.abs(travelled) < 5) return;
        dragging = true;
        release();
        track.setPointerCapture(pointer);
        // Snapping mid-gesture yanks the track out from under the cursor; it
        // comes back on release and settles the cards onto their positions.
        track.classList.add('is-dragging');
        window.getSelection()?.removeAllRanges();
      }

      track.scrollLeft = originScroll - travelled;
    });

    const stop = (event) => {
      if (event.pointerId !== pointer) return;
      if (dragging) track.classList.remove('is-dragging');
      pointer = null;
      dragging = false;
    };

    track.addEventListener('pointerup', stop);
    track.addEventListener('pointercancel', stop);
  };

  // ── Clamp ──
  // Reviews run from four lines to eight. Left alone the longest one sets the
  // height of every card and the short ones fill the difference with nothing, so
  // the text is capped at five lines and the rest goes behind a toggle.
  const clampSetup = () => {
    const texts = [...track.querySelectorAll('.review-text')];
    if (!texts.length) return () => {};

    // The cap is a class rather than a default so that a visitor without
    // JavaScript keeps the whole review: taller cards beat unreachable text.
    root.classList.add('has-clamp');

    const toggles = texts.map((text) => {
      const card = text.closest('.review-card');
      const button = document.createElement('button');

      button.type = 'button';
      button.className = 'review-more';
      button.textContent = 'Czytaj więcej';
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-controls', text.id);

      button.addEventListener('click', () => {
        const open = card.classList.toggle('is-expanded');
        button.textContent = open ? 'Zwiń' : 'Czytaj więcej';
        button.setAttribute('aria-expanded', String(open));
      });

      text.after(button);
      return { text, card, button };
    });

    // Narrower cards wrap the same review into more lines, so which reviews need
    // a toggle changes with the viewport. An open card is left alone: the reader
    // asked for it, and re-measuring would mean collapsing it under their eyes.
    return () => {
      toggles.forEach(({ text, card, button }) => {
        if (card.classList.contains('is-expanded')) return;
        button.hidden = text.scrollHeight <= text.clientHeight + 1;
      });
    };
  };

  const refreshClamp = clampSetup();

  prev.addEventListener('click', () => step(-1));
  next.addEventListener('click', () => step(1));
  track.addEventListener('scroll', sync, { passive: true });
  ['wheel', 'touchstart', 'keydown'].forEach((event) => {
    track.addEventListener(event, release, { passive: true });
  });
  window.addEventListener('resize', () => { release(); refreshClamp(); sync(); });

  dragSetup();
  refreshClamp();
  sync();
};

export function initReviews() {
  document.querySelectorAll('[data-reviews]').forEach(setup);
}
