document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-cvl-copy-sku]').forEach(function (button) {
    button.addEventListener('click', function () {
      var sku = button.getAttribute('data-cvl-copy-sku') || '';
      if (!sku || !navigator.clipboard || !navigator.clipboard.writeText) return;

      navigator.clipboard.writeText(sku).then(function () {
        button.classList.add('is-copied');
        button.setAttribute('title', 'Referência copiada');
        window.setTimeout(function () {
          button.classList.remove('is-copied');
          button.setAttribute('title', 'Copiar referência');
        }, 1400);
      }).catch(function () {});
    });
  });

  function decimals(value) {
    var text = String(value == null ? '' : value);
    var dot = text.indexOf('.');
    return dot === -1 ? 0 : text.length - dot - 1;
  }

  function initQuantity(quantity) {
    if (!quantity || quantity.dataset.cvlQuantityReady === '1') return;
    var input = quantity.querySelector('input.qty');
    if (!input) return;

    quantity.dataset.cvlQuantityReady = '1';

    var minus = document.createElement('button');
    minus.type = 'button';
    minus.className = 'cvl-qty-button is-minus';
    minus.setAttribute('aria-label', 'Diminuir quantidade');
    minus.textContent = '−';

    var plus = document.createElement('button');
    plus.type = 'button';
    plus.className = 'cvl-qty-button is-plus';
    plus.setAttribute('aria-label', 'Aumentar quantidade');
    plus.textContent = '+';

    quantity.insertBefore(minus, input);
    quantity.appendChild(plus);

    function update(direction) {
      var step = parseFloat(input.getAttribute('step'));
      if (!Number.isFinite(step) || step <= 0) step = 1;
      var min = parseFloat(input.getAttribute('min'));
      var max = parseFloat(input.getAttribute('max'));
      var current = parseFloat(input.value);
      if (!Number.isFinite(current)) current = Number.isFinite(min) ? min : 1;

      var next = current + direction * step;
      if (Number.isFinite(min)) next = Math.max(min, next);
      if (Number.isFinite(max)) next = Math.min(max, next);

      input.value = next.toFixed(decimals(step));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    minus.addEventListener('click', function () { update(-1); });
    plus.addEventListener('click', function () { update(1); });
  }

  document.querySelectorAll('.single-product .quantity').forEach(initQuantity);

  var quantityObserver = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      mutation.addedNodes.forEach(function (node) {
        if (!(node instanceof Element)) return;
        if (node.matches('.quantity')) initQuantity(node);
        node.querySelectorAll('.quantity').forEach(initQuantity);
      });
    });
  });
  quantityObserver.observe(document.body, { childList: true, subtree: true });

  document.querySelectorAll('.single-product .woocommerce-product-gallery').forEach(function (gallery) {
    var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('.flex-control-thumbs img'));
    if (thumbs.length < 2 || gallery.querySelector('.cvl-gallery-nav')) return;

    function activeIndex() {
      var index = thumbs.findIndex(function (thumb) { return thumb.classList.contains('flex-active'); });
      return index >= 0 ? index : 0;
    }

    function go(delta) {
      var current = activeIndex();
      var next = (current + delta + thumbs.length) % thumbs.length;
      thumbs[next].click();
    }

    var prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'cvl-gallery-nav is-prev';
    prev.setAttribute('aria-label', 'Imagem anterior');
    prev.textContent = '‹';

    var next = document.createElement('button');
    next.type = 'button';
    next.className = 'cvl-gallery-nav is-next';
    next.setAttribute('aria-label', 'Imagem seguinte');
    next.textContent = '›';

    gallery.appendChild(prev);
    gallery.appendChild(next);

    prev.addEventListener('click', function () { go(-1); });
    next.addEventListener('click', function () { go(1); });
  });
});
