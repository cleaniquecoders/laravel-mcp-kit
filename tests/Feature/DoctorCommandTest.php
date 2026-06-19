<?php

use Laravel\Passport\Passport;

// Passport's key path is global static state. The OAuth-keys test repoints it,
// so capture and restore it around every test here — otherwise the bogus path
// leaks into the OAuth suite under random ordering.
beforeEach(function () {
    $this->originalKeyPath = Passport::$keyPath;
});

afterEach(function () {
    Passport::$keyPath = $this->originalKeyPath;
});

it('passes the doctor checks in a token-only setup', function () {
    $this->artisan('mcp-kit:doctor')
        ->assertSuccessful();
});

it('reports a failure when OAuth is on but Passport keys are missing', function () {
    config()->set('mcp-kit.web.oauth.enabled', true);

    // Point Passport at an empty key directory so the key check fails
    // deterministically regardless of the test machine.
    Passport::loadKeysFrom(sys_get_temp_dir().'/mcp-kit-no-keys-'.uniqid());

    $this->artisan('mcp-kit:doctor')
        ->assertFailed();
});
