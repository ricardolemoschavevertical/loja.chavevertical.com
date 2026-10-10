/**
 * VIES oficial — faturação Empresa (WooCommerce checkout clássico).
 *
 * O utilizador pede a consulta; os dados retornados apenas completam campos
 * VAZIOS de faturação. O código nunca lê/escreve campos shipping_*.
 * Se o registo não existir ou o VIES estiver indisponível, continua manual.
 */
(function ($) {
    'use strict';

    var settings = window.CVLCheckoutVies || {};
    var activeRequest = null;
    var activeLookup = '';
    var autoFilled = {};
    var euCountries = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES',
        'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
        'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI'
    ];

    function $billing(id) {
        return $('form.checkout.woocommerce-checkout #billing_' + id);
    }

    function currentTypeIsCompany() {
        return $('form.checkout input[name="billing_customer_type"]:checked').val() === 'empresa';
    }

    function currentCountry() {
        return String($billing('country').val() || 'PT').toUpperCase();
    }

    function currentVat() {
        return String($billing('nipc').val() || '').trim();
    }

    function canLookUp(country, vat) {
        if (country === 'GB' && /^XI/i.test(vat)) {
            return true;
        }
        return euCountries.indexOf(country) !== -1;
    }

    function currentKey() {
        return currentCountry() + ':' + currentVat().toUpperCase().replace(/[\s.\-]+/g, '');
    }

    function setMessage(message, state) {
        var $message = $('#cvl-vies-message');
        $message.text(message || '');
        $message.removeClass('is-success is-warning is-loading');
        if (state) {
            $message.addClass('is-' + state);
        }
    }

    function hideProfile() {
        $('#cvl-vies-profile').prop('hidden', true);
        $('#cvl-vies-profile-name').text('');
        $('#cvl-vies-profile-address').text('');
    }

    function resetAutofilledValues() {
        Object.keys(autoFilled).forEach(function (fieldId) {
            var $field = $('#' + fieldId);
            if ($field.length && String($field.val()) === autoFilled[fieldId]) {
                $field.val('').trigger('change');
            }
        });
        autoFilled = {};
    }

    function resetResults(clearAutoFilled) {
        if (activeRequest) {
            activeRequest.abort();
            activeRequest = null;
        }
        activeLookup = '';
        setMessage('');
        hideProfile();
        if (clearAutoFilled) {
            resetAutofilledValues();
        }
        $('#cvl-vies-check').prop('disabled', false).text('Verificar no VIES');
    }

    function fillIfEmpty(id, value) {
        value = String(value || '').trim();
        var $field = $('#' + id);
        if (!value || !$field.length || String($field.val() || '').trim()) {
            return false;
        }
        $field.val(value).trigger('change');
        autoFilled[id] = value;
        return true;
    }

    function applyProfile(profile) {
        if (!profile || typeof profile !== 'object') {
            return;
        }

        // Só se alteram campos billing_*, nunca o destino de entrega.
        var used = 0;
        used += fillIfEmpty('billing_company', profile.company) ? 1 : 0;
        used += fillIfEmpty('billing_address_1', profile.street) ? 1 : 0;
        used += fillIfEmpty('billing_postcode', profile.postcode) ? 1 : 0;
        used += fillIfEmpty('billing_city', profile.city) ? 1 : 0;

        var name = String(profile.company || '').trim();
        var address = String(profile.address || '').trim();
        var $profile = $('#cvl-vies-profile');
        $('#cvl-vies-profile-name').text(name ? 'Empresa: ' + name : 'A designação da empresa não foi disponibilizada.');
        $('#cvl-vies-profile-address').text(address ? 'Morada: ' + address : 'A morada não foi disponibilizada.');
        $profile.prop('hidden', false);

        if (!name && !address) {
            setMessage('Número confirmado no VIES, mas sem dados de nome ou morada. Preencha manualmente a faturação.', 'success');
        } else if (used) {
            setMessage('Verificado no VIES. Os campos de faturação vazios foram preenchidos quando possível. Confirme os restantes dados.', 'success');
        } else {
            setMessage('Verificado no VIES. Consulte os dados abaixo; os campos de faturação já preenchidos não foram substituídos.', 'success');
        }
    }

    function syncAvailability() {
        var $button = $('#cvl-vies-check');
        if (!$button.length) {
            return;
        }

        var country = currentCountry();
        var isCompany = currentTypeIsCompany();
        var $row = $('#billing_nipc_field');
        var canUse = isCompany && canLookUp(country, currentVat());

        $row.toggleClass('cvl-vies-unsupported', !canUse);
        $button.prop('disabled', !canUse || !!activeRequest);

        // O número fiscal de outros países não se chama NIPC.
        var $label = $row.children('label').first();
        var $description = $row.find('.woocommerce-input-wrapper > .description').first();
        if (country !== 'PT' && country !== '') {
            $label.contents().filter(function () { return this.nodeType === 3; }).first().replaceWith('N.º fiscal / IVA ');
            if ($description.length) {
                $description.text('Pode consultar o VIES para os países da UE. Caso não seja possível, preencha os dados de faturação manualmente.');
            }
        } else {
            $label.contents().filter(function () { return this.nodeType === 3; }).first().replaceWith('NIPC ');
            if ($description.length) {
                $description.text('O VIES é opcional. Se não encontrar dados, preencha a faturação manualmente.');
            }
        }
    }

    function runLookup() {
        if (!currentTypeIsCompany() || !settings.ajaxUrl || !settings.nonce) {
            return;
        }

        var country = currentCountry();
        var vat = currentVat();
        if (!vat) {
            setMessage('Introduza primeiro o NIPC ou número de IVA da empresa.', 'warning');
            $billing('nipc').trigger('focus');
            return;
        }
        if (!canLookUp(country, vat)) {
            setMessage('O VIES não cobre este país. Preencha os dados de faturação manualmente.', 'warning');
            return;
        }

        if (activeRequest) {
            return;
        }

        // A chave impede aplicar uma resposta atrasada a outro contribuinte.
        var key = currentKey();
        hideProfile();
        activeLookup = key;
        setMessage('A consultar o VIES…', 'loading');
        $('#cvl-vies-check').prop('disabled', true).text('A verificar…');

        activeRequest = $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            timeout: 13000,
            data: {
                action: 'cvl_vies_billing_lookup',
                nonce: settings.nonce,
                country: country,
                vat: vat
            }
        }).done(function (response) {
            if (activeLookup !== key || currentKey() !== key || !currentTypeIsCompany()) {
                return;
            }
            var result = response && response.data;
            if (!response || !response.success || !result) {
                var reason = result && result.message ? result.message : 'Não foi possível consultar o VIES.';
                setMessage(reason + ' Preencha os dados de faturação manualmente.', 'warning');
                return;
            }

            if (result.status === 'valid') {
                applyProfile(result.profile || {});
            } else {
                hideProfile();
                setMessage(
                    String(result.message || 'Não foi possível confirmar o número.')
                    + ' A faturação pode ser preenchida manualmente.',
                    'warning'
                );
            }
        }).fail(function (_xhr, status) {
            if (status === 'abort' || activeLookup !== key || currentKey() !== key) {
                return;
            }
            setMessage('Não foi possível contactar o VIES. Preencha os dados de faturação manualmente.', 'warning');
        }).always(function () {
            if (activeLookup === key) {
                activeRequest = null;
                $('#cvl-vies-check').prop('disabled', false).text('Verificar no VIES');
                syncAvailability();
            }
        });
    }

    $(function () {
        syncAvailability();

        $(document).on('click', '#cvl-vies-check', function (event) {
            event.preventDefault();
            runLookup();
        });

        $(document).on('input', '#billing_nipc', function () {
            resetResults(true);
            syncAvailability();
        });

        $(document).on('change', '#billing_country, input[name="billing_customer_type"]', function () {
            resetResults(true);
            syncAvailability();
        });

        $(document.body).on('updated_checkout', syncAvailability);
    });
})(jQuery);
