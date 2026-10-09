(function () {
  'use strict';

  const translations = new Map([
    ['Save for later', 'Guardar para mais tarde'],
    ['Saved for later', 'Guardados para mais tarde'],
    ['Move to cart', 'Mover para o carrinho'],
    ['Move to Cart', 'Mover para o carrinho'],
    ['Your cart is currently empty!', 'O seu carrinho está vazio.'],
    ['Your cart is currently empty.', 'O seu carrinho está vazio.'],
    ['New in store', 'Produtos em promoção'],
    ['Shipping will be calculated at checkout', 'Os portes de envio serão calculados ao finalizar a encomenda'],
    ['Shipping will be calculated at checkout.', 'Os portes de envio serão calculados ao finalizar a encomenda.'],
    ['Quantity:', 'Quantidade:']
  ]);

  function translateNode(root) {
    if (!root || !document.body.classList.contains('woocommerce-cart')) {
      return;
    }

    const walker = document.createTreeWalker(
      root,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode(node) {
          const value = node.nodeValue ? node.nodeValue.trim() : '';
          return translations.has(value) || /^Quantity:\s*\d+$/i.test(value)
            ? NodeFilter.FILTER_ACCEPT
            : NodeFilter.FILTER_REJECT;
        }
      }
    );

    const nodes = [];
    let node;

    while ((node = walker.nextNode())) {
      nodes.push(node);
    }

    nodes.forEach((textNode) => {
      const original = textNode.nodeValue || '';
      const trimmed = original.trim();
      const replacement = translations.get(trimmed) || trimmed.replace(/^Quantity:\s*(\d+)$/i, 'Quantidade: $1');

      if (!replacement) {
        return;
      }

      const leading = original.match(/^\s*/)?.[0] || '';
      const trailing = original.match(/\s*$/)?.[0] || '';
      textNode.nodeValue = leading + replacement + trailing;
    });
  }

  function translateCart() {
    translateNode(document.body);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', translateCart, { once: true });
  } else {
    translateCart();
  }

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((addedNode) => {
        if (addedNode.nodeType === Node.TEXT_NODE) {
          translateNode(addedNode.parentNode);
        } else if (addedNode.nodeType === Node.ELEMENT_NODE) {
          translateNode(addedNode);
        }
      });
    });
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true
  });
})();
