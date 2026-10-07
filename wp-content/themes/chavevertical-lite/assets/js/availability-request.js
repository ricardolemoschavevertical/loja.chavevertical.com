(() => {
  'use strict';

  const settings = window.CVLAvailabilityRequest || {};
  const endpoint = String(settings.endpoint || '/wp-json/chavevertical/v1/availability-request');
  const fallbackMessage = String(
    settings.confirmationMessage ||
    'Agradecemos a sua consulta. O seu pedido foi encaminhado para Ricardo Lemos. Se preferir um contacto direto, pode fazê-lo através do e-mail Ricardo@chavevertical.com ou do telefone 914 580 410.'
  );

  let lastTrigger = null;

  const createModal = () => {
    const wrapper = document.createElement('div');
    wrapper.className = 'cvl-availability-modal';
    wrapper.hidden = true;
    wrapper.innerHTML = `
      <div class="cvl-availability-modal__backdrop" data-cvl-availability-close></div>
      <div class="cvl-availability-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cvl-availability-title">
        <button type="button" class="cvl-availability-modal__close" data-cvl-availability-close aria-label="Fechar">×</button>
        <div class="cvl-availability-modal__content">
          <h2 id="cvl-availability-title">Consultar disponibilidade</h2>
          <p class="cvl-availability-modal__intro">Indique os seus dados e confirmamos a disponibilidade deste produto.</p>
          <p class="cvl-availability-modal__product" data-cvl-availability-product></p>
          <form class="cvl-availability-form" data-cvl-availability-form novalidate>
            <label>
              <span>Nome</span>
              <input type="text" name="name" autocomplete="name" required>
            </label>
            <label>
              <span>E-mail</span>
              <input type="email" name="email" autocomplete="email" required>
            </label>
            <label>
              <span>Telefone</span>
              <input type="tel" name="phone" autocomplete="tel" required>
            </label>
            <label class="cvl-availability-hp" aria-hidden="true">
              <span>Empresa</span>
              <input type="text" name="company" tabindex="-1" autocomplete="off">
            </label>
            <input type="hidden" name="product_id">
            <input type="hidden" name="product_slug">
            <input type="hidden" name="product_name">
            <input type="hidden" name="product_url">
            <button type="submit" class="cvl-availability-submit">ENVIAR PEDIDO</button>
            <p class="cvl-availability-form__status" data-cvl-availability-status aria-live="polite"></p>
          </form>
          <div class="cvl-availability-success" data-cvl-availability-success hidden>
            <p data-cvl-availability-success-text></p>
            <div class="cvl-availability-success__actions">
              <a href="mailto:Ricardo@chavevertical.com">Ricardo@chavevertical.com</a>
              <a href="tel:+351914580410">914 580 410</a>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(wrapper);
    return wrapper;
  };

  const modal = createModal();
  const form = modal.querySelector('[data-cvl-availability-form]');
  const productLabel = modal.querySelector('[data-cvl-availability-product]');
  const status = modal.querySelector('[data-cvl-availability-status]');
  const success = modal.querySelector('[data-cvl-availability-success]');
  const successText = modal.querySelector('[data-cvl-availability-success-text]');
  const submitButton = modal.querySelector('.cvl-availability-submit');

  const closeModal = () => {
    modal.hidden = true;
    document.documentElement.classList.remove('cvl-availability-open');
    if (lastTrigger && typeof lastTrigger.focus === 'function') lastTrigger.focus();
  };

  const openModal = (trigger) => {
    lastTrigger = trigger;
    form.reset();
    form.hidden = false;
    success.hidden = true;
    status.textContent = '';
    submitButton.disabled = false;
    submitButton.textContent = 'ENVIAR PEDIDO';

    form.elements.product_id.value = trigger.dataset.productId || '';
    form.elements.product_slug.value = trigger.dataset.productSlug || '';
    form.elements.product_name.value = trigger.dataset.productName || '';
    form.elements.product_url.value = trigger.dataset.productUrl || window.location.href;
    productLabel.textContent = trigger.dataset.productName || '';

    modal.hidden = false;
    document.documentElement.classList.add('cvl-availability-open');

    const firstInput = form.querySelector('input[name="name"]');
    if (firstInput) window.setTimeout(() => firstInput.focus(), 0);
  };

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const trigger = target && target.closest('[data-cvl-availability-request]');

    if (trigger) {
      event.preventDefault();
      event.stopPropagation();
      openModal(trigger);
      return;
    }

    if (target && target.closest('[data-cvl-availability-close]')) {
      event.preventDefault();
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!form.reportValidity()) return;

    const payload = Object.fromEntries(new FormData(form).entries());
    submitButton.disabled = true;
    submitButton.textContent = 'A ENVIAR…';
    status.textContent = '';

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'omit',
        body: JSON.stringify(payload)
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || data.ok === false) {
        throw new Error(data.message || 'Não foi possível enviar o pedido.');
      }

      form.hidden = true;
      successText.textContent = data.message || fallbackMessage;
      success.hidden = false;
    } catch (error) {
      status.textContent = error && error.message
        ? error.message
        : 'Não foi possível enviar o pedido. Tente novamente.';
      submitButton.disabled = false;
      submitButton.textContent = 'ENVIAR PEDIDO';
    }
  });
})();
