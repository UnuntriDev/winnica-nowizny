export function initContactStatus() {
  const notice = document.querySelector('[data-clear-contact-status]');

  if (!notice || typeof window.history.replaceState !== 'function') return;

  const url = new URL(window.location.href);
  if (url.searchParams.get('contact') !== 'success') return;

  url.searchParams.delete('contact');
  window.history.replaceState(
    window.history.state,
    '',
    `${url.pathname}${url.search}${url.hash}`,
  );
}
