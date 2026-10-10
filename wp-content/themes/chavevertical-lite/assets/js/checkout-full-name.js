/**
 * CHAVE VERTICAL — um só campo "Nome completo" no checkout Blocks.
 *
 * WooCommerce/transportadoras ainda usam billing/shipping first_name,
 * last_name. Este controlo visual divide o nome nos dois campos nativos
 * através do cart data store oficial. Nunca muda os dados da encomenda
 * diretamente e nunca elimina os campos obrigatórios do Store API.
 *
 * Sem JS / caso o store não esteja disponível: os campos nativos continuam
 * visíveis e o checkout mantém o seu comportamento original.
 */
(function () {
    'use strict';

    var STORE = 'wc/store/cart';
    var SECTIONS = ['billing', 'shipping'];
    var state = {
        billing: { editing: false, touched: false },
        shipping: { editing: false, touched: false }
    };
    var started = false;
    var scheduled = false;

    function store() {
        try {
            var data = window.wp && window.wp.data;
            if (!data) {
                return null;
            }
            var query = data.select(STORE);
            var actions = data.dispatch(STORE);
            if (
                !query || typeof query.getCustomerData !== 'function'
                || !actions
                || typeof actions.setBillingAddress !== 'function'
                || typeof actions.setShippingAddress !== 'function'
            ) {
                return null;
            }
            return { query: query, actions: actions };
        } catch (_error) {
            return null;
        }
    }

    function normalize(value) {
        return String(value || '').trim().replace(/\s+/gu, ' ');
    }

    function splitName(value) {
        var name = normalize(value);
        if (!name) {
            return { first_name: '', last_name: '' };
        }
        var index = name.lastIndexOf(' ');
        if (index <= 0 || index >= name.length - 1) {
            return { first_name: name, last_name: '' };
        }
        return {
            first_name: name.slice(0, index),
            last_name: name.slice(index + 1)
        };
    }

    function joinName(address) {
        if (!address || typeof address !== 'object') {
            return '';
        }
        return normalize(
            String(address.first_name || '') + ' ' + String(address.last_name || '')
        );
    }

    function addressDetails(section, context) {
        var customer = context.query.getCustomerData();
        var key = section + 'Address';
        var address = customer && customer[key];
        return address && typeof address === 'object' ? address : null;
    }

    function form(section) {
        return document.getElementById(section);
    }

    function nativeField(section, fieldName) {
        return document.getElementById(section + '-' + fieldName);
    }

    function control(section) {
        return document.getElementById('cvl-' + section + '-full-name');
    }

    function visible(controlNode) {
        return !!controlNode && controlNode.getClientRects().length > 0;
    }

    function makeFullNameField(section) {
        var wrapper = document.createElement('div');
        wrapper.className = 'cvl-full-name-field';
        wrapper.id = 'cvl-' + section + '-full-name-field';

        var label = document.createElement('label');
        label.htmlFor = 'cvl-' + section + '-full-name';
        label.className = 'cvl-full-name-label';
        label.appendChild(document.createTextNode('Nome completo'));
        var asterisk = document.createElement('span');
        asterisk.className = 'cvl-full-name-required';
        asterisk.setAttribute('aria-hidden', 'true');
        asterisk.textContent = ' *';
        label.appendChild(asterisk);

        var input = document.createElement('input');
        input.id = label.htmlFor;
        input.className = 'cvl-full-name-input';
        input.type = 'text';
        input.autocomplete = 'name';
        input.placeholder = 'Nome e apelido';
        input.required = true;
        input.maxLength = 180;
        input.setAttribute('aria-describedby', input.id + '-message');

        var message = document.createElement('span');
        message.id = input.id + '-message';
        message.className = 'cvl-full-name-message';
        message.setAttribute('role', 'status');
        message.setAttribute('aria-live', 'polite');

        wrapper.appendChild(label);
        wrapper.appendChild(input);
        wrapper.appendChild(message);

        input.addEventListener('focus', function () {
            state[section].editing = true;
        });
        input.addEventListener('input', function () {
            state[section].touched = true;
            updateAddress(section, input.value);
            validate(section, false);
        });
        input.addEventListener('change', function () {
            updateAddress(section, input.value);
            validate(section, false);
        });
        input.addEventListener('blur', function () {
            state[section].editing = false;
            state[section].touched = true;
            var compact = normalize(input.value);
            if (input.value !== compact) {
                input.value = compact;
            }
            updateAddress(section, compact);
            validate(section, false);
            schedule();
        });

        return wrapper;
    }

    function updateAddress(section, fullName) {
        var context = store();
        if (!context) {
            return;
        }
        var address = addressDetails(section, context);
        if (!address) {
            return;
        }
        var parsed = splitName(fullName);
        if (
            String(address.first_name || '') === parsed.first_name
            && String(address.last_name || '') === parsed.last_name
        ) {
            return;
        }
        var updated = Object.assign({}, address, parsed);
        if (section === 'billing') {
            context.actions.setBillingAddress(updated);
        } else {
            context.actions.setShippingAddress(updated);
        }
    }

    function validate(section, onSubmit) {
        var input = control(section);
        var wrapper = document.getElementById('cvl-' + section + '-full-name-field');
        if (!input || !wrapper || input.disabled || !visible(input)) {
            return true;
        }
        var valid = splitName(input.value).last_name !== '';
        var shouldShow = (state[section].touched || onSubmit) && !valid;
        var message = wrapper.querySelector('.cvl-full-name-message');

        // A validação do Store API continua a exigir first_name + last_name.
        input.setCustomValidity(shouldShow ? 'Indique o nome completo, incluindo o apelido.' : '');
        input.setAttribute('aria-invalid', shouldShow ? 'true' : 'false');
        wrapper.classList.toggle('cvl-full-name-invalid', shouldShow);
        if (message) {
            message.textContent = shouldShow
                ? 'Introduza o nome completo, incluindo o apelido.'
                : '';
        }
        return valid;
    }

    function ensureControl(section, context) {
        var container = form(section);
        var first = nativeField(section, 'first_name');
        var last = nativeField(section, 'last_name');

        // Only hide the native pair once the alternative field was installed.
        if (!container || !first || !last) {
            return;
        }
        var firstWrapper = first.closest('.wc-block-components-text-input');
        var lastWrapper = last.closest('.wc-block-components-text-input');
        if (!firstWrapper || !lastWrapper || firstWrapper.parentElement !== container) {
            return;
        }
        var wrapperId = 'cvl-' + section + '-full-name-field';
        var wrapper = document.getElementById(wrapperId);
        if (!wrapper || wrapper.parentElement !== container) {
            if (wrapper) {
                wrapper.remove();
            }
            wrapper = makeFullNameField(section);
            container.insertBefore(wrapper, firstWrapper);
        }
        container.classList.add('cvl-full-name-enabled');

        var input = control(section);
        if (!input) {
            return;
        }

        var address = addressDetails(section, context);
        var storedValue = joinName(address);

        if (!state[section].editing && document.activeElement !== input && input.value !== storedValue) {
            input.value = storedValue;
            if (storedValue) {
                // Previously saved customer names are not edited, just shown.
                state[section].touched = false;
            }
        }

        // Hidden shipping fields cannot block checkout with an unfocusable
        // required field; the billing-first integration handles the copy.
        var active = visible(container);
        input.disabled = !active;
        input.required = active;
        if (!active) {
            input.setCustomValidity('');
        }
        if (active && state[section].touched) {
            validate(section, false);
        }
    }

    function sync() {
        scheduled = false;
        if (!document.querySelector('.wc-block-checkout')) {
            return;
        }
        var context = store();
        if (!context) {
            return; // Native separate name fields remain visible.
        }
        SECTIONS.forEach(function (section) {
            ensureControl(section, context);
        });
    }

    function schedule() {
        if (scheduled) {
            return;
        }
        scheduled = true;
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(sync);
        } else {
            window.setTimeout(sync, 0);
        }
    }

    function onPlaceOrder(event) {
        if (
            !event.target
            || !event.target.closest('.wc-block-components-checkout-place-order-button')
        ) {
            return;
        }
        for (var i = 0; i < SECTIONS.length; i++) {
            var section = SECTIONS[i];
            var input = control(section);
            if (input && !input.disabled && visible(input) && !validate(section, true)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                input.focus();
                return;
            }
        }
    }

    function init() {
        if (started) {
            return;
        }
        started = true;

        if (window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function') {
            window.wp.data.subscribe(schedule);
        }
        // Avoid direct modifications to React-owned first_name / last_name.
        document.addEventListener('click', onPlaceOrder, true);

        // A opção "usar faturação para entrega" altera a visibilidade por
        // classe CSS, mesmo quando o checkout não muda dados no Store API.
        document.addEventListener('change', function (event) {
            if (event.target && event.target.id === 'order-cvl-fiscal-use-billing-for-shipping') {
                window.setTimeout(schedule, 150);
            }
        }, true);

        if (typeof MutationObserver === 'function' && document.body) {
            var classObserver = new MutationObserver(schedule);
            classObserver.observe(document.body, {
                attributes: true,
                attributeFilter: ['class']
            });
            var observer = new MutationObserver(function (mutations) {
                if (mutations.some(function (mutation) {
                    return mutation.type === 'childList'
                        && (mutation.addedNodes.length || mutation.removedNodes.length);
                })) {
                    schedule();
                }
            });
            observer.observe(document.body, { subtree: true, childList: true });
        }
        schedule();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
