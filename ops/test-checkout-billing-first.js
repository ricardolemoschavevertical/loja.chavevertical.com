/**
 * Hermetic tests of billing-first WooCommerce Blocks checkout logic.
 * No live cart, order, payment, HTTP requests, WordPress writes, or browser.
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const filename = 'wp-content/themes/chavevertical-lite/assets/js/checkout-billing-first.js';
const code = fs.readFileSync(filename, 'utf8');

function setup({
    checked = true,
    needsShipping = true,
    collection = false,
    useShippingAsBilling = true,
    canChangeDirection = true
} = {}) {
    const classes = new Set();
    const callbacks = [];
    const listeners = {};
    const queue = [];
    const nativeCheckbox = { id: 'order-cvl-fiscal-use-billing-for-shipping', checked };
    let notifications = 0;
    let shippingDispatches = 0;
    let directionChanges = 0;

    let customer = {
        billingAddress: {
            first_name: 'Ana', last_name: 'Silva', company: '',
            address_1: 'Rua Comercial 4', address_2: '',
            country: 'PT', state: 'PT-01', city: 'Lisboa',
            postcode: '1000-098', phone: '911000000'
        },
        shippingAddress: {
            first_name: '', last_name: '', company: '',
            address_1: '', address_2: '',
            country: 'PT', state: '', city: '',
            postcode: '', phone: ''
        }
    };

    const notify = () => {
        notifications++;
        if (notifications > 60) throw new Error('Loop in WooCommerce wp.data notifications');
        callbacks.forEach(cb => cb());
    };

    const checkout = {
        getUseShippingAsBilling: () => useShippingAsBilling,
        prefersCollection: () => collection
    };
    const checkoutDispatch = canChangeDirection
        ? {
            __internalSetUseShippingAsBilling: enabled => {
                if (useShippingAsBilling !== enabled) {
                    useShippingAsBilling = enabled;
                    directionChanges++;
                    notify();
                }
            }
        } : {};
    const cart = {
        getCustomerData: () => customer,
        getNeedsShipping: () => needsShipping
    };
    const cartDispatch = {
        setShippingAddress: addr => {
            shippingDispatches++;
            customer.shippingAddress = {...addr};
            notify();
        }
    };
    const dom = {
        readyState: 'complete',
        body: {classList: {
            toggle: (name, active) => {
                if(active)classes.add(name);
                else classes.delete(name);
            }
        }},
        querySelector: query => query === '.wc-block-checkout' ? {} : null,
        getElementById: id => id === nativeCheckbox.id ? nativeCheckbox : null,
        addEventListener: (name, callback) => {listeners[name] = callback}
    };
    let id = 0;
    const future = new Map();
    const schedule = (fn, _ms) => {
        const key = ++id;
        future.set(key, fn);
        queue.push(key);
        return key;
    };

    const window = {
        wp: {
            data: {
                select: name => name === 'wc/store/cart' ? cart : checkout,
                dispatch: name => name === 'wc/store/cart' ? cartDispatch : checkoutDispatch,
                subscribe: cb => {callbacks.push(cb);return () => {}}
            }
        },
        setTimeout: schedule
    };

    vm.runInNewContext(code, {document:dom, window, console, setTimeout:schedule,clearTimeout:k=>future.delete(k)}, {filename, timeout:1000});

    function flush(){
        let steps=0;
        while(queue.length){
            if(++steps>100)throw Error('Too many queued synchronize tasks');
            const key=queue.shift();
            const fn=future.get(key);
            if(fn){future.delete(key);fn();}
        }
    }

    return {
        flush,
        classes,
        customer,
        checkbox: nativeCheckbox,
        notifications: () => notifications,
        shippingDispatches: () => shippingDispatches,
        directionChanges: () => directionChanges,
        sameMode: () => useShippingAsBilling,
        toggle(checked){
            nativeCheckbox.checked=checked;
            listeners.change?.({target:nativeCheckbox});
            flush();
        },
        updateBilling(fields){
            customer.billingAddress={...customer.billingAddress,...fields};
            notify();flush();
        },
        updateShipping(fields){
            customer.shippingAddress={...customer.shippingAddress,...fields};
            notify();flush();
        }
    };
}

let env=setup();
env.flush();
assert.equal(env.sameMode(), false, 'Native reverse shipping->billing toggle disabled');
assert.equal(env.directionChanges(), 1, 'Direction changed exactly once');
assert.equal(env.customer.shippingAddress.postcode,'1000-098','Billing postcode copied to delivery');
assert.equal(env.customer.shippingAddress.city,'Lisboa','Billing city copied to delivery');
assert.equal(env.customer.shippingAddress.address_1,'Rua Comercial 4','Billing street copied to delivery');
assert.equal(env.customer.shippingAddress.first_name,'Ana','Billing name copied to delivery');
assert(env.classes.has('cvl-billing-first-active'));
assert(env.classes.has('cvl-use-billing-for-shipping'));
console.log('PASS: Billing is primary and selected same-delivery copies complete address');

const originalSyncs=env.shippingDispatches();
env.updateBilling({postcode:'6160-152',city:'Isna',address_1:'Rua Nova'});
assert.equal(env.customer.shippingAddress.postcode,'6160-152');
assert.equal(env.customer.shippingAddress.city,'Isna');
assert.equal(env.customer.shippingAddress.address_1,'Rua Nova');
assert.equal(env.shippingDispatches(),originalSyncs+1,'Exactly one copy for billing edit');
console.log('PASS: Billing updates synchronize delivery exactly once');

env.toggle(false);
assert(!env.classes.has('cvl-use-billing-for-shipping'),'Different delivery form becomes visible');
env.updateShipping({city:'Coimbra',postcode:'3000-123'});
env.updateBilling({city:'Aveiro',postcode:'3800-001'});
assert.equal(env.customer.shippingAddress.city,'Coimbra','Delivery field remains independent');
assert.equal(env.customer.shippingAddress.postcode,'3000-123','Delivery postcode remains independent');
console.log('PASS: Separate delivery address retained when option unchecked');

env.toggle(true);
assert.equal(env.customer.shippingAddress.city,'Aveiro','Re-check synchronizes billing');
assert.equal(env.customer.shippingAddress.postcode,'3800-001');
console.log('PASS: Re-selecting same-delivery copies current billing safely');

const noShipping=setup({needsShipping:false});
noShipping.flush();
assert(!noShipping.classes.has('cvl-use-billing-for-shipping'));
assert(noShipping.classes.has('cvl-delivery-unavailable'));
assert.equal(noShipping.shippingDispatches(),0);
console.log('PASS: Virtual product carts do not synchronize shipping');

const pickup=setup({collection:true});
pickup.flush();
assert(!pickup.classes.has('cvl-use-billing-for-shipping'));
assert.equal(pickup.shippingDispatches(),0);
console.log('PASS: Click-and-collect does not require delivery form');

const unsupported=setup({canChangeDirection:false});
unsupported.flush();
assert(!unsupported.classes.has('cvl-billing-first-active'),'Unknown checkout API fail-open');
assert(!unsupported.classes.has('cvl-use-billing-for-shipping'));
assert.equal(unsupported.shippingDispatches(),0);
console.log('PASS: Unsupported WooCommerce store safely retains native checkout');

console.log('checkout_billing_first_unit_status=ok');
