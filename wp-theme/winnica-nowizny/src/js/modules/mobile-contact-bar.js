/**
 * Reveals the compact contact bar between the hero and footer. Intersection
 * observers own the scroll-dependent state, while class changes cover modal UI
 * such as the mobile menu and gallery lightbox.
 */

export function initMobileContactBar() {
  const bar = document.querySelector('[data-mobile-contact-bar]');
  if (!bar) return;

  const links = [...bar.querySelectorAll('[data-mobile-contact-link]')];
  const trigger = document.querySelector('[data-mobile-cta-trigger]');
  const hero = document.querySelector('.hero');
  const footer = document.querySelector('footer.site-footer');
  const startBoundary = hero || trigger;
  const mobile = window.matchMedia('(max-width: 768px)');
  let heroHasPassed = false;
  let footerIsVisible = false;

  const overlayOpen = () => document.documentElement.classList.contains('menu-open')
    || document.body.classList.contains('lightbox-open');

  const sync = () => {
    const visible = mobile.matches && heroHasPassed && !footerIsVisible && !overlayOpen();

    bar.classList.toggle('is-visible', visible);
    bar.setAttribute('aria-hidden', String(!visible));
    links.forEach((link) => {
      if (visible) link.removeAttribute('tabindex');
      else link.setAttribute('tabindex', '-1');
    });
  };

  if (!startBoundary || !('IntersectionObserver' in window)) {
    sync();
    return;
  }

  const visibilityObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.target === startBoundary) {
        heroHasPassed = !entry.isIntersecting && entry.boundingClientRect.bottom <= 0;
      } else if (entry.target === footer) {
        footerIsVisible = entry.isIntersecting;
      }
    });

    sync();
  }, { threshold: 0 });

  visibilityObserver.observe(startBoundary);
  if (footer) visibilityObserver.observe(footer);

  if (typeof mobile.addEventListener === 'function') {
    mobile.addEventListener('change', sync);
  } else {
    mobile.addListener(sync);
  }

  const classObserver = new MutationObserver(sync);
  classObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  classObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });

  document.documentElement.classList.add('mobile-cta-ready');
  sync();
}
