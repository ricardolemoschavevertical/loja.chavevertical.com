/**
 * Hermetic WooCommerce Blocks postcode-to-city integration tests.
 * No internet, no browser, no orders, no WordPress writes.
 */
'use strict';

const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const filename = 'wp-content/themes/chavevertical-lite/assets/js/checkout-postcode-locality.js';
const code = fs.readFileSync(filename, 'utf8');

function setup({ same = true, needsShipping = true, shipping, billing, responses = {} }) {
    let customer = {
        shippingAddress: {
            first_name: 'Maria', last_name: 'Teste', address_1: 'Rua 1',
            country: 'PT', postcode: '1000-098', city: '', ...shipping
        },
        billingAddress: {
            first_name: 'Maria', last_name: 'Teste', address_1: 'Rua 1',
            country: 'PT', postcode: '1000-098', city: '', ...billing
        }
    };

    let selectedSame = same;
    const subscribers = [];
    const queuedTimers = [];
    const calls = [];
    const changes = [];
    let fetchError = false;

    const notify = () => subscribers.forEach(callback => callback());
    const actions = {
        setShippingAddress: next => {
            customer.shippingAddress = { ...next };
            changes.push({ section: 'shipping', city: next.city, postcode: next.postcode });
            notify();
        },
        setBillingAddress: next => {
            customer.billingAddress = { ...next };
            changes.push({ section: 'billing', city: next.city, postcode: next.postcode });
            notify();
        }
    };

    const cartSelectors = {
        getCustomerData: () => customer,
        getNeedsShipping: () => needsShipping
    };

    const fakeWindow = {
        CVLPostcodeLocality: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'mock' },
        wp: {
            data: {
                select: name => name === 'wc/store/cart'
                    ? cartSelectors
                    : { getUseShippingAsBilling: () => selectedSame },
                dispatch: name => name === 'wc/store/cart' ? actions : {},
                subscribe: callback => subscribers.push(callback)
            }
        }
    };

    const mockFetch = (_url, opts) => {
        const args = new URLSearchParams(opts.body);
        const cp = args.get('postcode');
        calls.push(cp);
        if (fetchError) {
            return Promise.reject(new Error('Simulated network outage'));
        }
        return Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                success: true,
                data: responses[cp] || { status: 'not_found' }
            })
        });
    };

    const context = {
        window: fakeWindow,
        document: {
            readyState: 'complete',
            querySelector: selector => selector.includes('.wc-block-checkout') ? {} : null
        },
        fetch: mockFetch,
        URLSearchParams,
        AbortController,
        setTimeout: callback => { queuedTimers.push(callback); return queuedTimers.length; },
        clearTimeout: () => {},
        Event,
        console
    };

    vm.runInNewContext(code, context, { filename, timeout: 1000 });

    async function flush() {
        for (let pass = 0; pass < 10; pass++) {
            while (queuedTimers.length) {
                const callback = queuedTimers.shift();
                callback();
            }
            for (let i = 0; i < 8; i++) await Promise.resolve();
        }
    }

    return {
        flush,
        get customer() { return customer; },
        calls,
        changes,
        change(section, update) {
            const key = section + 'Address';
            customer[key] = { ...customer[key], ...update };
            notify();
        },
        setSame(value) { selectedSame = value; notify(); },
        setFetchError(value) { fetchError = value; }
    };
}

(async () => {
    let env = setup({
        responses: {
            '1000-098': { status: 'found', localidade: 'Lisboa' },
            '6160-152': { status: 'found', localidade: 'Isna' }
        }
    });
    await env.flush();
    assert.equal(env.customer.shippingAddress.city, 'Lisboa', 'Shipping city autofilled through cart store');
    assert.equal(env.customer.billingAddress.city, 'Lisboa', 'Use shipping as billing synchronizes city');
    assert.equal(env.customer.shippingAddress.postcode, '1000-098', 'Postcode unchanged');
    assert.equal(env.customer.shippingAddress.first_name, 'Maria', 'Original customer data preserved');
    assert.deepStrictEqual(env.calls, ['1000-098'], 'Only one lookup when billing and shipping are same');
    console.log('PASS: Woo Blocks shipping+same billing city autofilled safely');

    env.change('shipping', { postcode: '6160-152' });
    await env.flush();
    assert.equal(env.customer.shippingAddress.city, 'Isna', 'Changed postcode updates old auto city');
    assert.equal(env.customer.billingAddress.city, 'Isna', 'Changed shipping postcode syncs billing');
    assert.deepStrictEqual(env.calls, ['1000-098', '6160-152'], 'Only one lookup per distinct CP7');
    console.log('PASS: Postcode change clears and replaces previous automatic city');

    const beforeManual = env.calls.length;
    env.change('shipping', { city: 'Cidade introduzida pelo cliente', postcode: '2500-663' });
    await env.flush();
    assert.equal(env.customer.shippingAddress.city, 'Cidade introduzida pelo cliente', 'Manual city not replaced');
    assert.equal(env.calls.length, beforeManual, 'Manual locality does not call external service');
    console.log('PASS: Manually entered locality respected');

    const separate = setup({
        same: false,
        shipping: { postcode: '6160-152', city: '' },
        billing: { postcode: '1000-098', city: '' },
        responses: {
            '1000-098': { status: 'found', localidade: 'Lisboa' },
            '6160-152': { status: 'found', localidade: 'Isna' }
        }
    });
    await separate.flush();
    assert.equal(separate.customer.shippingAddress.city, 'Isna');
    assert.equal(separate.customer.billingAddress.city, 'Lisboa');
    assert.equal(separate.calls.length, 2);
    console.log('PASS: Billing and shipping city lookups independent');

    const foreign = setup({
        same: false,
        shipping: { country: 'ES', postcode: '28013', city: '' },
        billing: { country: 'FR', postcode: '75001', city: '' }
    });
    await foreign.flush();
    assert.deepStrictEqual(foreign.calls, []);
    assert.equal(foreign.customer.shippingAddress.city, '');
    console.log('PASS: No Portuguese validation for foreign countries');

    const unavailable = setup({
        same: false,
        shipping: { postcode: '1000-098', city: '' },
        billing: { postcode: '', city: '' }
    });
    unavailable.setFetchError(true);
    await unavailable.flush();
    assert.equal(unavailable.customer.shippingAddress.city, '', 'Service failure allows manual city');
    assert.equal(unavailable.changes.length, 0, 'Service failure never changes checkout');
    console.log('PASS: Service outage never blocks or changes checkout');

    console.log('checkout_postcode_blocks_unit_status=ok');
})().catch(err => {
    console.error(err.stack || err.message);
    process.exitCode = 1;
});
