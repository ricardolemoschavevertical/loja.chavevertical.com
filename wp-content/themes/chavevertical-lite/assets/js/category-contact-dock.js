/* Toque e teclado: <details> nativo. Desktop: expansão no hover sem bloquear cliques. */
(() => {
  const setup = () => {
    document.querySelectorAll('[data-cv-category-contact-dock]').forEach((dock) => {
      if (dock.dataset.cvDockReady === '1') return;
      dock.dataset.cvDockReady = '1';
      const items = Array.from(dock.querySelectorAll('.cv-dock-item'));
      const supportsHover = window.matchMedia('(hover:hover) and (pointer:fine)');
      const closeOthers = (active) => {
        // A aplicação Tawk.to é carregada só quando o visitante interage com o botão.
      // Se já existir um widget instalado (por exemplo, por plugin WooCommerce),
      // aproveita-se essa instância: nunca são carregados dois scripts de chat.
      const tawkItem = dock.querySelector('.cv-dock-tawk');
      const tawkLink = dock.querySelector('[data-cv-tawk-launch]');
      let tawkPreloadStarted = false;
      const preloadTawk = () => {
        if (tawkPreloadStarted) return;
        tawkPreloadStarted = true;
        if ((window.Tawk_API && typeof window.Tawk_API.maximize === 'function') ||
            document.querySelector('script[src*="embed.tawk.to/"]')) return;

        window.Tawk_API = window.Tawk_API || {};
        const api = window.Tawk_API;
        const previousOnLoad = api.onLoad;
        api.onLoad = function (...args) {
          try {
            if (typeof previousOnLoad === 'function') previousOnLoad.apply(this, args);
          } finally {
            // Só o nosso carregamento adia o widget de origem até o utilizador abrir o chat.
            if (typeof api.hideWidget === 'function' && !(api.isChatMaximized && api.isChatMaximized())) {
              api.hideWidget();
            }
          }
        };
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://embed.tawk.to/5fb80845a1d54c18d8ebc361/default';
        script.setAttribute('data-cv-tawk-embed', '1');
        document.head.appendChild(script);
      };
      if (tawkItem && tawkLink) {
        tawkItem.addEventListener('pointerenter', (event) => {
          if (event.pointerType === 'mouse') preloadTawk();
        });
        tawkItem.addEventListener('focusin', preloadTawk);
        tawkItem.addEventListener('toggle', () => {
          if (tawkItem.open) preloadTawk();
        });
        tawkLink.addEventListener('click', (event) => {
          const api = window.Tawk_API;
          if (api && typeof api.maximize === 'function') {
            try {
              if (typeof api.showWidget === 'function') api.showWidget();
              api.maximize();
              event.preventDefault();
              closeOthers(null);
              return;
            } catch (_) {
              // Se a API falhar, segue-se o URL oficial de chat direto do href.
            }
          }
          // Em ligações lentas ou com scripts bloqueados, abre o chat direto numa
          // nova aba (target=_blank), sem deixar o botão sem resposta.
        });
      }
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
