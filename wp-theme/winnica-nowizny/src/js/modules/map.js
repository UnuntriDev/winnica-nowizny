export function initMap() {
  document.querySelectorAll('[data-map]').forEach((frame) => {
    const template = frame.querySelector('[data-map-embed]');
    const button = frame.querySelector('[data-map-load]');
    if (!template || !button) return;

    button.addEventListener('click', () => {
      frame.replaceChildren(template.content.cloneNode(true));

      // The click was the only way in, so hand focus to what it opened.
      const embed = frame.querySelector('iframe');
      if (embed) {
        embed.tabIndex = 0;
        embed.focus();
      }
    });
  });
}
