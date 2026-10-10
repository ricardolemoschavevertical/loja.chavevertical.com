/**
 * Campos fiscais do checkout clássico WooCommerce.
 * O PHP valida sempre: este script apenas melhora a experiência visual.
 *
 * Portugal: 9 dígitos + módulo 11; Particular 1-4; NIPC 5,6,8,9;
 * entidades com NIF 7 registado na AT são aceites em Empresa.
 * Estrangeiros: sem falsa validação pelo algoritmo português.
 */
(function ($) {
    'use strict';

    function fiscalKind(raw) {
        var value = String(raw || '').toUpperCase().replace(/[\s.\-]/g, '');
        if (value.slice(0, 2) === 'PT') {
            value = value.slice(2);
        }
        if (!/^[1-9][0-9]{8}$/.test(value)) {
            return '';
        }

        var sum = 0;
        for (var i = 0; i < 8; i++) {
            sum += Number(value.charAt(i)) * (9 - i);
        }
        var expected = 11 - (sum % 11);
        if (expected >= 10) {
            expected = 0;
        }
        if (expected !== Number(value.charAt(8))) {
            return '';
        }

        if (/^[1-4]/.test(value)) {
            return 'singular';
        }
        if (/^[5689]/.test(value)) {
            return 'nipc';
        }
        if (value.charAt(0) === '7') {
            return 'entidade_at';
        }
        return '';
    }

    function checkIdentifier(value, isCompany, country) {
        value = String(value || '').trim();
        if (!value) {
            return isCompany ? 'Indique o NIPC da empresa.' : 'Indique o NIF.';
        }

        var isPortuguese = !country || country === 'PT' || /^PT[\s.\-]*[0-9]/i.test(value);
        if (isPortuguese) {
            var kind = fiscalKind(value);
            if (!kind) {
                return 'Número português inválido: verifique os 9 dígitos e o dígito de controlo.';
            }
            if (!isCompany && kind !== 'singular') {
                return 'O NIF de um particular deve começar por 1, 2, 3 ou 4.';
            }
            if (isCompany && kind === 'singular') {
                return 'É um NIF pessoal. Se é empresário em nome individual, escolha Particular.';
            }
        } else if (value.length > 32) {
            return 'O número fiscal estrangeiro não pode ultrapassar 32 caracteres.';
        }
        return '';
    }

    function updateFeedback() {
        var $form = $('form.checkout.woocommerce-checkout');
        if (!$form.length) {
            return;
        }

        var country = String($form.find('#billing_country').val() || 'PT').toUpperCase();
        var isCompany = $form.find('input[name="billing_customer_type"]:checked').val() === 'empresa';

        ['billing_nif', 'billing_nipc'].forEach(function (id) {
            var $input = $form.find('#' + id);
            var $row = $form.find('#' + id + '_field');
            if (!$input.length || !$row.length) {
                return;
            }

            $input.attr('inputmode', country === 'PT' ? 'numeric' : 'text');
            var $feedback = $row.find('.cvl-fiscal-feedback');
            if (!$feedback.length) {
                $feedback = $('<span class="cvl-fiscal-feedback" role="status" aria-live="polite"></span>');
                $row.find('.woocommerce-input-wrapper').append($feedback);
            }

            var active = id === (isCompany ? 'billing_nipc' : 'billing_nif');
            var value = String($input.val() || '');
            var touched = $input.attr('data-cvl-touched') === 'yes';
            var complete = country === 'PT' && value.replace(/[\s.\-]/g, '').replace(/^PT/i, '').length >= 9;

            var error = active && (touched || complete)
                ? checkIdentifier(value, isCompany, country)
                : '';

            $row.toggleClass('cvl-fiscal-invalid', !!error);
            $input.attr('aria-invalid', error ? 'true' : 'false');
            $feedback.text(error);
        });
    }

    function syncCheckoutCustomerFields() {
        var $form = $('form.checkout.woocommerce-checkout');
        if (!$form.length) {
            return;
        }

        var $choice = $form.find('input[name="billing_customer_type"]:checked');
        if (!$choice.length) {
            return;
        }

        var isCompany = $choice.val() === 'empresa';
        var rows = [
            { selector: '#billing_company_field', active: isCompany },
            { selector: '#billing_nipc_field', active: isCompany },
            { selector: '#billing_nif_field', active: !isCompany }
        ];

        rows.forEach(function (field) {
            var $row = $form.find(field.selector);
            var $input = $row.find('input, select, textarea').first();
            if (!$row.length || !$input.length) {
                return;
            }

            $row.toggleClass('cvl-fiscal-hidden', !field.active)
                .toggleClass('cvl-fiscal-required', field.active)
                .toggleClass('validate-required', field.active)
                .attr('aria-hidden', field.active ? 'false' : 'true');

            $input.prop('disabled', !field.active)
                .prop('required', field.active)
                .attr('aria-required', field.active ? 'true' : 'false');

            if (!field.active) {
                $row.removeClass('woocommerce-invalid woocommerce-invalid-required-field');
            }
        });

        $form.addClass('cvl-customer-fields-ready');
        updateFeedback();
    }

    $(function () {
        syncCheckoutCustomerFields();

        $(document).on('change', 'input[name="billing_customer_type"]', syncCheckoutCustomerFields);
        $(document.body).on('updated_checkout country_to_state_changed', syncCheckoutCustomerFields);
        $(document).on('change', '#billing_country', syncCheckoutCustomerFields);

        $(document).on('blur', '#billing_nif, #billing_nipc', function () {
            $(this).attr('data-cvl-touched', 'yes');
            updateFeedback();
        });
        $(document).on('input', '#billing_nif, #billing_nipc', updateFeedback);
    });
})(jQuery);
