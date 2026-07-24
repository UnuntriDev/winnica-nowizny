function initScrollSpy(links) {
  if (!links || !('IntersectionObserver' in window)) return;

  const anchors = Array.from(links.querySelectorAll('a[href*="#"]'))
    .filter(a => !a.classList.contains('nav-social-link'));

  const map = new Map();
  anchors.forEach(anchor => {
    const hash = new URL(anchor.href, window.location.href).hash;
    if (!hash || hash === '#') return;
    const section = document.querySelector(hash);
    if (section) map.set(section, anchor);
  });

  if (!map.size) return;

  const setActive = (anchor) => {
    anchors.forEach(a => a.classList.toggle('is-active', a === anchor));
  };

  const observer = new IntersectionObserver((entries) => {
    entries
      .filter(entry => entry.isIntersecting)
      .forEach(entry => setActive(map.get(entry.target)));
  }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

  map.forEach((_, section) => observer.observe(section));
}

export function initNav() {
  const nav = document.getElementById('siteNav');
  const toggle = document.getElementById('navToggle');
  const links = document.getElementById('navLinks');
  const mobileQuery = window.matchMedia('(max-width: 768px)');

  if (!nav) return;

  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 60);
  }, { passive: true });

  initScrollSpy(links);

  if (toggle && links) {
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let isOpen = false;

    const getMenuItems = () => Array.from(links.querySelectorAll(focusableSelector));

    const setScrollLock = (locked) => {
      document.documentElement.classList.toggle('menu-open', locked);
      document.body.classList.toggle('menu-open', locked);
    };

    const closeMenu = (restoreFocus = true) => {
      if (!isOpen) return;

      isOpen = false;
      toggle.classList.remove('open');
      links.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Otwórz menu');
      setScrollLock(false);

      if (restoreFocus) {
        toggle.focus({ preventScroll: true });
      }
    };

    const openMenu = () => {
      if (isOpen || !mobileQuery.matches) return;

      isOpen = true;
      toggle.classList.add('open');
      links.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Zamknij menu');
      setScrollLock(true);

      const firstMenuItem = getMenuItems()[0];
      if (firstMenuItem) {
        firstMenuItem.focus({ preventScroll: true });
      }
    };

    toggle.addEventListener('click', () => {
      if (isOpen) {
        closeMenu();
      } else {
        openMenu();
      }
    });

    links.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        const destination = new URL(link.href, window.location.href);
        const isSamePageAnchor = destination.origin === window.location.origin
          && destination.pathname === window.location.pathname
          && destination.search === window.location.search
          && Boolean(destination.hash);

        closeMenu();

        if (isSamePageAnchor) {
          window.requestAnimationFrame(() => {
            toggle.focus({ preventScroll: true });
          });
        }
      });
    });

    document.addEventListener('keydown', (event) => {
      if (!isOpen) return;

      if (event.key === 'Escape') {
        event.preventDefault();
        closeMenu();
        return;
      }

      if (event.key !== 'Tab') return;

      const focusableItems = [toggle, ...getMenuItems()];
      const firstItem = focusableItems[0];
      const lastItem = focusableItems[focusableItems.length - 1];
      const activeItem = document.activeElement;

      if (event.shiftKey && (activeItem === firstItem || !focusableItems.includes(activeItem))) {
        event.preventDefault();
        lastItem.focus();
      } else if (!event.shiftKey && activeItem === lastItem) {
        event.preventDefault();
        firstItem.focus();
      }
    });

    const handleViewportChange = (event) => {
      if (!event.matches) closeMenu(false);
    };

    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', handleViewportChange);
    } else {
      mobileQuery.addListener(handleViewportChange);
    }
  }
}
