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

export function initConsent() {
  const banner = document.getElementById('consentBanner');
  if (!banner) return;

  const analyticsId = banner.dataset.analyticsId || '';
  const stored = window.localStorage.getItem(STORAGE_KEY);

  if (stored === 'granted') loadAnalytics(analyticsId);
  if (!stored) banner.hidden = false;

  banner.querySelectorAll('[data-consent]').forEach((button) => {
    button.addEventListener('click', () => {
      const choice = button.dataset.consent;
      window.localStorage.setItem(STORAGE_KEY, choice);
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
