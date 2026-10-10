/**
 * Checkout Chave Vertical — códigos postais PT, faturação e entrega.
 *
 * Formata 1234567 / 1234 567 / 1234-567 como 1234-567.
 * Não assume que um código postal existente corresponde à morada.
 * Outros países continuam sob as regras do WooCommerce.
 */
(function ($) {
    'use strict';

    function getField(section, suffix) {
        return $('form.checkout.woocommerce-checkout #' + section + '_' + suffix);
    }

    function isPortuguese(section) {
        return String(getField(section, 'country').val() || '').toUpperCase() === 'PT';
    }

    function normalizePortuguese(value) {
        var trimmed = String(value || '').trim();
        var digits = trimmed.replace(/[\s-]+/g, '');
        if (/^[0-9]{7}$/.test(digits)) {
            return digits.slice(0, 4) + '-' + digits.slice(4);
        }
        return trimmed;
    }

    function isValidPortuguese(value) {
        return /^[0-9]{4}-[0-9]{3}$/.test(String(value || ''));
    }

    function renderFeedback(section) {
        var $field = getField(section, 'postcode');
        var $row = $('#' + section + '_postcode_field');
        if (!$row.length || !$field.length) {
            return;
        }

        var pt = isPortuguese(section);
        $row.find('.cvl-postcode-help').toggle(pt);
        $field.attr('inputmode', pt ? 'numeric' : 'text');
        if (pt) {
            $field.attr('placeholder', '1234-567');
        } else if ($field.attr('placeholder') === '1234-567') {
            $field.removeAttr('placeholder');
        }

        var $hint = $row.find('.cvl-postcode-error');
        if (!$hint.length) {
            $hint = $('<span class="cvl-postcode-error" role="status" aria-live="polite"></span>');
            $row.append($hint);
        }

        var visible = $row.is(':visible');
        var value = String($field.val() || '').trim();
        var touched = $field.attr('data-cvl-postcode-touched') === 'yes';
        var compact = value.replace(/[\s-]+/g, '');
        var complete = compact.length >= 7;
        var malformed = pt && visible && value && (touched || complete)
            && !isValidPortuguese(normalizePortuguese(value));

        // Campo vazio ou país estrangeiro: WooCommerce mantém a sua validação.
        $row.toggleClass('cvl-postcode-invalid', !!malformed);
        $field.attr('aria-invalid', malformed ? 'true' : 'false');
        $hint.text(malformed
            ? 'Código postal português inválido. Utilize sete algarismos (ex.: 1000-001).'
            : '');
    }

    function formatOnChange(section) {
        var $field = getField(section, 'postcode');
        if (!$field.length || !isPortuguese(section)) {
            return;
        }

        var value = String($field.val() || '');
        var formatted = normalizePortuguese(value);
        if (value !== formatted) {
            $field.val(formatted).trigger('change');
        }
    }

    function refreshPostcodes() {
        ['billing', 'shipping'].forEach(renderFeedback);
    }

    $(function () {
        refreshPostcodes();

        // O WooCommerce atualiza partes do checkout durante cálculos de portes.
        $(document.body).on('updated_checkout country_to_state_changed', refreshPostcodes);
        $(document).on('change', '#billing_country, #shipping_country, #ship-to-different-address-checkbox', refreshPostcodes);

        $(document).on('input', '#billing_postcode, #shipping_postcode', function () {
            var section = this.id.indexOf('shipping_') === 0 ? 'shipping' : 'billing';
            renderFeedback(section);
        });

        $(document).on('blur change', '#billing_postcode, #shipping_postcode', function () {
            var section = this.id.indexOf('shipping_') === 0 ? 'shipping' : 'billing';
            $(this).attr('data-cvl-postcode-touched', 'yes');
            formatOnChange(section);
            renderFeedback(section);
        });
    });
})(jQuery);
