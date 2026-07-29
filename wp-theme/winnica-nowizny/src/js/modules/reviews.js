/**
 * Reviews slider.
 *
 * The track scrolls on its own: scroll-snap does the positioning, a finger or a
 * trackpad does the moving. This module adds the two things markup alone cannot
 * do, a pair of arrows and the fold on longer reviews, and it adds both at
 * runtime so a visitor without JavaScript never sees a control that would do
 * nothing: no arrows, and every review shown whole.
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

  // ── Longer reviews ──
  // Six lines by default, the rest folded away and opened in place. The cut and
  // the ellipsis are line-clamp's; nothing here touches the text node, so a
  // visitor still selects, copies and finds the whole review.
  const excerptSetup = () => {
    const texts = [...track.querySelectorAll('.review-text')];
    if (!texts.length) return () => {};

    root.classList.add('has-excerpt');

    // line-clamp itself cannot be animated, so the height travels on max-height
    // between two measured ends. Setting it back to '' at the finish hands the
    // box back to layout, which matters because a resize rewraps the lines.
    const resize = (item, open, instant) => {
      const { text } = item;
      const from = text.getBoundingClientRect().height;

      item.open = open;
      text.style.maxHeight = '';

      // Both ends, measured: with the clamp on the box is six lines, with it off
      // it is the whole review. Two forced layouts on a click, which is nothing.
      text.classList.remove('is-open');
      const closed = text.clientHeight;
      text.classList.add('is-open');
      const to = open ? text.clientHeight : closed;

      if (instant || motion.matches || Math.abs(to - from) < 1) {
        text.classList.toggle('is-open', open);
        return;
      }

      // The clamp stays off for the whole gesture, collapsing included. Putting
      // it back first would shrink the box to six lines before the animation
      // started, leaving max-height nothing to travel over and the fold
      // snapping shut. It goes back on at the finish instead.
      text.style.maxHeight = `${from}px`;
      void text.offsetHeight;
      text.style.maxHeight = `${to}px`;
    };

    const items = texts.map((text) => {
      const author = text.closest('.review-card').querySelector('.review-name');
      const name = author ? author.textContent.trim() : 'gość';
      const button = document.createElement('button');
      const item = { text, button, open: false };

      button.type = 'button';
      button.className = 'review-more';
      button.textContent = 'Czytaj więcej';
      item.label = () => {
        button.textContent = item.open ? 'Zwiń' : 'Czytaj więcej';
        button.setAttribute('aria-expanded', String(item.open));
        // Sam napis powtarza sie pod kazda kolumna, wiec etykieta niesie autora:
        // czytnik ekranu wymienia przyciski jeden po drugim, poza kontekstem.
        button.setAttribute('aria-label', item.open ? `Zwiń opinię, ${name}` : `Czytaj całą opinię, ${name}`);
      };
      item.label();

      button.addEventListener('click', () => {
        resize(item, !item.open, false);
        item.label();
      });

      // Only the animation runs on max-height, so any transition ending here is
      // that one. Reading item.open rather than a captured direction keeps a
      // late event from a double click landing on the state that won.
      text.addEventListener('transitionend', (event) => {
        if (event.propertyName !== 'max-height') return;
        text.classList.toggle('is-open', item.open);
        text.style.maxHeight = '';
      });

      text.after(button);
      return item;
    });

    // Narrower cards wrap the same review into more lines, so which reviews are
    // long enough to fold changes with the viewport. Measuring needs the text
    // clamped, so an open one is closed and reopened without animating.
    return () => {
      items.forEach((item) => {
        const wasOpen = item.open;
        if (wasOpen) resize(item, false, true);

        item.button.hidden = item.text.scrollHeight <= item.text.clientHeight + 1;

        if (wasOpen && !item.button.hidden) resize(item, true, true);
        item.label();
      });
    };
  };

  const refreshExcerpts = excerptSetup();

  prev.addEventListener('click', () => step(-1));
  next.addEventListener('click', () => step(1));
  track.addEventListener('scroll', sync, { passive: true });
  ['wheel', 'touchstart', 'keydown'].forEach((event) => {
    track.addEventListener(event, release, { passive: true });
  });
  window.addEventListener('resize', () => { release(); refreshExcerpts(); sync(); });

  dragSetup();
  refreshExcerpts();
  sync();
};

export function initReviews() {
  document.querySelectorAll('[data-reviews]').forEach(setup);
}
