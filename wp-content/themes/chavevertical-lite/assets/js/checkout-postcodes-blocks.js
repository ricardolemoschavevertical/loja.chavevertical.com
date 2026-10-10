/**
 * WooCommerce Checkout Blocks — feedback apenas para códigos postais PT.
 *
 * Não toca no estado React, não substitui a validação dos Woo Blocks,
 * não modifica pagamentos, entrega/faturação nem outros países.
 * O Store API faz a normalização/validação final no servidor.
 */
(function () {
    'use strict';

    var rootSelector = '.wc-block-checkout, .wp-block-woocommerce-checkout';
    var supported = {
        billing: '#billing-postcode',
        shipping: '#shipping-postcode'
    };
    var helpUrl = 'https://www.ctt.pt/feapl_2/app/open/postalCodeSearch/postalCodeSearch.jspx';
    var scheduled = false;

    function country(section) {
        var input = document.querySelector('#' + section + '-country');
        if (!input) {
            return '';
        }
        return String(input.value || '').trim().toUpperCase();
    }

    function isPT(section) {
        var code = country(section);
        return code === 'PT' || code === 'PORTUGAL' || code === 'PORTUGAL (PT)';
    }

    function ptPostalCode(value) {
        var compact = String(value || '').trim().replace(/[\s-]+/g, '');
        return /^[0-9]{7}$/.test(compact)
            ? compact.slice(0, 4) + '-' + compact.slice(4)
            : String(value || '').trim();
    }

    function findHelp(input) {
        var parent = input.closest('.wc-block-components-text-input')
            || input.parentElement;
        if (!parent) {
            return null;
        }

        var next = parent.nextElementSibling;
        if (next && next.classList.contains('cvl-postcode-block-help')) {
            return next;
        }

        var container = document.createElement('div');
        container.className = 'cvl-postcode-block-help';

        var hint = document.createElement('small');
        hint.className = 'cvl-postcode-block-hint';
        hint.appendChild(document.createTextNode('Formato português: 1234-567. '));

        var lookup = document.createElement('a');
        lookup.href = helpUrl;
        lookup.target = '_blank';
        lookup.rel = 'noopener noreferrer';
        lookup.textContent = 'Confirmar a morada nos CTT';
        hint.appendChild(lookup);

        var status = document.createElement('span');
        status.className = 'cvl-postcode-block-error';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');

        container.appendChild(hint);
        container.appendChild(status);
        parent.insertAdjacentElement('afterend', container);
        return container;
    }

    function render(section) {
        var root = document.querySelector(rootSelector);
        if (!root) {
            return;
        }

        var input = root.querySelector(supported[section]);
        if (!input) {
            return;
        }

        var help = findHelp(input);
        if (!help) {
            return;
        }

        var pt = isPT(section);
        if (help.hidden !== !pt) {
            help.hidden = !pt;
        }
        if (!pt) {
            return; // Estrangeiros: não aplicar regras portuguesas.
        }

        var value = String(input.value || '').trim();
        var visible = input.getClientRects().length > 0;
        var touched = input.dataset.cvlPostcodeTouched === 'yes';
        var enough = value.replace(/[\s-]+/g, '').length >= 7;

        var message = '';
        if (visible && value && (touched || enough) && !/^[0-9]{4}-[0-9]{3}$/.test(value)) {
            if (/^[0-9]{7}$/.test(value.replace(/[\s-]+/g, ''))) {
                message = 'Separe os primeiros quatro algarismos com um hífen: ' + ptPostalCode(value) + '.';
            } else {
                message = 'Código postal português inválido. Utilize o formato 1234-567.';
            }
        }

        var status = help.querySelector('.cvl-postcode-block-error');
        if (status && status.textContent !== message) {
            status.textContent = message;
        }
        var invalid = !!message;
        if (help.classList.contains('is-invalid') !== invalid) {
            help.classList.toggle('is-invalid', invalid);
        }
        // Não modificar aria-invalid no input: React/Woo gere a validação oficial.
    }

    function update() {
        scheduled = false;
        render('billing');
        render('shipping');
    }

    function scheduleUpdate() {
        if (scheduled) {
            return;
        }
        scheduled = true;
        window.requestAnimationFrame(update);
    }

    function onFieldChange(event) {
        var target = event.target;
        if (!target || !target.id) {
            return;
        }
        if (target.id === 'billing-postcode' || target.id === 'shipping-postcode') {
            if (event.type === 'focusout') {
                target.dataset.cvlPostcodeTouched = 'yes';
            }
            scheduleUpdate();
            return;
        }
        if (target.id === 'billing-country' || target.id === 'shipping-country') {
            scheduleUpdate();
        }
    }

    document.addEventListener('input', onFieldChange, true);
    document.addEventListener('change', onFieldChange, true);
    document.addEventListener('focusout', onFieldChange, true);

    function init() {
        scheduleUpdate();
        // Os campos React podem aparecer depois do carregamento inicial
        // ou ser desmontados quando o cliente alterna a morada.
        if (!('MutationObserver' in window) || !document.body) {
            return;
        }

        var observer = new MutationObserver(function (mutations) {
            var changed = mutations.some(function (mutation) {
                return mutation.type === 'childList'
                    && (mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0);
            });
            if (changed) {
                scheduleUpdate();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
