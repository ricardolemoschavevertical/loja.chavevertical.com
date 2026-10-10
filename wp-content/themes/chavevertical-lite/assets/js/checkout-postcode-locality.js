/**
 * Preencher localidade a partir do CP7 português, sem botão.
 * Compatível com WooCommerce Checkout Blocks e checkout clássico.
 *
 * Apenas altera billing_city OU shipping_city, consoante o próprio campo.
 * Uma localidade escrita pelo cliente nunca é substituída pela API.
 * A confirmação de morada e a validação final continuam no WooCommerce.
 */
(function () {
    'use strict';

    var options = window.CVLPostcodeLocality || {};
    var sections = ['billing', 'shipping'];
    var state = {
        billing: { timer: null, request: 0, code: '', autoValue: '', autoCode: '', attempted: false, attemptsToRestore: 0 },
        shipping: { timer: null, request: 0, code: '', autoValue: '', autoCode: '', attempted: false, attemptsToRestore: 0 }
    };
    var known = Object.create(null);
    var scheduled = false;
    var activeControllers = { billing: null, shipping: null };

    function field(section, name) {
        return document.getElementById(section + '-' + name)
            || document.getElementById(section + '_' + name);
    }

    function canonicalPostcode(raw) {
        var digits = String(raw || '').trim().replace(/[\s-]+/g, '');
        if (!/^[0-9]{7}$/.test(digits)) {
            return '';
        }
        return digits.slice(0, 4) + '-' + digits.slice(4);
    }

    function countryIsPortugal(section) {
        var f = field(section, 'country');
        if (!f) {
            return false;
        }
        return String(f.value || '').trim().toUpperCase() === 'PT';
    }

    function isVisible(input) {
        return !!input
            && input.getClientRects().length > 0
            && !input.closest('[hidden], [aria-hidden="true"]');
    }

    function messageNode(section, postcodeInput) {
        var id = 'cvl-postcode-locality-' + section;
        var parent = postcodeInput.closest('.wc-block-components-text-input, .form-row')
            || postcodeInput.parentElement;
        if (!parent) {
            return null;
        }

        var existing = document.getElementById(id);
        if (existing && existing.previousElementSibling === parent) {
            return existing;
        }
        if (existing) {
            existing.remove();
        }

        var node = document.createElement('span');
        node.className = 'cvl-postcode-locality-status';
        node.id = id;
        node.setAttribute('role', 'status');
        node.setAttribute('aria-live', 'polite');
        parent.insertAdjacentElement('afterend', node);
        return node;
    }

    function showMessage(section, message, kind) {
        var postcodeInput = field(section, 'postcode');
        if (!postcodeInput) {
            return;
        }

        var container = messageNode(section, postcodeInput);
        if (!container) {
            return;
        }

        if (container.textContent !== message) {
            container.textContent = message;
        }
        container.className = 'cvl-postcode-locality-status' + (kind ? ' is-' + kind : '');
        container.hidden = !message;
    }

    /**
     * React controla os valores de Blocks: usar o setter nativo permite que
     * o evento input seja observado pelo React e atualizado no estado real.
     */
    function updateTextValue(input, value) {
        if (input.value === value) {
            return true;
        }

        if (input.tagName === 'SELECT') {
            var match = Array.prototype.some.call(input.options, function (option) {
                return option.value === value || option.textContent.trim().toUpperCase() === value.toUpperCase();
            });
            if (!match) {
                return false;
            }
            input.value = value;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }

        if (input.tagName !== 'INPUT' && input.tagName !== 'TEXTAREA') {
            return false;
        }

        var prototype = input.tagName === 'TEXTAREA'
            ? HTMLTextAreaElement.prototype
            : HTMLInputElement.prototype;
        var descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');

        if (descriptor && typeof descriptor.set === 'function') {
            descriptor.set.call(input, value);
        } else {
            input.value = value;
        }

        input.dispatchEvent(new Event('input', { bubbles: true }));
        // WooCommerce clássico e plugins antigos escutam o evento change.
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    function applyLocality(section, code, localidade) {
        var status = state[section];
        var city = field(section, 'city');
        if (!isVisible(city) || !countryIsPortugal(section)) {
            return;
        }

        var current = String(city.value || '').trim();
        var wasOurValue = !!status.autoValue && current === status.autoValue;
        var shouldApply = !current || (wasOurValue && status.autoCode !== code);

        if (current.toLocaleLowerCase('pt-PT') === localidade.toLocaleLowerCase('pt-PT')) {
            showMessage(section, 'Localidade confirmada: ' + localidade + '.', 'success');
            return;
        }

        if (!shouldApply) {
            showMessage(section, 'Localidade sugerida: ' + localidade + '. Mantivemos a que indicou; confirme se está correta.', 'notice');
            return;
        }

        if (updateTextValue(city, localidade)) {
            status.autoValue = localidade;
            status.autoCode = code;
            status.attemptsToRestore++;
            showMessage(section, 'Localidade preenchida automaticamente: ' + localidade + '.', 'success');
        } else {
            showMessage(section, 'Localidade sugerida: ' + localidade + '. Escolha-a no campo Localidade.', 'notice');
        }
    }

    function clearPreviousAutofill(section, cityInput) {
        var info = state[section];
        if (cityInput && info.autoValue && String(cityInput.value || '').trim() === info.autoValue) {
            updateTextValue(cityInput, '');
        }
        info.autoValue = '';
        info.autoCode = '';
        info.attemptsToRestore = 0;
    }

    function cancel(section) {
        var status = state[section];
        status.request += 1;
        if (status.timer) {
            window.clearTimeout(status.timer);
            status.timer = null;
        }
        if (activeControllers[section]) {
            activeControllers[section].abort();
            activeControllers[section] = null;
        }
    }

    function lookup(section, code) {
        if (!options.ajaxUrl || !options.nonce) {
            return;
        }

        var status = state[section];
        var request = ++status.request;
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        activeControllers[section] = controller;

        showMessage(section, 'A procurar a localidade…', 'loading');

        var params = new URLSearchParams({
            action: 'cvl_postcode_locality',
            nonce: options.nonce,
            country: 'PT',
            postcode: code
        });

        var requestOptions = {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: params.toString()
        };
        if (controller) {
            requestOptions.signal = controller.signal;
        }

        fetch(options.ajaxUrl, requestOptions).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function (payload) {
            if (
                status.request !== request
                || status.code !== code
                || !countryIsPortugal(section)
                || canonicalPostcode(field(section, 'postcode') && field(section, 'postcode').value) !== code
            ) {
                return;
            }

            var result = payload && payload.success ? payload.data : null;
            if (!result || typeof result.status !== 'string') {
                showMessage(section, 'Não foi possível preencher automaticamente. Introduza a localidade manualmente.', 'notice');
                return;
            }

            known[code] = result;
            if (result.status === 'found' && typeof result.localidade === 'string' && result.localidade.trim()) {
                applyLocality(section, code, result.localidade.trim());
            } else if (result.status === 'ambiguous') {
                showMessage(section, 'Este código postal pode corresponder a várias localidades. Preencha a localidade manualmente.', 'notice');
            } else {
                showMessage(section, 'Não foi possível identificar a localidade. Pode preenchê-la manualmente.', 'notice');
            }
        }).catch(function (error) {
            if (status.request === request && (!error || error.name !== 'AbortError')) {
                showMessage(section, 'A pesquisa está indisponível. Pode preencher a localidade manualmente.', 'notice');
            }
        }).finally(function () {
            if (status.request === request) {
                activeControllers[section] = null;
            }
        });
    }

    function examine(section, delay) {
        var postcodeField = field(section, 'postcode');
        var cityField = field(section, 'city');
        var status = state[section];

        if (!isVisible(postcodeField) || !isVisible(cityField)) {
            if (status.code) {
                cancel(section);
            }
            status.code = '';
            status.attempted = false;
            showMessage(section, '', '');
            return;
        }

        if (!countryIsPortugal(section)) {
            if (status.code) {
                cancel(section);
            }
            clearPreviousAutofill(section, cityField);
            status.code = '';
            status.attempted = false;
            showMessage(section, '', '');
            return;
        }

        var code = canonicalPostcode(postcodeField.value);
        if (!code) {
            if (status.code) {
                cancel(section);
            }
            clearPreviousAutofill(section, cityField);
            status.code = '';
            status.attempted = false;
            showMessage(section, '', '');
            return;
        }

        // Uma alteração de CP7 não pode deixar uma localidade anterior
        // preenchida automaticamente num endereço diferente.
        if (code !== status.code) {
            cancel(section);
            clearPreviousAutofill(section, cityField);
            status.code = code;
            status.attempted = false;
        }

        if (status.attempted) {
            // React poderá recriar um input, mas não insistir indefinidamente.
            if (status.autoCode === code && status.autoValue && !cityField.value.trim() && status.attemptsToRestore < 2) {
                applyLocality(section, code, status.autoValue);
            }
            return;
        }

        status.attempted = true;
        if (known[code]) {
            var result = known[code];
            if (result.status === 'found' && typeof result.localidade === 'string') {
                applyLocality(section, code, result.localidade);
            } else {
                showMessage(section, 'Localidade não encontrada automaticamente. Preencha-a manualmente.', 'notice');
            }
            return;
        }

        status.timer = window.setTimeout(function () {
            status.timer = null;
            // Abortadas/alteradas entretanto? Não chamar a API.
            if (status.code === code && countryIsPortugal(section)) {
                lookup(section, code);
            }
        }, delay);
    }

    function inspectAll() {
        scheduled = false;
        sections.forEach(function (section) {
            examine(section, 350);
        });
    }

    function scheduleInspect() {
        if (scheduled) {
            return;
        }
        scheduled = true;
        window.requestAnimationFrame(inspectAll);
    }

    function onChange(event) {
        var input = event.target;
        if (!input || !input.id) {
            return;
        }

        sections.forEach(function (section) {
            if (
                input.id === section + '-postcode'
                || input.id === section + '_postcode'
                || input.id === section + '-country'
                || input.id === section + '_country'
            ) {
                examine(section, event.type === 'focusout' ? 0 : 350);
            }
        });
    }

    document.addEventListener('input', onChange, true);
    document.addEventListener('change', onChange, true);
    document.addEventListener('focusout', onChange, true);

    function init() {
        scheduleInspect();
        if (!document.body || !('MutationObserver' in window)) {
            return;
        }
        var observer = new MutationObserver(function (mutations) {
            // Ignorar alterações de texto: só os campos React criados/removidos.
            var interesting = mutations.some(function (mutation) {
                if (mutation.type !== 'childList') {
                    return false;
                }
                return Array.prototype.some.call(mutation.addedNodes, function (node) {
                    return node.nodeType === 1
                        && (node.matches && (
                            node.matches('input, select, .wc-block-checkout, .wc-block-components-address-form, form.checkout')
                            || node.querySelector('input[id$="-postcode"],input[id$="_postcode"]')
                        ));
                });
            });
            if (interesting) {
                scheduleInspect();
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
