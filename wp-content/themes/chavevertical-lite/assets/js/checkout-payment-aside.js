/**
 * CHAVE VERTICAL — pagamento na coluna direita do WooCommerce Checkout Blocks.
 *
 * Os componentes React nao sao movidos, clonados nem substituidos.
 * Apenas se calcula o lugar do pagamento, termos e botao de submissao,
 * mantendo-os no formulario WooCommerce e preservando a Store API.
 *
 * Em mobile/tablet, ou se a estrutura WC for diferente, usa o layout nativo.
 */
(function () {
    'use strict';

    var ACTIVE_CLASS = 'cvl-checkout-payment-right';
    var BREAKPOINT = '(min-width:1100px)';
    var VARIABLES = [
        '--cvl-payment-side-left',
        '--cvl-payment-side-width',
        '--cvl-payment-side-top',
        '--cvl-payment-terms-top',
        '--cvl-payment-actions-top',
        '--cvl-payment-side-min-height'
    ];
    var pending = false;
    var resizeObserver = null;
    var observedNodes = [];
    var started = false;
    var media = window.matchMedia ? window.matchMedia(BREAKPOINT) : null;

    function measureableParts() {
        var layout = document.querySelector('.wc-block-components-sidebar-layout.wc-block-checkout');
        if (!layout || !layout.classList.contains('is-large')) {
            return null;
        }

        var main = layout.querySelector('.wc-block-checkout__main');
        var sidebar = layout.querySelector('.wc-block-checkout__sidebar');
        var summary = sidebar && sidebar.querySelector('.wp-block-woocommerce-checkout-order-summary-block');
        var form = main && main.querySelector('form.wc-block-checkout__form');
        var payment = form && form.querySelector('#payment-method.wp-block-woocommerce-checkout-payment-block');
        var terms = form && form.querySelector('.wc-block-checkout__terms');
        var actions = form && form.querySelector('.wc-block-checkout__actions');

        return (main && sidebar && summary && form && payment && terms && actions)
            ? {layout:layout, main:main, sidebar:sidebar, summary:summary,
               payment:payment, terms:terms, actions:actions}
            : null;
    }

    function clearAside() {
        if (!document.body.classList.contains(ACTIVE_CLASS)) {
            return;
        }
        document.body.classList.remove(ACTIVE_CLASS);
        VARIABLES.forEach(function (variable) {
            document.body.style.removeProperty(variable);
        });
    }

    function setVariable(name, value) {
        var next = String(Math.round(value)) + 'px';
        if (document.body.style.getPropertyValue(name) !== next) {
            document.body.style.setProperty(name, next);
        }
    }

    function watchSizes(nodes) {
        if (!resizeObserver) {
            return;
        }
        if (nodes.length === observedNodes.length && nodes.every(function (el, i) {
            return el === observedNodes[i];
        })) {
            return;
        }
        resizeObserver.disconnect();
        observedNodes = nodes;
        nodes.forEach(function (el) { resizeObserver.observe(el); });
    }

    function synchronize() {
        pending = false;
        var parts = measureableParts();
        if (!media || !media.matches || !parts) {
            clearAside();
            watchSizes(parts ? [parts.summary, parts.payment, parts.terms, parts.actions] : []);
            return;
        }

        var layoutRect = parts.layout.getBoundingClientRect();
        var mainRect = parts.main.getBoundingClientRect();
        var summaryRect = parts.summary.getBoundingClientRect();

        // A sidebar deve estar efetivamente ao lado, nao por cima do form.
        if (summaryRect.width < 310 || summaryRect.height < 60 ||
            summaryRect.left < mainRect.right - 5 ||
            !layoutRect.width || !parts.payment.getBoundingClientRect().height) {
            clearAside();
            return;
        }

        var left = summaryRect.left - layoutRect.left;
        var top = summaryRect.bottom - layoutRect.top + 18;
        setVariable('--cvl-payment-side-left', left);
        setVariable('--cvl-payment-side-width', summaryRect.width);
        setVariable('--cvl-payment-side-top', top);

        // A classe so e aplicada quando todos os elementos foram validados.
        document.body.classList.add(ACTIVE_CLASS);

        // Medir depois de aplicar a largura da coluna; gateways sao dinamicos.
        var paymentHeight = parts.payment.getBoundingClientRect().height;
        var termsTop = top + paymentHeight + 15;
        setVariable('--cvl-payment-terms-top', termsTop);

        var termsHeight = parts.terms.getBoundingClientRect().height;
        var actionsTop = termsTop + termsHeight + 14;
        setVariable('--cvl-payment-actions-top', actionsTop);

        var actionsHeight = parts.actions.getBoundingClientRect().height;
        setVariable('--cvl-payment-side-min-height', actionsTop + actionsHeight + 35);
        watchSizes([parts.summary, parts.payment, parts.terms, parts.actions]);
    }

    function schedule() {
        if (pending) {
            return;
        }
        pending = true;
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(synchronize);
        } else {
            window.setTimeout(synchronize, 40);
        }
    }

    function boot() {
        if (started || !media || !document.body) {
            return;
        }
        started = true;

        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(schedule);
        }
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function (mutations) {
                if (mutations.some(function (mutation) {
                    return mutation.type === 'childList' &&
                        (mutation.addedNodes.length || mutation.removedNodes.length);
                })) {
                    schedule();
                }
            });
            // React pode substituir campos quando o metodo de pagamento muda.
            observer.observe(document.body, {childList:true, subtree:true});
        }

        window.addEventListener('resize', schedule, {passive:true});
        if (typeof media.addEventListener === 'function') {
            media.addEventListener('change', schedule);
        } else if (typeof media.addListener === 'function') {
            media.addListener(schedule);
        }
        schedule();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once:true});
    } else {
        boot();
    }
})();
