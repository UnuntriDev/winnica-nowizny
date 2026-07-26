/**
 * Polish date picker for the reservation form.
 *
 * A native <input type="date"> renders in the browser's locale, not the page's,
 * so a visitor with an English profile sees 08/15/2026 on a Polish site and has
 * to guess which number is the month. This replaces it with a plain text field
 * (DD.MM.RRRR, weeks starting on Monday) plus a calendar popup, and the server
 * accepts what someone types by hand if the script never runs.
 */

const MONTHS = [
  'styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec',
  'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień',
];

// Spoken dates take the genitive: "15 sierpnia", never "15 sierpień".
const MONTHS_SPOKEN = [
  'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca',
  'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia',
];

const WEEKDAYS_SHORT = ['pon', 'wt', 'śr', 'czw', 'pt', 'sob', 'ndz'];
const WEEKDAYS_FULL = ['poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota', 'niedziela'];

const pad = (value) => String(value).padStart(2, '0');
const startOfDay = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());
const addDays = (date, count) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + count);

const addMonths = (date, count) => {
  const target = new Date(date.getFullYear(), date.getMonth() + count, 1);
  const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
  target.setDate(Math.min(date.getDate(), lastDay));
  return target;
};

const toDisplay = (date) => `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`;
const toKey = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

// Monday is column zero here; getDay() puts Sunday there.
const weekdayIndex = (date) => (date.getDay() + 6) % 7;

const spoken = (date) => `${date.getDate()} ${MONTHS_SPOKEN[date.getMonth()]} ${date.getFullYear()}, ${WEEKDAYS_FULL[weekdayIndex(date)]}`;

const parseDisplay = (value) => {
  const match = /^\s*(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{4})\s*$/.exec(value);
  if (!match) return null;

  const day = Number(match[1]);
  const month = Number(match[2]);
  const year = Number(match[3]);
  const date = new Date(year, month - 1, day);

  // Round-trip catches the impossible days: 31.04 rolls over to 1 May.
  return date.getDate() === day && date.getMonth() === month - 1 && date.getFullYear() === year
    ? date
    : null;
};

