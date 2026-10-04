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
