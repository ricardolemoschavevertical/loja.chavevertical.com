/**
 * Chave Vertical — preenchimento automático da localidade pelo CP7.
 *
 * WooCommerce Blocks: API suportada wp.data, store wc/store/cart.
 * Checkout clássico: eventos input/change nos campos de faturação/entrega.
 * Não modifica DOM gerido pelo React e não cria encomendas ou pagamentos.
 *
 * Nunca substitui uma localidade escrita pelo cliente. Se não for possível
 * consultar o serviço, o campo continua totalmente editável.
 */
(function () {
    'use strict';

    var config = window.CVLPostcodeLocality || {};
    var known = Object.create(null);
    var sections = ['billing', 'shipping'];
    var state = {
        billing: { postcode: '', request: 0, timer: null, controller: null, autoCity: '', autoPostcode: '' },
        shipping: { postcode: '', request: 0, timer: null, controller: null, autoCity: '', autoPostcode: '' }
    };
    var blockStore = 'wc/store/cart';
    var checkoutStore = 'wc/store/checkout';
    var classicInitialized = false;
    var blocksInitialized = false;

    function normalize(value) {
        var digits = String(value || '').trim().replace(/[\s-]+/g, '');
        if (!/^[0-9]{7}$/.test(digits)) {
            return '';
        }
        return digits.slice(0, 4) + '-' + digits.slice(4);
    }

    function getBlocksData() {
        if (!window.wp || !window.wp.data) {
            return null;
        }

        try {
            var cart = window.wp.data.select(blockStore);
            if (!cart || typeof cart.getCustomerData !== 'function') {
                return null;
            }

            var customer = cart.getCustomerData();
            if (!customer || typeof customer !== 'object') {
                return null;
            }

            var dispatch = window.wp.data.dispatch(blockStore);
            if (
                !dispatch || typeof dispatch.setBillingAddress !== 'function'
                || typeof dispatch.setShippingAddress !== 'function'
            ) {
                return null;
            }

            var checkout = window.wp.data.select(checkoutStore);
            var sameAddress = checkout && typeof checkout.getUseShippingAsBilling === 'function'
                ? checkout.getUseShippingAsBilling() === true
                : false;
            var needsShipping = typeof cart.getNeedsShipping === 'function'
                ? cart.getNeedsShipping() !== false
                : true;

            return {
                customer: customer,
                dispatch: dispatch,
                sameAddress: sameAddress,
                needsShipping: needsShipping
            };
        } catch (_error) {
            return null;
        }
    }

    function currentAddress(section, context) {
        return context.customer[section === 'billing' ? 'billingAddress' : 'shippingAddress'];
    }

    function hasIndependentAddress(section, context) {
        if (section === 'shipping') {
            return context.needsShipping;
        }
        // O WooCommerce copia a morada de entrega para faturação.
        // Se estiver ativa esta opção, não disparar pesquisa dupla.
        return !context.sameAddress || !context.needsShipping;
    }

    function cancel(section) {
        var record = state[section];
        record.request++;
        if (record.timer) {
            clearTimeout(record.timer);
            record.timer = null;
        }
        if (record.controller) {
            record.controller.abort();
            record.controller = null;
        }
    }

    function blocksApply(section, code, locality) {
        var context = getBlocksData();
        if (!context || !hasIndependentAddress(section, context)) {
            return;
        }

        var address = currentAddress(section, context);
        if (!address || address.country !== 'PT' || normalize(address.postcode) !== code) {
            return;
        }

        var record = state[section];
        var city = String(address.city || '').trim();
        if (city && city !== record.autoCity) {
            // A localidade já foi escrita pelo cliente: não substituir.
            return;
        }
        if (city === locality) {
            return;
        }

        record.autoCity = locality;
        record.autoPostcode = code;
        var updated = Object.assign({}, address, { city: locality });
        if (section === 'billing') {
            context.dispatch.setBillingAddress(updated);
        } else {
            context.dispatch.setShippingAddress(updated);
            // Woo Blocks nem sempre propaga ao endereço de faturação
            // oculto quando ambos são o mesmo. Corrigir sem tocar nos restantes
            // campos fiscais nem no tipo de cliente.
            if (context.sameAddress) {
                var current = context.customer.billingAddress || {};
                context.dispatch.setBillingAddress(
                    Object.assign({}, current, { city: locality })
                );
            }
        }
    }

    function blocksClearOldCity(section, current, record) {
        if (!record.autoCity || !current || String(current.city || '').trim() !== record.autoCity) {
            record.autoCity = '';
            record.autoPostcode = '';
            return;
        }
        var context = getBlocksData();
        if (!context || !hasIndependentAddress(section, context)) {
            return;
        }
        record.autoCity = '';
        record.autoPostcode = '';

        var newAddress = Object.assign({}, current, { city: '' });
        if (section === 'billing') {
            context.dispatch.setBillingAddress(newAddress);
        } else {
            context.dispatch.setShippingAddress(newAddress);
            if (context.sameAddress) {
                var bill = context.customer.billingAddress || {};
                // Apenas apagar o que foi automaticamente preenchido.
                if (String(bill.city || '').trim() === String(current.city || '').trim()) {
                    context.dispatch.setBillingAddress(
                        Object.assign({}, bill, { city: '' })
                    );
                }
            }
        }
    }

    function classicField(section, fieldName) {
        return document.getElementById(section + '_' + fieldName);
    }

    function classicApply(section, code, locality) {
        var city = classicField(section, 'city');
        var postcode = classicField(section, 'postcode');
        var country = classicField(section, 'country');
        if (
            !city || !postcode || !country || country.value !== 'PT'
            || normalize(postcode.value) !== code
        ) {
            return;
        }

        var record = state[section];
        var current = String(city.value || '').trim();
        if (current && current !== record.autoCity) {
            return;
        }

        if (current === locality) {
            return;
        }

        city.value = locality;
        record.autoCity = locality;
        record.autoPostcode = code;
        city.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function applyResult(section, code, result, isBlocks) {
        if (!result || result.status !== 'found' || typeof result.localidade !== 'string') {
            return; // Sem correspondência: o cliente pode preencher manualmente.
        }

        var city = result.localidade.trim();
        if (!city || city.length > 180) {
            return;
        }

        if (isBlocks) {
            blocksApply(section, code, city);
        } else {
            classicApply(section, code, city);
        }
    }

    function lookup(section, code, isBlocks) {
        if (!config.ajaxUrl || !config.nonce) {
            return;
        }

        var record = state[section];
        var request = ++record.request;
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        record.controller = controller;

        var post = new URLSearchParams({
            action: 'cvl_postcode_locality',
            nonce: config.nonce,
            country: 'PT',
            postcode: code
        });

        var options = {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: post.toString()
        };
        if (controller) {
            options.signal = controller.signal;
        }

        fetch(config.ajaxUrl, options)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Locality lookup unavailable');
                }
                return response.json();
            })
            .then(function (payload) {
                if (record.request !== request || record.postcode !== code) {
                    return;
                }

                var result = payload && payload.success ? payload.data : null;
                if (result && result.status === 'found') {
                    known[code] = result;
                }
                applyResult(section, code, result, isBlocks);
            })
            .catch(function () {
                // Falha na consulta nunca impede checkout nem lança erro visual.
            })
            .finally(function () {
                if (record.request === request) {
                    record.controller = null;
                }
            });
    }

    function schedule(section, postcode, isBlocks, cityValue, rawAddress) {
        var record = state[section];
        var code = normalize(postcode);
        if (record.postcode === code) {
            return;
        }

        cancel(section);
        record.postcode = code;

        if (isBlocks) {
            if (record.autoPostcode && record.autoPostcode !== code) {
                var wasAutoFilled = String(cityValue || '').trim() === record.autoCity;
                blocksClearOldCity(section, rawAddress, record);
                if (wasAutoFilled) {
                    cityValue = '';
                }
            }
        } else if (record.autoPostcode && record.autoPostcode !== code) {
            var city = classicField(section, 'city');
            if (city && city.value === record.autoCity) {
                city.value = '';
                cityValue = '';
                city.dispatchEvent(new Event('change', { bubbles: true }));
            }
            record.autoCity = '';
            record.autoPostcode = '';
        }

        if (!code) {
            return;
        }

        // Uma localidade introduzida manualmente nunca desencadeia substituição.
        if (String(cityValue || '').trim() && String(cityValue || '').trim() !== record.autoCity) {
            return;
        }

        if (known[code]) {
            applyResult(section, code, known[code], isBlocks);
            return;
        }

        record.timer = setTimeout(function () {
            record.timer = null;
            if (record.postcode === code) {
                lookup(section, code, isBlocks);
            }
        }, 350);
    }

    function checkBlocks() {
        var context = getBlocksData();
        if (!context) {
            return;
        }

        sections.forEach(function (section) {
            if (!hasIndependentAddress(section, context)) {
                cancel(section);
                state[section].postcode = '';
                return;
            }
            var address = currentAddress(section, context);
            if (!address || address.country !== 'PT') {
                if (state[section].postcode) {
                    cancel(section);
                    state[section].postcode = '';
                }
                if (state[section].autoCity) {
                    blocksClearOldCity(section, address, state[section]);
                }
                return;
            }
            schedule(section, address.postcode, true, address.city, address);
        });
    }

    function classicOnChange(event) {
        var el = event.target;
        if (!el || !el.id) {
            return;
        }

        sections.forEach(function (section) {
            if (el.id !== section + '_postcode' && el.id !== section + '_country') {
                return;
            }

            var postcode = classicField(section, 'postcode');
            var country = classicField(section, 'country');
            var city = classicField(section, 'city');
            if (!postcode || !country || !city) {
                return;
            }

            if (country.value !== 'PT') {
                cancel(section);
                state[section].postcode = '';
                var record = state[section];
                if (record.autoCity && city.value === record.autoCity) {
                    city.value = '';
                    city.dispatchEvent(new Event('change', { bubbles: true }));
                }
                record.autoCity = '';
                record.autoPostcode = '';
                return;
            }
            schedule(section, postcode.value, false, city.value, null);
        });
    }

    function init() {
        if (!blocksInitialized && document.querySelector('.wc-block-checkout, .wp-block-woocommerce-checkout')) {
            if (window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function') {
                blocksInitialized = true;
                window.wp.data.subscribe(checkBlocks);
                checkBlocks();
            }
        }

        if (!classicInitialized && document.querySelector('form.checkout.woocommerce-checkout')) {
            classicInitialized = true;
            document.addEventListener('input', classicOnChange, true);
            document.addEventListener('change', classicOnChange, true);
            sections.forEach(function (section) {
                var postcode = classicField(section, 'postcode');
                if (postcode) {
                    classicOnChange({ target: postcode });
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
