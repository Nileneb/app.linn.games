<?php

use App\Services\UnpaywallService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Config::set('mail.from.address', 'test@test.com');
    Http::preventStrayRequests();
});

test('resolveOaUrl returns PDF URL when found', function () {
    Http::fake([
        'https://api.unpaywall.org/v2/*' => Http::response([
            'best_oa_location' => [
                'url_for_pdf' => 'https://example.com/paper.pdf',
            ],
        ]),
    ]);

    $service = app(UnpaywallService::class);
    $url = $service->resolveOaUrl('10.1234/test.doi');

    expect($url)->toBe('https://example.com/paper.pdf');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.unpaywall.org/v2/10.1234')
            && $request['email'] === 'test@test.com';
    });
});

test('resolveOaUrl returns null when no URL found', function () {
    Http::fake([
        'https://api.unpaywall.org/v2/*' => Http::response([
            'best_oa_location' => null,
        ]),
    ]);

    $service = app(UnpaywallService::class);
    $url = $service->resolveOaUrl('10.1234/test.doi');

    expect($url)->toBeNull();
});

test('resolveOaUrl handles API failures gracefully', function () {
    Log::spy();
    
    Http::fake([
        'https://api.unpaywall.org/v2/*' => Http::response([], 503),
    ]);

    $service = app(UnpaywallService::class);
    $url = $service->resolveOaUrl('10.1234/test.doi');

    expect($url)->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(function ($message) {
            return $message === 'Unpaywall API request failed';
        });
});

test('resolveOaUrl handles network errors', function () {
    Log::spy();
    
    Http::fake([
        'https://api.unpaywall.org/v2/*' => fn () => throw new \Exception('Network error'),
    ]);

    $service = app(UnpaywallService::class);
    $url = $service->resolveOaUrl('10.1234/test.doi');

    expect($url)->toBeNull();

    Log::shouldHaveReceived('error')
        ->withArgs(function ($message) {
            return $message === 'Unpaywall API error';
        });
});

test('resolveOaUrl encodes DOI properly', function () {
    Http::fake([
        'https://api.unpaywall.org/v2/*' => Http::response([
            'best_oa_location' => ['url_for_pdf' => 'https://example.com/paper.pdf'],
        ]),
    ]);

    $service = app(UnpaywallService::class);
    $service->resolveOaUrl('10.1234/test/with/slashes');

    Http::assertSent(function ($request) {
        // rawurlencode should convert slashes to %2F
        return str_contains($request->url(), '10.1234%2Ftest%2Fwith%2Fslashes');
    });
});