const setup = (root) => {
  const input = root.querySelector('input');
  if (!input) return;

  // Bounds live here rather than in a rendered attribute: the front page is
  // cached for up to an hour, so a server-stamped "tomorrow" can already be
  // yesterday by the time somebody opens the calendar.
  const min = addDays(startOfDay(new Date()), 1);
  const max = addMonths(startOfDay(new Date()), 24);
  const inRange = (date) => date >= min && date <= max;

  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'datepicker-toggle';
  toggle.setAttribute('aria-haspopup', 'dialog');
  toggle.setAttribute('aria-expanded', 'false');
  toggle.setAttribute('aria-label', 'Otwórz kalendarz');
  toggle.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg>';

  const popup = document.createElement('div');
  popup.className = 'datepicker-popup';
  popup.setAttribute('role', 'dialog');
  popup.setAttribute('aria-label', 'Wybierz datę wizyty');
  popup.hidden = true;
  popup.innerHTML = `
    <div class="datepicker-head">
      <button type="button" class="datepicker-nav" data-step="-1" aria-label="Poprzedni miesiąc">&lsaquo;</button>
      <p class="datepicker-title" aria-live="polite"></p>
      <button type="button" class="datepicker-nav" data-step="1" aria-label="Następny miesiąc">&rsaquo;</button>
    </div>
    <table class="datepicker-grid" role="grid">
      <thead>
        <tr>${WEEKDAYS_SHORT.map((short, i) => `<th scope="col"><abbr title="${WEEKDAYS_FULL[i]}">${short}</abbr></th>`).join('')}</tr>
      </thead>
      <tbody></tbody>
    </table>
  `;

  root.append(toggle, popup);

  const title = popup.querySelector('.datepicker-title');
  const body = popup.querySelector('tbody');
  const prev = popup.querySelector('[data-step="-1"]');
  const next = popup.querySelector('[data-step="1"]');

  let selected = parseDisplay(input.value);
  let cursor = selected && inRange(selected) ? selected : min;

  const render = () => {
    title.textContent = `${MONTHS[cursor.getMonth()]} ${cursor.getFullYear()}`;

    const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    let day = addDays(first, -weekdayIndex(first));
    const selectedKey = selected ? toKey(selected) : '';
    const cursorKey = toKey(cursor);
    let html = '';

    for (let week = 0; week < 6; week += 1) {
      html += '<tr>';
      for (let column = 0; column < 7; column += 1) {
        const outside = day.getMonth() !== cursor.getMonth();
        const disabled = outside || !inRange(day);
        const key = toKey(day);
        const classes = ['datepicker-day'];
        if (outside) classes.push('is-outside');
        if (key === selectedKey) classes.push('is-selected');

        html += `<td role="gridcell" class="${classes.join(' ')}"`
          + ` tabindex="${key === cursorKey ? '0' : '-1'}"`
          + (disabled ? ' aria-disabled="true"' : ` data-date="${key}"`)
          + ` aria-selected="${key === selectedKey ? 'true' : 'false'}"`
          + ` aria-label="${spoken(day)}">${day.getDate()}</td>`;

        day = addDays(day, 1);
      }
      html += '</tr>';
    }

    body.innerHTML = html;
    prev.disabled = new Date(cursor.getFullYear(), cursor.getMonth(), 0) < min;
    next.disabled = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1) > max;
  };

  const focusCursor = () => {
    const cell = body.querySelector('[tabindex="0"]');
    if (cell) cell.focus({ preventScroll: true });
  };

  const moveCursor = (date, { focus = true } = {}) => {
    // Never let the arrow keys walk out of the bookable window.
    cursor = date < min ? min : date > max ? max : date;
    render();
    if (focus) focusCursor();
  };

  const isOpen = () => !popup.hidden;

  const onOutside = (event) => {
    if (!root.contains(event.target)) close({ restoreFocus: false });
  };

  function open() {
    if (isOpen()) return;
    const typed = parseDisplay(input.value);
    selected = typed;
    cursor = typed && inRange(typed) ? typed : min;
    render();
    popup.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    document.addEventListener('pointerdown', onOutside);
    focusCursor();
  }

  function close({ restoreFocus = true } = {}) {
    if (!isOpen()) return;
    popup.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    document.removeEventListener('pointerdown', onOutside);
    if (restoreFocus) toggle.focus({ preventScroll: true });
  }

  const choose = (date) => {
    selected = date;
    input.value = toDisplay(date);
    input.removeAttribute('aria-invalid');
    close();
  };

  toggle.addEventListener('click', () => (isOpen() ? close() : open()));
  prev.addEventListener('click', () => moveCursor(addMonths(cursor, -1)));
  next.addEventListener('click', () => moveCursor(addMonths(cursor, 1)));

  body.addEventListener('click', (event) => {
    const cell = event.target.closest('[data-date]');
    if (!cell) return;
    const [year, month, dayOfMonth] = cell.dataset.date.split('-').map(Number);
    choose(new Date(year, month - 1, dayOfMonth));
  });

  popup.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.stopPropagation();
      close();
      return;
    }

    if (!event.target.closest('[role="gridcell"]')) return;

    const steps = {
      ArrowLeft: () => addDays(cursor, -1),
      ArrowRight: () => addDays(cursor, 1),
      ArrowUp: () => addDays(cursor, -7),
      ArrowDown: () => addDays(cursor, 7),
      Home: () => addDays(cursor, -weekdayIndex(cursor)),
      End: () => addDays(cursor, 6 - weekdayIndex(cursor)),
      PageUp: () => addMonths(cursor, event.shiftKey ? -12 : -1),
      PageDown: () => addMonths(cursor, event.shiftKey ? 12 : 1),
    };

    if (steps[event.key]) {
      event.preventDefault();
      moveCursor(steps[event.key]());
      return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      if (inRange(cursor)) choose(cursor);
    }
  });

  input.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isOpen()) close({ restoreFocus: false });
    if (event.key === 'ArrowDown' && event.altKey) {
      event.preventDefault();
      open();
    }
  });

  // Hand-typed "5.8.2026" is a valid date; leaving it ragged next to the
  // picker's own output just looks like the field stopped working.
  input.addEventListener('change', () => {
    const typed = parseDisplay(input.value);
    if (typed) {
      selected = typed;
      input.value = toDisplay(typed);
    }
  });

  render();
};

export function initDatepicker() {
  document.querySelectorAll('[data-datepicker]').forEach(setup);
}
