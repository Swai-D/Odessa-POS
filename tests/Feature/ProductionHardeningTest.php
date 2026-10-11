<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('web')->get('/_test/abort/{code}', fn (int $code) => abort($code));
});

it('shows a branded error page for each common status, without signing in', function (int $code, string $title): void {
    $page = $this->get('/_test/abort/'.$code)->assertStatus($code)->getContent();

    expect($page)->toContain('Odessa POS')->toContain($title)->not->toContain('Illuminate');
})->with([
    [403, 'Access denied'],
    [404, 'Page not found'],
    [419, 'Page expired'],
    [429, 'Too many attempts'],
    [500, 'Something went wrong'],
    [503, 'Back in a moment'],
]);

it('shows the error pages in Swahili when the visitor chose Swahili', function (): void {
    $this->post('/language/sw')->assertRedirect();

    $this->get('/_test/abort/404')->assertNotFound()->assertSee('Ukurasa haupatikani');
});

it('renders the error pages without the app layout, so they work when the database is down', function (string $view): void {
    $html = view($view)->render();

    expect($html)->toContain('Odessa POS')->not->toContain('sidebar');
})->with(['errors.403', 'errors.404', 'errors.419', 'errors.429', 'errors.500', 'errors.503']);

it('answers the health check with the database status and no session', function (): void {
    $response = $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok', 'database' => 'up']);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('reports a degraded status when the database cannot be reached', function (): void {
    config([
        'database.connections.unreachable' => ['driver' => 'sqlite', 'database' => '/nonexistent-dir/odessa.sqlite', 'prefix' => '', 'foreign_key_constraints' => false],
        'database.default' => 'unreachable',
    ]);
    DB::purge();

    $this->get('/health')->assertStatus(503)->assertExactJson(['status' => 'degraded', 'database' => 'down']);
});

it('sends safe browser headers on pages', function (): void {
    $response = $this->get('/login')->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('sends HSTS only over HTTPS in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $this->get('https://localhost/login', ['X-Forwarded-Proto' => 'https'])->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});
