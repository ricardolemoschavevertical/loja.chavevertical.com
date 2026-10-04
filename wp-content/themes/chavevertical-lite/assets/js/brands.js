document.addEventListener('DOMContentLoaded', function () {
  var search = document.querySelector('[data-cvl-brands-search]');

  if (!search) {
    return;
  }

  var page = search.closest('.cvl-brands-page');

  if (!page) {
    return;
  }

  var cards = Array.prototype.slice.call(page.querySelectorAll('[data-cvl-brand-card]'));
  var groups = Array.prototype.slice.call(page.querySelectorAll('[data-cvl-brand-group]'));
  var indexLinks = Array.prototype.slice.call(page.querySelectorAll('[data-cvl-brand-index-link]'));
  var empty = page.querySelector('[data-cvl-brands-search-empty]');

  function normalize(value) {
    var text = String(value || '').toLocaleLowerCase('pt-PT');

    if (typeof text.normalize === 'function') {
      text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    return text.trim();
  }

  function filterBrands() {
    var query = normalize(search.value);
    var visibleCards = 0;

    cards.forEach(function (card) {
      var name = normalize(card.getAttribute('data-brand-name') || card.textContent);
      var matches = !query || name.indexOf(query) !== -1;

      card.hidden = !matches;

      if (matches) {
        visibleCards += 1;
      }
    });

    groups.forEach(function (group) {
      var hasVisibleCards = Array.prototype.some.call(
        group.querySelectorAll('[data-cvl-brand-card]'),
        function (card) {
          return !card.hidden;
        }
      );

      group.hidden = !hasVisibleCards;
    });

    indexLinks.forEach(function (link) {
      var selector = link.getAttribute('href');
      var group = selector && selector.charAt(0) === '#' ? page.querySelector(selector) : null;

      link.hidden = !group || group.hidden;
    });

    if (empty) {
      empty.hidden = visibleCards !== 0;
    }
  }

  search.addEventListener('input', filterBrands);
  search.addEventListener('search', filterBrands);
  filterBrands();
});
