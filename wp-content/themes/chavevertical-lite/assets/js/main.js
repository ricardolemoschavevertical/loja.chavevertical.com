(() => {
  const menuToggle = document.querySelector('.cvl-menu-toggle');
  const nav = document.querySelector('.cvl-nav');

  if (menuToggle && nav) {
    menuToggle.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      menuToggle.setAttribute('aria-expanded', String(open));
      document.body.classList.toggle('cvl-menu-open', open);
    });
  }

  const drawer = document.querySelector('.cvl-category-drawer');
  const drawerTrigger = document.querySelector('.cvl-categories-trigger');
  const drawerClose = document.querySelector('.cvl-category-drawer-close');
  const drawerOverlay = document.querySelector('.cvl-category-overlay');

  const openDrawer = () => {
    if (!drawer || !drawerTrigger || !drawerOverlay) return;

    if (nav && menuToggle) {
      nav.classList.remove('is-open');
      menuToggle.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('cvl-menu-open');
    }

    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    drawerTrigger.setAttribute('aria-expanded', 'true');
    drawerOverlay.hidden = false;
    requestAnimationFrame(() => drawerOverlay.classList.add('is-visible'));
    document.body.classList.add('cvl-category-drawer-open');
  };

  const closeDrawer = () => {
    if (!drawer || !drawerTrigger || !drawerOverlay) return;

    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    drawerTrigger.setAttribute('aria-expanded', 'false');
    drawerOverlay.classList.remove('is-visible');
    document.body.classList.remove('cvl-category-drawer-open');

    window.setTimeout(() => {
      if (!drawerOverlay.classList.contains('is-visible')) {
        drawerOverlay.hidden = true;
      }
    }, 180);
  };

  if (drawerTrigger) {
    drawerTrigger.addEventListener('click', openDrawer);
  }

  if (drawerClose) {
    drawerClose.addEventListener('click', closeDrawer);
  }

  if (drawerOverlay) {
    drawerOverlay.addEventListener('click', closeDrawer);
  }

  document.querySelectorAll('.cvl-category-expand').forEach((button) => {
    button.addEventListener('click', () => {
      const item = button.closest('.cvl-category-drawer-item');
      const children = item?.querySelector(':scope > .cvl-category-drawer-children');

      if (!item || !children) return;

      const willOpen = !item.classList.contains('is-expanded');

      item.classList.toggle('is-expanded', willOpen);
      button.setAttribute('aria-expanded', String(willOpen));
      children.hidden = !willOpen;
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    if (nav && menuToggle) {
      nav.classList.remove('is-open');
      menuToggle.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('cvl-menu-open');
    }

    closeDrawer();
  });
})();
