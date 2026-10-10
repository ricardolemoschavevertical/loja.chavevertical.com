/**
 * Chave Vertical: radio buttons for the native WooCommerce Blocks fiscal select.
 *
 * WC 11.1.2 supports text/select/checkbox additional fields, not a radio field.
 * Keep the original, required "cvl-fiscal/customer-type" SELECT intact and
 * update it with real input/change events. The Woo checkout data store stays
 * authoritative; no duplicated order metadata or checkout field registration.
 *
 * Safe fallback: the native select remains visible unless the companion
 * accessible radio controls are mounted. The radio group is the ONLY visual
 * substitute and existing conditional NIF/NIPC fields stay Woo-owned.
 */
(function () {
    'use strict';

    var FIELD_ID = 'order-cvl-fiscal-customer-type';
    var GROUP_ID = 'cvl-fiscal-customer-radio-group';
    var DETAILS_TITLE_ID = 'cvl-fiscal-fields-heading';
    var ROOT_CLASS = 'cvl-fiscal-radio-layout';
    var HIDE_CLASS = 'cvl-fiscal-native-select-hidden';
    var OPTIONS = [
        { value: 'particular', label: 'Particular' },
        { value: 'empresa', label: 'Empresa' }
    ];

    var scheduled = false;
    var observer = null;
    var initialized = false;

    function nativeSelect() {
        return document.getElementById(FIELD_ID);
    }

    function nativeWrapper(select) {
        return select && select.closest('.wc-block-components-select-input-cvl-fiscal-customer-type');
    }

    function choiceGroup() {
        return document.getElementById(GROUP_ID);
    }

    function makeElement(tag, cls, text) {
        var el = document.createElement(tag);
        if (cls) {
            el.className = cls;
        }
        if (text) {
            el.textContent = text;
        }
        return el;
    }

    function makeRadios() {
        var fieldset = makeElement('fieldset', 'cvl-fiscal-customer-radios');
        fieldset.id = GROUP_ID;
        fieldset.setAttribute('aria-required', 'true');

        var legend = makeElement('legend', 'cvl-fiscal-customer-radios__legend', 'Tipo de cliente para faturação');
        var required = makeElement('span', 'cvl-fiscal-customer-radios__required', ' *');
        required.setAttribute('aria-hidden', 'true');
        legend.appendChild(required);
        fieldset.appendChild(legend);

        var options = makeElement('div', 'cvl-fiscal-customer-radios__options');
        OPTIONS.forEach(function (option) {
            var label = makeElement('label', 'cvl-fiscal-customer-radios__option');
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'cvl_fiscal_customer_type_visual';
            radio.value = option.value;
            radio.required = true;
            radio.className = 'cvl-fiscal-customer-radios__input';
            radio.setAttribute('aria-label', option.label);
            label.appendChild(radio);
            label.appendChild(makeElement('span', 'cvl-fiscal-customer-radios__label', option.label));
            options.appendChild(label);
        });
        fieldset.appendChild(options);

        var error = makeElement('p', 'cvl-fiscal-customer-radios__error');
        error.id = 'cvl-fiscal-choice-error';
        error.setAttribute('role', 'alert');
        error.setAttribute('aria-live', 'polite');
        error.hidden = true;
        fieldset.appendChild(error);

        fieldset.addEventListener('change', function (event) {
            var radio = event.target;
            if (!radio || radio.type !== 'radio' || !OPTIONS.some(function (o) { return o.value === radio.value; })) {
                return;
            }

            var select = nativeSelect();
            if (!select) {
                return;
            }

            try {
                // React listens to the native SELECT change event and writes
                // checkout.additional_fields. Do not mutate Woo's internal API.
                var descriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
                if (descriptor && typeof descriptor.set === 'function') {
                    descriptor.set.call(select, radio.value);
                } else {
                    select.value = radio.value;
                }
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (_error) {
                select.value = radio.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }

            setInvalid(fieldset, false);
            scheduleSync();
        });

        return fieldset;
    }

    function setInvalid(group, invalid) {
        if (!group) {
            return;
        }

        group.classList.toggle('cvl-fiscal-choice-invalid', invalid);
        var error = group.querySelector('.cvl-fiscal-customer-radios__error');
        if (error) {
            error.hidden = !invalid;
            error.textContent = invalid ? 'Selecione Particular ou Empresa para continuar.' : '';
        }
    }

    function sync() {
        scheduled = false;
        var select = nativeSelect();
        var wrapper = nativeWrapper(select);
        var parent = wrapper && wrapper.parentElement;
        if (!select || !wrapper || !parent || parent.id !== 'order') {
            return;
        }

        var group = choiceGroup();
        if (!group || group.parentElement !== parent) {
            if (group) {
                group.remove();
            }
            group = makeRadios();
            // Insert alongside React's native field, not inside controlled
            // form inputs. React retains responsibility for NIF/NIPC fields.
            parent.insertBefore(group, wrapper);
        }

        var value = String(select.value || '');
        var valid = value === 'particular' || value === 'empresa';

        // Titulos acessiveis e alinhados entre as duas colunas fiscais.
        // O WooCommerce continua responsavel pelos campos e pela validacao.
        var detailsTitle = document.getElementById(DETAILS_TITLE_ID);
        if (!detailsTitle || detailsTitle.parentElement !== parent) {
            if (detailsTitle) {
                detailsTitle.remove();
            }
            detailsTitle = makeElement('p', 'cvl-fiscal-fields-heading');
            detailsTitle.id = DETAILS_TITLE_ID;
            detailsTitle.setAttribute('role', 'heading');
            detailsTitle.setAttribute('aria-level', '3');
            parent.insertBefore(detailsTitle, wrapper);
        }
        var title = value === 'empresa' ? 'Dados da empresa' : 'Identificação fiscal';
        if (detailsTitle.textContent !== title) {
            detailsTitle.textContent = title;
        }
        detailsTitle.hidden = !valid;
        group.querySelectorAll('input[type="radio"]').forEach(function (radio) {
            if (radio.checked !== (radio.value === value)) {
                radio.checked = radio.value === value;
            }
        });
        parent.classList.add(ROOT_CLASS);
        wrapper.classList.add(HIDE_CLASS);
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        var isInvalid = select.getAttribute('aria-invalid') === 'true';
        if (valid) {
            setInvalid(group, false);
        } else if (isInvalid && !group.classList.contains('cvl-fiscal-choice-invalid')) {
            setInvalid(group, true);
        }

        // Accessibility: native conditional inputs remain focusable,
        // and the actual selector is still required by the Woo Store API.
    }

    function scheduleSync() {
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

    function install() {
        if (initialized) {
            return;
        }
        initialized = true;

        // This event updates the companion radio buttons after WC's own
        // state or autofill changes the underlying SELECT.
        document.addEventListener('change', function (event) {
            if (event.target && event.target.id === FIELD_ID) {
                scheduleSync();
            }
        }, true);

        // If the buyer places an order without a choice, show the required
        // error beside the visual radio buttons; Woo also validates in PHP.
        document.addEventListener('click', function (event) {
            if (!event.target || !event.target.closest('.wc-block-components-checkout-place-order-button')) {
                return;
            }
            var select = nativeSelect();
            var group = choiceGroup();
            if (select && group && select.value !== 'particular' && select.value !== 'empresa') {
                setInvalid(group, true);
            }
        }, true);

        if (typeof MutationObserver === 'function' && document.body) {
            observer = new MutationObserver(function (mutations) {
                if (!nativeSelect()) {
                    return;
                }
                var changed = mutations.some(function (mutation) {
                    return mutation.type === 'childList'
                        && (mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0);
                });
                if (changed) {
                    scheduleSync();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

        scheduleSync();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', install, { once: true });
    } else {
        install();
    }
})();
