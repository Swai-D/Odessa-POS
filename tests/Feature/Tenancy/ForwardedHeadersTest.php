<?php

it('ignores a forwarded Host header, so a visitor cannot pick a shop with it', function (): void {
    config(['app.domain' => 'example.test']);
    createTenant('spoof');

    $page = $this->get('http://example.test/login', ['X-Forwarded-Host' => 'spoof.example.test'])->assertOk()->getContent();

    expect($page)->not->toContain('Spoof');
});

it('still honours the forwarded scheme behind the proxy, so assets use HTTPS', function (): void {
    config(['app.domain' => 'example.test']);

    $this->get('http://example.test/login', ['X-Forwarded-Proto' => 'https'])->assertOk()->assertSee('https://example.test/login', false);
});
