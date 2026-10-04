document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-cvl-copy-sku]').forEach(function (button) {
    button.addEventListener('click', function () {
      var sku = button.getAttribute('data-cvl-copy-sku') || '';

      if (!sku || !navigator.clipboard || !navigator.clipboard.writeText) {
        return;
      }

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
});
