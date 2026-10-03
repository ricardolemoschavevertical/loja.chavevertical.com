(() => {
  const toggle = document.querySelector('.cvl-menu-toggle');
  const nav = document.querySelector('.cvl-nav');

  if (!toggle || !nav) return;

  toggle.addEventListener('click', () => {
    const open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('cvl-menu-open', open);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('cvl-menu-open');
    }
  });
})();
