(() => {
  const cfg = window.CVOpenSearch || {};

  const money = (value, currency = 'EUR') => {
    const number = Number(value || 0);
    if (!number) return 'Preço sob consulta';

    try {
      return new Intl.NumberFormat('pt-PT', { style: 'currency', currency }).format(number);
    } catch {
      return number.toFixed(2) + ' €';
    }
  };

  const escapeHtml = (value = '') => String(value).replace(/[&<>"']/g, (ch) => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[ch] || ch));

  const plainText = (value = '') => {
    const node = document.createElement('div');
    node.innerHTML = String(value || '');
    return (node.textContent || node.innerText || '').replace(/\s+/g, ' ').trim();
  };

  const shortText = (value = '', max = 240) => {
    const text = plainText(value);
    if (text.length <= max) return text;
    return text.slice(0, Math.max(0, max - 1)).trimEnd() + '…';
  };

  const productImage = (item) => item?.image_url || item?.gallery?.[0]?.src || '';

  const firstValue = (value) => {
    if (Array.isArray(value)) return value[0] || '';
    return value || '';
  };

  const productBrand = (item) => {
    if (Array.isArray(item?.brand_names) && item.brand_names.length) return item.brand_names[0];
    if (Array.isArray(item?.brands) && item.brands.length) return item.brands[0]?.name || '';
    return '';
  };

  const productCategory = (item) => {
    if (Array.isArray(item?.category_names) && item.category_names.length) return item.category_names[0];
    if (Array.isArray(item?.categories) && item.categories.length) return item.categories[0]?.name || '';
    return '';
  };

  const stockLabel = (status) => {
    switch (String(status || '').toLowerCase()) {
      case 'instock':
        return 'Em stock';
      case 'onbackorder':
        return 'Disponível por encomenda';
      case 'outofstock':
        return 'Sob consulta';
      default:
        return '';
    }
  };

  const previewMarkup = (item) => {
    if (!item) {
      return '<div class="cvos-preview-empty">Passe o rato sobre um produto para ver os detalhes.</div>';
    }

    const image = productImage(item);
    const title = item.name || '';
    const sku = item.sku || firstValue(item.variation_skus) || '';
    const brand = productBrand(item);
    const category = productCategory(item);
    const stock = stockLabel(item.stock_status);
    const description = shortText(item.short_description || item.description || '', 260);
    const url = item.url || item.purchase_url || '#';

    return `
      <a class="cvos-preview-image" href="${escapeHtml(url)}" tabindex="-1">
        ${image ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(title)}">` : '<span class="cvos-preview-no-image">Sem imagem</span>'}
      </a>
      <div class="cvos-preview-body">
        ${brand ? `<div class="cvos-preview-brand">${escapeHtml(brand)}</div>` : ''}
        <a class="cvos-preview-title" href="${escapeHtml(url)}">${escapeHtml(title)}</a>
        <div class="cvos-preview-price">${escapeHtml(money(item.price, item.currency || 'EUR'))}</div>
        <div class="cvos-preview-meta">
          ${sku ? `<span><b>Ref.</b> ${escapeHtml(sku)}</span>` : ''}
          ${category ? `<span><b>Categoria</b> ${escapeHtml(category)}</span>` : ''}
          ${stock ? `<span class="cvos-preview-stock is-${escapeHtml(item.stock_status || '')}">${escapeHtml(stock)}</span>` : ''}
        </div>
        ${description ? `<p class="cvos-preview-description">${escapeHtml(description)}</p>` : ''}
        <a class="cvos-preview-button" href="${escapeHtml(url)}">VER PRODUTO</a>
      </div>
    `;
  };

  const prepareThemeSearch = () => {
    document.querySelectorAll('form.cvl-search').forEach((form) => {
      if (form.hasAttribute('data-cvos-search')) return;

      const input = form.querySelector('input[type="search"][name="s"]');
      if (!input) return;

      form.setAttribute('data-cvos-search', '');
      form.classList.add('cvos-theme-search');
      input.setAttribute('data-cvos-input', '');
      input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-expanded', 'false');

      let box = form.querySelector('[data-cvos-results]');
      if (!box) {
        box = document.createElement('div');
        box.className = 'cvos-suggestions';
        box.setAttribute('data-cvos-results', '');
        box.hidden = true;
        form.appendChild(box);
      }
    });
  };

  const bind = (root) => {
    if (root.dataset.cvosBound === '1') return;

    const input = root.querySelector('[data-cvos-input]');
    const box = root.querySelector('[data-cvos-results]');
    if (!input || !box || !cfg.endpoint) return;

    root.dataset.cvosBound = '1';
    let timer = 0;
    let controller = null;
    let currentItems = [];

    const close = () => {
      box.hidden = true;
      box.innerHTML = '';
      currentItems = [];
      input.setAttribute('aria-expanded', 'false');
    };

    const updatePreview = (index) => {
      const preview = box.querySelector('[data-cvos-preview]');
      if (!preview) return;

      const item = currentItems[Number(index)] || null;
      preview.innerHTML = previewMarkup(item);

      box.querySelectorAll('.cvos-suggestion.is-active').forEach((row) => {
        row.classList.remove('is-active');
      });

      const active = box.querySelector(`.cvos-suggestion[data-cvos-index="${Number(index)}"]`);
      active?.classList.add('is-active');
    };

    const render = (items, query) => {
      if (!Array.isArray(items) || !items.length) {
        box.innerHTML = '<div class="cvos-empty">Sem resultados</div>';
        box.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        return;
      }

      currentItems = items;

      const rows = items.map((item, index) => {
        const image = productImage(item);
        const title = item.name || '';
        const sku = item.sku || '';
        const url = item.url || item.purchase_url || '#';

        return `
          <a class="cvos-suggestion" data-cvos-index="${index}" href="${escapeHtml(url)}">
            <span class="cvos-thumb">${image ? `<img src="${escapeHtml(image)}" alt="">` : ''}</span>
            <span class="cvos-copy">
              <strong>${escapeHtml(title)}</strong>
              ${sku ? `<small>Ref. ${escapeHtml(sku)}</small>` : ''}
            </span>
            <span class="cvos-price">${escapeHtml(money(item.price, item.currency || 'EUR'))}</span>
          </a>`;
      }).join('');

      box.innerHTML = `
        <div class="cvos-results-layout">
          <div class="cvos-results-list">
            ${rows}
            <a class="cvos-see-all" href="${escapeHtml(cfg.searchUrl || '/')}?s=${encodeURIComponent(query)}&post_type=product">
              Ver todos os resultados →
            </a>
          </div>
          <aside class="cvos-product-preview" data-cvos-preview aria-live="polite">
            ${previewMarkup(items[0])}
          </aside>
        </div>
      `;

      box.querySelectorAll('.cvos-suggestion[data-cvos-index]').forEach((row) => {
        const show = () => updatePreview(row.dataset.cvosIndex);
        row.addEventListener('mouseenter', show);
        row.addEventListener('focus', show);
      });

      box.querySelector('.cvos-suggestion[data-cvos-index="0"]')?.classList.add('is-active');
      box.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };

    const search = async () => {
      const q = input.value.trim();

      if (q.length < Number(cfg.minChars || 2)) {
        close();
        return;
      }

      if (controller) controller.abort();
      controller = new AbortController();

      try {
        const url = new URL(cfg.endpoint, window.location.origin);
        url.searchParams.set('q', q);
        url.searchParams.set('limit', String(cfg.limit || 10));

        const response = await fetch(url.toString(), {
          signal: controller.signal,
          headers: { Accept: 'application/json' }
        });
        const data = await response.json();

        if (!response.ok) throw new Error(data?.message || 'Erro de pesquisa');
        render(data.items || [], q);
      } catch (error) {
        if (error?.name !== 'AbortError') close();
      }
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 180);
    });

    input.addEventListener('focus', () => {
      if (input.value.trim().length >= Number(cfg.minChars || 2)) search();
    });

    document.addEventListener('click', (event) => {
      if (!root.contains(event.target)) close();
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  };

  prepareThemeSearch();
  document.querySelectorAll('[data-cvos-search]').forEach(bind);
})();