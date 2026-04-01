<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

test('webhook rejects request without signature', function () {
    Config::set('services.langdock.secret', 'test-secret');

    $response = $this->postJson('/api/webhooks/langdock', [
        'user_id' => 1,
        'projekt_id' => fake()->uuid(),
        'eingabe' => 'test',
    ]);

    $response->assertStatus(403);
});

test('webhook rejects request with invalid signature', function () {
    Config::set('services.langdock.secret', 'test-secret');

    $timestamp = (string) time();
    $body = json_encode(['user_id' => 1, 'projekt_id' => fake()->uuid(), 'eingabe' => 'test']);

    $response = $this->postJson('/api/webhooks/langdock', json_decode($body, true), [
        'X-Langdock-Signature' => 'invalid-signature',
        'X-Langdock-Timestamp' => $timestamp,
    ]);

    $response->assertStatus(403);
});

test('webhook rejects request without timestamp', function () {
    Config::set('services.langdock.secret', 'test-secret');

    $body = json_encode(['user_id' => 1, 'projekt_id' => fake()->uuid(), 'eingabe' => 'test']);
    $signature = hash_hmac('sha256', $body, 'test-secret');

    $response = $this->postJson('/api/webhooks/langdock', json_decode($body, true), [
        'X-Langdock-Signature' => $signature,
    ]);

    $response->assertStatus(403);
});

test('webhook rejects request with expired timestamp', function () {
    Config::set('services.langdock.secret', 'test-secret');

    $timestamp = (string) (time() - 600); // 10 minutes ago
    $body = json_encode(['user_id' => 1, 'projekt_id' => fake()->uuid(), 'eingabe' => 'test']);
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

    $response = $this->call('POST', '/api/webhooks/langdock', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LANGDOCK_SIGNATURE' => $signature,
        'HTTP_X_LANGDOCK_TIMESTAMP' => $timestamp,
    ], $body);

    $response->assertStatus(403);
});

test('webhook accepts request with valid signature and timestamp', function () {
    Queue::fake();
    Config::set('services.langdock.secret', 'test-secret');

    $user = \App\Models\User::factory()->withoutTwoFactor()->create();
    $projekt = \App\Models\Recherche\Projekt::factory()->create(['user_id' => $user->id]);

    $timestamp = (string) time();
    $payload = [
        'user_id' => $user->id,
        'projekt_id' => $projekt->id,
        'eingabe' => 'Testfrage',
    ];
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

    $response = $this->call('POST', '/api/webhooks/langdock', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LANGDOCK_SIGNATURE' => $signature,
        'HTTP_X_LANGDOCK_TIMESTAMP' => $timestamp,
    ], $body);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'queued']);
});
