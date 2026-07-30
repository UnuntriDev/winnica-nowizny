export function initReveal() {
  if (!('IntersectionObserver' in window)) {
    return;
  }

  // const, nie var: callback wykonuje sie dopiero po przypisaniu, wiec
  // odwolanie do observer w jego wnetrzu jest bezpieczne.
  const observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.05, rootMargin: '0px 0px -20px 0px' });

  function observeReveals() {
    document.querySelectorAll('.reveal:not(.visible)').forEach(function (el) {
      observer.observe(el);
    });
  }

  document.documentElement.classList.add('reveal-ready');
  observeReveals();
  window.addEventListener('load', observeReveals);
}
