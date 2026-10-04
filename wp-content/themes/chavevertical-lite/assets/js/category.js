document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.cv-sidebar-inner').forEach(function (sidebar) {
    var toggle = sidebar.querySelector('.cv-cat-toggle');
    var text = sidebar.querySelector('.cv-cat-toggle-text');
    var icon = sidebar.querySelector('.cv-cat-toggle-icon');

    if (!toggle) {
      return;
    }

    function setCollapsed(collapsed) {
      sidebar.classList.toggle('cv-cat-collapsed', collapsed);
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

      if (text) {
        text.textContent = collapsed ? 'Mostrar categorias' : 'Ocultar categorias';
      }

      if (icon) {
        icon.textContent = collapsed ? '+' : '−';
      }
    }

    if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches) {
      setCollapsed(true);
    }

    toggle.addEventListener('click', function () {
      setCollapsed(!sidebar.classList.contains('cv-cat-collapsed'));
    });
  });
});


document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-cvl-category-carousel]').forEach(function (carousel) {
    var viewport = carousel.querySelector('[data-cvl-category-viewport]');
    var prev = carousel.querySelector('[data-cvl-category-prev]');
    var next = carousel.querySelector('[data-cvl-category-next]');

    if (!viewport || !prev || !next) {
      return;
    }

    function step() {
      var card = viewport.querySelector('.cvl-category-carousel-card');
      if (!card) {
        return Math.max(260, viewport.clientWidth * 0.75);
      }

      var styles = window.getComputedStyle(viewport.querySelector('.cvl-category-carousel-track'));
      var gap = parseFloat(styles.columnGap || styles.gap || '12') || 12;
      return Math.max(card.getBoundingClientRect().width + gap, viewport.clientWidth * 0.7);
    }

    function updateButtons() {
      var max = Math.max(0, viewport.scrollWidth - viewport.clientWidth - 2);
      prev.disabled = viewport.scrollLeft <= 2;
      next.disabled = viewport.scrollLeft >= max;
    }

    prev.addEventListener('click', function () {
      viewport.scrollBy({ left: -step(), behavior: 'smooth' });
    });

    next.addEventListener('click', function () {
      viewport.scrollBy({ left: step(), behavior: 'smooth' });
    });

    viewport.addEventListener('scroll', updateButtons, { passive: true });
    window.addEventListener('resize', updateButtons);
    updateButtons();
  });
});


document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-cvl-category-filter-tree]').forEach(function (tree) {
    tree.querySelectorAll('[data-cvl-category-tree-toggle]').forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var item = toggle.closest('.cvl-category-filter-item');
        if (!item) {
          return;
        }

        var children = item.querySelector(':scope > [data-cvl-category-tree-children]');
        if (!children) {
          return;
        }

        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        item.classList.toggle('is-expanded', !expanded);
        children.hidden = expanded;
      });
    });
  });
});


document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-cvl-brand-auto-filter]').forEach(function (form) {
    form.querySelectorAll('input[type="checkbox"][name="marca[]"]').forEach(function (input) {
      input.addEventListener('change', function () {
        var chip = input.closest('.cvl-final-brand-chip');

        if (chip) {
          chip.classList.toggle('is-active', input.checked);
        }

        form.setAttribute('aria-busy', 'true');

        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      });
    });
  });
});
