const STORAGE_KEY = 'winnica_analytics_consent_v1';

function loadAnalytics(id) {
  if (!id || document.querySelector('script[data-winnica-analytics]')) return;

  window.dataLayer = window.dataLayer || [];
  window.gtag = function gtag() { window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  window.gtag('config', id, { anonymize_ip: true, allow_google_signals: false });

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`;
  script.dataset.winnicaAnalytics = 'true';
  document.head.appendChild(script);
}

// Blocked storage (Safari private mode, strict cookie settings) throws on access
// rather than returning null, so every call has to be guarded.
function readChoice() {
  try {
    return window.localStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

function storeChoice(choice) {
  try {
    window.localStorage.setItem(STORAGE_KEY, choice);
  } catch {
    // Nothing to remember it in; the choice still applies to this page view.
  }
}

export function initConsent() {
  const banner = document.getElementById('consentBanner');
  if (!banner) return;

  const analyticsId = banner.dataset.analyticsId || '';
  const stored = readChoice();

  if (stored === 'granted') loadAnalytics(analyticsId);
  if (!stored) banner.hidden = false;

  banner.querySelectorAll('[data-consent]').forEach((button) => {
    button.addEventListener('click', () => {
      const choice = button.dataset.consent;
      storeChoice(choice);
      banner.hidden = true;
      if (choice === 'granted') loadAnalytics(analyticsId);
    });
  });

  document.querySelectorAll('[data-open-consent]').forEach((button) => {
    button.addEventListener('click', () => {
      banner.hidden = false;
      banner.querySelector('[data-consent="granted"]')?.focus();
    });
  });
}
