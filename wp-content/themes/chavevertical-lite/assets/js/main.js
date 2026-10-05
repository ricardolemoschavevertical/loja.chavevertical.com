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


  const contactMenu = document.querySelector('.cvl-contact-menu');

  if (contactMenu) {
    const desktop = window.matchMedia('(min-width: 992px)');
    const contactDirect = contactMenu.querySelector('.cvl-contact-menu-direct');

    if (contactDirect) {
      contactDirect.addEventListener('click', (event) => {
        event.stopPropagation();
      });
    }

    contactMenu.addEventListener('mouseenter', () => {
      if (desktop.matches) contactMenu.open = true;
    });

    contactMenu.addEventListener('mouseleave', () => {
      if (desktop.matches) contactMenu.open = false;
    });
  }

  const quickViewModal = document.querySelector('[data-cvl-quick-view-modal]');
  const quickViewContent = quickViewModal?.querySelector('[data-cvl-quick-view-content]');

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&':'&amp;',
    '<':'&lt;',
    '>':'&gt;',
    "'":'&#39;',
    '"':'&quot;'
  }[char] || char));

  const closeQuickView = () => {
    if (!quickViewModal) return;
    quickViewModal.hidden = true;
    document.body.classList.remove('cvl-quick-view-open');
  };

  const renderQuickView = (product) => {
    if (!quickViewModal || !quickViewContent) return;

    const price = Number(product?.price || 0);
    const priceVat = price > 0
      ? (price * 1.23).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'
      : 'Preço sob consulta';
    const brand = String(product?.brands?.[0]?.name || '');
    const brandLogo = String(product?.brands?.[0]?.logo || '');
    const image = String(product?.images?.[0]?.src || '');
    const sku = String(product?.sku || '');
    const stockStatus = String(product?.stock_status || 'onbackorder');
    const stock = stockStatus === 'instock'
      ? 'Em stock'
      : stockStatus === 'outofstock'
        ? 'Indisponível'
        : 'Disponível por encomenda';
    const shortDescription = String(product?.short_description || product?.description || '');
    const permalink = String(product?.permalink || '#');

    quickViewContent.innerHTML = `
      <div class="cvl-quick-view-grid">
        <div class="cvl-quick-view-media">
          ${image ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(product?.name || '')}">` : ''}
        </div>
        <div class="cvl-quick-view-copy">
          ${brand ? `<div class="cvl-quick-view-brand">${brandLogo ? `<img src="${escapeHtml(brandLogo)}" alt="${escapeHtml(brand)}">` : `<strong>${escapeHtml(brand)}</strong>`}</div>` : ''}
          <h2 id="cvl-quick-view-title">${escapeHtml(product?.name || 'Produto')}</h2>
          ${sku ? `<div class="cvl-quick-view-sku">Ref. ${escapeHtml(sku)}</div>` : ''}
          <div class="cvl-quick-view-price">${escapeHtml(priceVat)}${price > 0 ? '<small>c/ IVA</small>' : ''}</div>
          <div class="cvl-quick-view-stock">${escapeHtml(stock)}</div>
          ${shortDescription ? `<div class="cvl-quick-view-description">${shortDescription}</div>` : ''}
          <a class="cvl-quick-view-open-product" href="${escapeHtml(permalink)}">ABRIR PRODUTO →</a>
        </div>
      </div>
    `;

    quickViewModal.hidden = false;
    document.body.classList.add('cvl-quick-view-open');
  };

  document.addEventListener('click', async (event) => {
    const closeButton = event.target.closest('[data-cvl-quick-view-close]');
    if (closeButton) {
      event.preventDefault();
      closeQuickView();
      return;
    }

    const button = event.target.closest('[data-cvl-quick-view]');
    if (!button) return;

    event.preventDefault();
    event.stopPropagation();

    const slug = String(button.dataset.productSlug || '').trim();
    if (!slug || !quickViewModal || !quickViewContent) return;

    quickViewContent.innerHTML = '<div class="cvl-quick-view-loading">A carregar produto…</div>';
    quickViewModal.hidden = false;
    document.body.classList.add('cvl-quick-view-open');

    try {
      const response = await fetch('/wp-json/cv-astro/v1/product/by-slug/' + encodeURIComponent(slug), {
        headers: { Accept: 'application/json' }
      });
      const product = await response.json();
      if (!response.ok || !product?.ok) throw new Error('product_unavailable');
      renderQuickView(product);
    } catch {
      quickViewContent.innerHTML = '<div class="cvl-quick-view-loading">Não foi possível carregar o produto.</div>';
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    if (nav && menuToggle) {
      nav.classList.remove('is-open');
      menuToggle.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('cvl-menu-open');
    }

    closeDrawer();
    closeQuickView();
  });
})();
