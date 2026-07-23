function createGalleryButton(item) {
  const button = document.createElement('button');

  for (const attribute of item.attributes) {
    button.setAttribute(attribute.name, attribute.value);
  }

  button.type = 'button';
  button.classList.add('gallery-item--interactive');
  button.setAttribute('aria-label', `Powiększ zdjęcie: ${item.dataset.galleryAlt || 'zdjęcie z galerii'}`);

  while (item.firstChild) {
    button.appendChild(item.firstChild);
  }

  item.replaceWith(button);
  return button;
}

export function initLightbox() {
  const sourceItems = [...document.querySelectorAll('[data-gallery-item]')];
  const modal = document.querySelector('[data-gallery-lightbox]');

  if (!sourceItems.length || !modal) return;

  const items = sourceItems.map(createGalleryButton);
  const imageHost = modal.querySelector('[data-lightbox-image-host]');
  const caption = modal.querySelector('[data-lightbox-caption]');
  const counter = modal.querySelector('[data-lightbox-counter]');
  const closeButton = modal.querySelector('[data-lightbox-close]');
  const previousButton = modal.querySelector('[data-lightbox-prev]');
  const nextButton = modal.querySelector('[data-lightbox-next]');
  const focusable = [closeButton, previousButton, nextButton];
  let activeIndex = 0;
  let returnFocus = null;
  let image = null;

  const getImage = () => {
    if (!image) {
      image = document.createElement('img');
      imageHost.appendChild(image);
    }
    return image;
  };

  const render = (index) => {
    activeIndex = (index + items.length) % items.length;
    const item = items[activeIndex];
    const alt = item.dataset.galleryAlt || item.querySelector('img')?.alt || '';
    const src = item.dataset.gallerySrc || item.querySelector('img')?.currentSrc || item.querySelector('img')?.src;

    const activeImage = getImage();
    activeImage.src = src;
    activeImage.alt = alt;
    caption.textContent = alt;
    counter.textContent = `${activeIndex + 1} / ${items.length}`;
  };

  const close = () => {
    modal.hidden = true;
    document.body.classList.remove('lightbox-open');
    returnFocus?.focus();
  };

  const open = (index, trigger) => {
    returnFocus = trigger;
    render(index);
    modal.hidden = false;
    document.body.classList.add('lightbox-open');
    closeButton.focus();
  };

  const handleKeydown = (event) => {
    if (modal.hidden) return;

    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }

    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      render(activeIndex - 1);
      return;
    }

    if (event.key === 'ArrowRight') {
      event.preventDefault();
      render(activeIndex + 1);
      return;
    }

    if (event.key === 'Tab') {
      const currentIndex = focusable.indexOf(document.activeElement);
      const nextIndex = event.shiftKey
        ? (currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1)
        : (currentIndex === focusable.length - 1 ? 0 : currentIndex + 1);

      event.preventDefault();
      focusable[nextIndex].focus();
    }
  };

  items.forEach((item, index) => {
    item.addEventListener('click', () => open(index, item));
  });

  closeButton.addEventListener('click', close);
  previousButton.addEventListener('click', () => render(activeIndex - 1));
  nextButton.addEventListener('click', () => render(activeIndex + 1));
  modal.addEventListener('click', (event) => {
    if (event.target === modal) close();
  });
  document.addEventListener('keydown', handleKeydown);
}
