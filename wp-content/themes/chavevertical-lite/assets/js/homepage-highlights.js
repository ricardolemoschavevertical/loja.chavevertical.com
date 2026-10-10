(function () {
  'use strict';

  // No telemovel, o destaque lateral integra o mesmo grupo dos quatro destaques,
  // imediatamente a seguir as promocoes. Em tablet/desktop volta ao hero original.
  var sideHighlight = document.querySelector('.cvl-ref-hero-grid > .cvl-ref-hero-side');
  var highlightGrid = document.querySelector('.cvl-homepage-highlights-section .cvl-homepage-highlights');
  if (sideHighlight && highlightGrid && window.matchMedia) {
    var sideOrigin = document.createComment('cvl-side-highlight-original-position');
    sideHighlight.parentNode.insertBefore(sideOrigin, sideHighlight);

    var mobileQuery = window.matchMedia('(max-width: 767.98px)');
    var syncMobileHighlights = function () {
      if (mobileQuery.matches) {
        if (sideHighlight.parentNode !== highlightGrid) {
          highlightGrid.insertBefore(sideHighlight, highlightGrid.firstChild);
        }
        sideHighlight.classList.add('cvl-mobile-highlight-side');
      } else {
        if (sideOrigin.parentNode && sideHighlight.parentNode !== sideOrigin.parentNode) {
          sideOrigin.parentNode.insertBefore(sideHighlight, sideOrigin.nextSibling);
        }
        sideHighlight.classList.remove('cvl-mobile-highlight-side');
      }
    };

    syncMobileHighlights();
    if (mobileQuery.addEventListener) {
      mobileQuery.addEventListener('change', syncMobileHighlights);
    } else if (mobileQuery.addListener) {
      mobileQuery.addListener(syncMobileHighlights);
    }
  }

  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.querySelectorAll('[data-cvl-highlight-rotation]').forEach(function (media) {
    var slides = Array.prototype.slice.call(media.querySelectorAll('.cvl-homepage-highlight-slide'));

    if (slides.length < 2 || reducedMotion) {
      return;
    }

    var delay = parseInt(media.getAttribute('data-cvl-rotation-ms'), 10);
    delay = Number.isFinite(delay) ? Math.max(2000, Math.min(60000, delay)) : 5000;

    var index = 0;
    var timer = null;
    var visible = true;
    var paused = false;
    var card = media.closest('.cvl-homepage-highlight');

    function show(nextIndex) {
      slides[index].classList.remove('is-active');
      slides[index].setAttribute('aria-hidden', 'true');

      index = nextIndex % slides.length;

      slides[index].classList.add('is-active');
      slides[index].setAttribute('aria-hidden', 'false');
    }

    function stop() {
      if (timer !== null) {
        window.clearInterval(timer);
        timer = null;
      }
    }

    function start() {
      stop();

      if (!visible || paused || document.hidden) {
        return;
      }

      timer = window.setInterval(function () {
        show(index + 1);
      }, delay);
    }

    if (card) {
      card.addEventListener('mouseenter', function () {
        paused = true;
        stop();
      });

      card.addEventListener('mouseleave', function () {
        paused = false;
        start();
      });

      card.addEventListener('focusin', function () {
        paused = true;
        stop();
      });

      card.addEventListener('focusout', function () {
        paused = false;
        start();
      });
    }

    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.target !== media) {
            return;
          }

          visible = entry.isIntersecting;
          if (visible) {
            start();
          } else {
            stop();
          }
        });
      }, { threshold: 0.12 });

      observer.observe(media);
    }

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        stop();
      } else {
        start();
      }
    });

    start();
  });
})();
