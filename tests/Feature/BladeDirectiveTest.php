<?php

use Illuminate\Support\Facades\Blade;

it('renders the hub snippet tag', function () {
    expect(Blade::render('@boraindicarScript'))
        ->toBe('<script src="https://hub.test/js/boraindicar.js" data-key="pk_test" data-domain=".exemplo.test" defer></script>');
});

it('omits data-domain without a cookie domain', function () {
    config(['boraindicar.cookie.domain' => null]);

    expect(Blade::render('@boraindicarScript'))->toBe('<script src="https://hub.test/js/boraindicar.js" data-key="pk_test" defer></script>');
});

it('renders nothing without a public key', function () {
    config(['boraindicar.key' => null]);

    expect(Blade::render('@boraindicarScript'))->toBe('');
});
