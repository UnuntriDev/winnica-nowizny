const STORAGE_KEY = 'winnica_analytics_consent_v2';
const LEGACY_STORAGE_KEY = 'winnica_analytics_consent_v1';
const CONSENT_VERSION = 2;
const CONSENT_MAX_AGE = 180 * 24 * 60 * 60 * 1000;

const deniedConsent = {
  ad_storage: 'denied',
  ad_user_data: 'denied',
  ad_personalization: 'denied',
  analytics_storage: 'denied',
};

function ensureConsentApi() {
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() { window.dataLayer.push(arguments); };

  if (!window.winnicaConsentDefaultsSet) {
    window.gtag('consent', 'default', { ...deniedConsent, wait_for_update: 500 });
    window.winnicaConsentDefaultsSet = true;
  }
}

function updateConsent(choice) {
  ensureConsentApi();
  window.gtag('consent', 'update', {
    ...deniedConsent,
    analytics_storage: choice === 'granted' ? 'granted' : 'denied',
  });
}

function loadAnalytics(id) {
  if (!id) return;

  ensureConsentApi();
  window[`ga-disable-${id}`] = false;
  updateConsent('granted');

  if (!window.winnicaAnalyticsConfigured) {
    window.gtag('js', new Date());
    window.gtag('config', id, {
      anonymize_ip: true,
      allow_google_signals: false,
      allow_ad_personalization_signals: false,
    });
    window.winnicaAnalyticsConfigured = true;
  }

  if (document.querySelector('script[data-winnica-analytics]')) return;

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`;
  script.dataset.winnicaAnalytics = 'true';
  document.head.appendChild(script);
}

function analyticsCookieNames() {
  return document.cookie
    .split(';')
    .map((cookie) => cookie.split('=')[0].trim())
    .filter((name) => name === '_ga' || name.startsWith('_ga_') || name === '_gid' || name === '_gat');
}

function removeAnalyticsCookies() {
  const hostnameParts = window.location.hostname.split('.');
  const domains = new Set(['']);

  for (let index = 0; index < hostnameParts.length - 1; index += 1) {
    const domain = hostnameParts.slice(index).join('.');
    domains.add(domain);
    domains.add(`.${domain}`);
  }

  analyticsCookieNames().forEach((name) => {
    domains.forEach((domain) => {
      const domainPart = domain ? `; domain=${domain}` : '';
      document.cookie = `${name}=; Max-Age=0; path=/${domainPart}; SameSite=Lax`;
    });
  });
}

function disableAnalytics(id) {
  updateConsent('denied');
  if (id) window[`ga-disable-${id}`] = true;
  removeAnalyticsCookies();
}

// Blocked storage (Safari private mode, strict cookie settings) throws on access
// rather than returning null, so every call has to be guarded.
function readChoice() {
  try {
    window.localStorage.removeItem(LEGACY_STORAGE_KEY);
    const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || 'null');

    if (
      !stored
      || stored.version !== CONSENT_VERSION
      || !['granted', 'denied'].includes(stored.choice)
      || !Number.isFinite(stored.timestamp)
      || Date.now() - stored.timestamp > CONSENT_MAX_AGE
    ) {
      window.localStorage.removeItem(STORAGE_KEY);
      return null;
    }

    return stored.choice;
  } catch {
    return null;
  }
}

function storeChoice(choice) {
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify({
      choice,
      timestamp: Date.now(),
      version: CONSENT_VERSION,
    }));
  } catch {
    // Nothing to remember it in; the choice still applies to this page view.
  }
}

function syncButtons(panel, choice) {
  panel.querySelectorAll('[data-consent]').forEach((button) => {
    button.setAttribute('aria-pressed', String(button.dataset.consent === choice));
  });
}

export function initConsent() {
  const widget = document.getElementById('consent-widget');
  const panel = document.getElementById('consent-panel');
  const toggle = widget?.querySelector('[data-consent-toggle]');
  if (!widget || !panel || !toggle) return;

  const analyticsId = widget.dataset.analyticsId || '';
  const stored = readChoice();
  let settingsTrigger = null;

  ensureConsentApi();
  syncButtons(panel, stored);
  widget.dataset.consentState = stored || 'unset';
  panel.hidden = false;
  // Zamkniety panel zostaje poza kolejnoscia tabulacji. Samo CSS nie wystarcza:
  // po pierwszym otwarciu i zamknieciu opozniona zmiana visibility nie wraca do
  // hidden, wiec przyciski panelu lapaly focus, mimo ze byly niewidoczne.
  panel.inert = true;

  if (stored === 'granted') {
    loadAnalytics(analyticsId);
  } else {
    disableAnalytics(analyticsId);
  }

  const setExpandedState = (expanded) => {
    document.querySelectorAll('[data-open-consent]').forEach((button) => {
      button.setAttribute('aria-expanded', String(expanded));
    });
    toggle.setAttribute('aria-label', expanded ? 'Zwiń ustawienia cookies' : 'Otwórz ustawienia cookies');
  };

  const openPanel = (trigger, { moveFocus = true } = {}) => {
    settingsTrigger = trigger;
    panel.inert = false;
    panel.setAttribute('aria-hidden', 'false');
    panel.classList.add('is-open');
    setExpandedState(true);
    if (moveFocus) panel.focus({ preventScroll: true });
  };

  const closePanel = ({ restoreFocus = true } = {}) => {
    panel.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    setExpandedState(false);
    // Focus wychodzi z panelu przed inert, inaczej przegladarka rzuca go na body.
    if (restoreFocus) (settingsTrigger || toggle).focus({ preventScroll: true });
    panel.inert = true;
    settingsTrigger = null;
  };

  panel.querySelectorAll('[data-consent]').forEach((button) => {
    button.addEventListener('click', () => {
      const choice = button.dataset.consent;
      storeChoice(choice);
      syncButtons(panel, choice);
      widget.dataset.consentState = choice;
      if (choice === 'granted') {
        loadAnalytics(analyticsId);
      } else {
        disableAnalytics(analyticsId);
      }
      closePanel();
    });
  });

  document.querySelectorAll('[data-open-consent]').forEach((button) => {
    button.addEventListener('click', () => {
      if (button === toggle && panel.classList.contains('is-open')) {
        closePanel();
        return;
      }
      openPanel(button);
    });
  });

  panel.querySelectorAll('[data-consent-close]').forEach((button) => {
    button.addEventListener('click', () => closePanel());
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && panel.classList.contains('is-open')) {
      event.preventDefault();
      closePanel();
    }
  });

  if (!stored) {
    // Nobody has been told about the cookies yet, and an icon in the corner is
    // not a notice. Short delay so the panel slides in over a settled page
    // instead of snapping open mid-load, and no focus grab: moving the caret
    // out from under someone who just started reading is worse than the icon.
    window.setTimeout(() => openPanel(null, { moveFocus: false }), 900);
  }
}
