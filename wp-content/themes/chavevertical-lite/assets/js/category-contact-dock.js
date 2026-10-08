/**
 * Barra lateral Chave Vertical e visibilidade do launcher nativo Tawk.to.
 * O botão flutuante inferior do Tawk só é visível durante uma conversa ativa.
 * A barra lateral continua disponível para iniciar/reabrir uma conversa.
 */
(() => {
  'use strict';

  if (window.__cvTawkDockRuntimeReady) return;
  window.__cvTawkDockRuntimeReady = true;

  const TAWK_EMBED = 'https://embed.tawk.to/5fb80845a1d54c18d8ebc361/default';
  let embedRequested = false;
  let openedFromDock = false;
  let conversationStarted = false;
  let conversationEnded = false;

  const safeBoolean = (method) => {
    const api = window.Tawk_API;
    if (typeof api?.[method] !== 'function') return false;
    try { return api[method]() === true; } catch (_) { return false; }
  };

  const conversationIsActive = () => (
    !conversationEnded && (
      conversationStarted ||
      safeBoolean('isChatOngoing') ||
      safeBoolean('isVisitorEngaged')
    )
  );

  const syncNativeWidget = () => {
    const api = window.Tawk_API;
    if (!api) return;
    // Nunca mostrar o launcher inferior a um visitante sem conversa.
    // A janela que o cliente abriu na barra lateral continua utilizável.
    const visible = openedFromDock || conversationIsActive();
    const action = visible ? 'showWidget' : 'hideWidget';
    if (typeof api[action] === 'function') {
      try { api[action](); } catch (_) { /* widget ainda a inicializar */ }
    }
  };

  const installTawkHooks = () => {
    const api = window.Tawk_API = window.Tawk_API || {};
    if (api.__cvTawkVisibilityHooks) return;
    api.__cvTawkVisibilityHooks = true;

    const hook = (eventName, onEvent) => {
      const previous = api[eventName];
      api[eventName] = function (...args) {
        try {
          if (typeof previous === 'function') previous.apply(this, args);
        } finally {
          onEvent(...args);
        }
      };
    };

    hook('onBeforeLoad', syncNativeWidget);
    hook('onLoad', syncNativeWidget);
    hook('onChatStarted', () => {
      conversationStarted = true;
      conversationEnded = false;
      syncNativeWidget();
    });
    hook('onChatEnded', () => {
      conversationStarted = false;
      conversationEnded = true;
      openedFromDock = false;
      // O estado isVisitorEngaged pode demorar a atualizar-se; ocultar já.
      syncNativeWidget();
    });
    hook('onChatMinimized', () => {
      // Se ainda não iniciou uma conversa, a bolha nativa volta a desaparecer.
      openedFromDock = false;
      syncNativeWidget();
    });
    hook('onChatMaximized', syncNativeWidget);
    syncNativeWidget();
  };

  const preloadTawk = () => {
    installTawkHooks();
    const api = window.Tawk_API;
    if (typeof api.maximize === 'function' ||
        document.querySelector('script[src*="embed.tawk.to/"]') ||
        embedRequested) return;

    embedRequested = true;
    const script = document.createElement('script');
    script.async = true;
    script.src = TAWK_EMBED;
    script.dataset.cvTawkEmbed = '1';
    script.addEventListener('error', () => {
      embedRequested = false;
      script.remove();
    });
    document.head.appendChild(script);
  };

  const setupContactDock = () => {
    installTawkHooks();
    document.querySelectorAll('[data-cv-category-contact-dock]').forEach((dock) => {
      if (dock.dataset.cvDockReady === '1') return;
      dock.dataset.cvDockReady = '1';

      const items = Array.from(dock.querySelectorAll('.cv-dock-item'));
      const supportsHover = window.matchMedia('(hover:hover) and (pointer:fine)');
      const closeOthers = (active) => {
        items.forEach((item) => { if (item !== active) item.open = false; });
      };

      const tawkItem = dock.querySelector('.cv-dock-tawk');
      const tawkLink = dock.querySelector('[data-cv-tawk-launch]');
      if (tawkItem && tawkLink) {
        tawkItem.addEventListener('pointerenter', (event) => {
          if (event.pointerType === 'mouse') preloadTawk();
        });
        tawkItem.addEventListener('focusin', preloadTawk);
        tawkItem.addEventListener('toggle', () => {
          if (tawkItem.open) preloadTawk();
        });
        tawkLink.addEventListener('click', (event) => {
          installTawkHooks();
          const api = window.Tawk_API;
          if (typeof api?.maximize === 'function') {
            try {
              openedFromDock = true;
              syncNativeWidget();
              api.maximize();
              event.preventDefault();
              closeOthers(null);
              return;
            } catch (_) {
              openedFromDock = false;
            }
          }
          // Caso o script esteja bloqueado/ainda a carregar, o href oficial
          // abre o chat numa nova aba. Nunca se perde o acesso ao chat.
          preloadTawk();
        });
      }

      items.forEach((item) => {
        // Um clique no ícone após expandir (hover em desktop / primeiro toque
        // em mobile) executa a ação. A área vazia do cartão também é clicável.
        // Ligações secundárias, como o telefone fixo, mantêm a sua ação.
        const summary = item.querySelector('summary');
        const primary = item.querySelector('a[data-cv-dock-primary]');
        const closedLabel = summary?.getAttribute('aria-label') || '';
        const actionLabel = primary?.getAttribute('aria-label') || primary?.textContent?.trim() || '';
        const runPrimary = () => {
          if (primary) primary.click();
        };

        summary?.addEventListener('click', (event) => {
          // No primeiro toque deixa o <details> abrir de forma nativa.
          if (!item.open) return;
          // No segundo toque ou após hover, não recolher: ativar a ação.
          event.preventDefault();
          runPrimary();
        });

        item.addEventListener('click', (event) => {
          if (!item.open || !event.target || typeof event.target.closest !== 'function') return;
          // O summary e as ligações já têm handlers próprios; evitar duplicação.
          if (event.target.closest('summary, a, button, input, textarea, select')) return;
          runPrimary();
        });

        item.addEventListener('pointerenter', (event) => {
          if (!supportsHover.matches || event.pointerType !== 'mouse') return;
          closeOthers(item);
          item.open = true;
        });
        item.addEventListener('pointerleave', (event) => {
          if (event.pointerType === 'mouse' && !item.contains(document.activeElement)) {
            item.open = false;
          }
        });
        item.addEventListener('toggle', () => {
          if (item.open) closeOthers(item);
          if (summary) {
            summary.setAttribute('aria-label', item.open && actionLabel
              ? 'Executar ação: ' + actionLabel
              : closedLabel);
          }
        });
        item.addEventListener('focusout', (event) => {
          if (item.contains(event.relatedTarget)) return;
          setTimeout(() => {
            if (!item.contains(document.activeElement) &&
                !(supportsHover.matches && item.matches(':hover'))) {
              item.open = false;
            }
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
          expanded.querySelector('summary')?.focus({ preventScroll: true });
        }
      });
    });
  };

  // Inicializar ANTES do embed, inclusive quando o Tawk é instalado por
  // um plugin externo e a barra lateral não está visível nesta página.
  installTawkHooks();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupContactDock, { once: true });
  } else {
    setupContactDock();
  }
  window.addEventListener('load', installTawkHooks, { once: true });
  document.addEventListener('astro:page-load', setupContactDock);
})();
