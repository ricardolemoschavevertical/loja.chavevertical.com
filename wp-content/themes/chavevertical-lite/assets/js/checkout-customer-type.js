/**
 * Campos fiscais do checkout clássico WooCommerce.
 * A escolha é progressiva: sem JS, o servidor continua a validar NIF/NIPC.
 */
(function ($) {
    'use strict';

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
    }

    $(function () {
        syncCheckoutCustomerFields();
        $(document).on('change', 'input[name="billing_customer_type"]', syncCheckoutCustomerFields);
        $(document.body).on('updated_checkout country_to_state_changed', syncCheckoutCustomerFields);
    });
})(jQuery);
