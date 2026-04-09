<?php

test('contact form accepts valid submission', function () {
    $response = $this->postJson(route('contact.store'), [
        'name' => 'Max Mustermann',
        'company' => 'Musterfirma GmbH',
        'email' => 'max@example.com',
        'project_type' => 'Web-App',
        'message' => 'Ich brauche eine neue Website.',
        'timeline' => 'Q3 2026',
    ]);

    $response
        ->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Anfrage erfolgreich gesendet.']);

    $this->assertDatabaseHas('contacts', [
        'name' => 'Max Mustermann',
        'company' => 'Musterfirma GmbH',
        'email' => 'max@example.com',
        'project_type' => 'Web-App',
    ]);
});

test('contact form works without optional fields', function () {
    $response = $this->postJson(route('contact.store'), [
        'name' => 'Anna Schmidt',
        'email' => 'anna@example.com',
        'project_type' => 'KI-Lösung',
        'message' => 'Projekt ohne Firma und Timeline.',
    ]);

    $response->assertStatus(200)->assertJson(['success' => true]);

    $this->assertDatabaseHas('contacts', [
        'name' => 'Anna Schmidt',
        'company' => null,
        'timeline' => null,
    ]);
});

test('contact form requires name', function () {
    $response = $this->postJson(route('contact.store'), [
        'email' => 'test@example.com',
        'project_type' => 'Game',
        'message' => 'Test message.',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});

test('contact form requires email', function () {
    $response = $this->postJson(route('contact.store'), [
        'name' => 'Test',
        'project_type' => 'Game',
        'message' => 'Test message.',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

test('contact form requires valid email', function () {
    $response = $this->postJson(route('contact.store'), [
        'name' => 'Test',
        'email' => 'not-an-email',
        'project_type' => 'Game',
        'message' => 'Test message.',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

test('contact form requires project_type', function () {
    $response = $this->postJson(route('contact.store'), [
        'name' => 'Test',
        'email' => 'test@example.com',
        'message' => 'Test message.',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['project_type']);
});

test('contact form requires message', function () {
    $response = $this->postJson(route('contact.store'), [
        'name' => 'Test',
        'email' => 'test@example.com',
        'project_type' => 'Game',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['message']);
});

test('contact form rejects message exceeding 5000 characters', function () {
    $response = $this->postJson(route('contact.store'), [
        'name' => 'Test',
        'email' => 'test@example.com',
        'project_type' => 'Game',
        'message' => str_repeat('a', 5001),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['message']);
});
