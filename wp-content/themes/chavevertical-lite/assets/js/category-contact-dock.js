/* Toque e teclado: <details> nativo. Desktop: expansão no hover sem bloquear cliques. */
(() => {
  const setup = () => {
    document.querySelectorAll('[data-cv-category-contact-dock]').forEach((dock) => {
      if (dock.dataset.cvDockReady === '1') return;
      dock.dataset.cvDockReady = '1';
      const items = Array.from(dock.querySelectorAll('.cv-dock-item'));
      const supportsHover = window.matchMedia('(hover:hover) and (pointer:fine)');
      const closeOthers = (active) => {
        items.forEach((item) => { if (item !== active) item.open = false; });
      };
      items.forEach((item) => {
        item.addEventListener('pointerenter', (event) => {
          if (!supportsHover.matches || event.pointerType !== 'mouse') return;
          closeOthers(item);
          item.open = true;
        });
        item.addEventListener('pointerleave', (event) => {
          if (event.pointerType === 'mouse' && !item.contains(document.activeElement)) item.open = false;
        });
        item.addEventListener('toggle', () => {
          if (item.open) closeOthers(item);
        });
        item.addEventListener('focusout', (event) => {
          if (item.contains(event.relatedTarget)) return;
          setTimeout(() => {
            if (!item.contains(document.activeElement) && !(supportsHover.matches && item.matches(':hover'))) item.open = false;
          }, 0);
        });
      });
      document.addEventListener('pointerdown', (event) => {
        if (!dock.contains(event.target)) closeOthers(null);
      });
      document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const expanded = items.find((item) => item.open);
        if (!expanded) return;
        const focused = expanded.contains(document.activeElement);
        closeOthers(null);
        if (focused) {
          event.preventDefault();
          expanded.querySelector('summary')?.focus({preventScroll:true});
        }
      });
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup, {once:true});
  else setup();
  document.addEventListener('astro:page-load', setup);
})();
