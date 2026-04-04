<?php

use App\Services\LangdockContextInjector;

test('inject validates projekt_id as valid uuid format', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    $validUuid = '550e8400-e29b-41d4-a716-446655440000';
    $result = $injector->inject($messages, ['projekt_id' => $validUuid]);

    expect($result)->toHaveCount(2);
    expect($result[0]['role'])->toBe('system');
    expect($result[0]['parts'][0]['text'])->toContain('app.current_projekt_id');
    expect($result[0]['parts'][0]['text'])->toContain($validUuid);
});

test('inject throws InvalidArgumentException for invalid projekt_id', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    try {
        $injector->inject($messages, ['projekt_id' => 'not-a-valid-uuid']);
        expect(true)->toBe(false); // Should have thrown
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Invalid projekt_id format');
        expect($e->getMessage())->toContain('not-a-valid-uuid');
    }
});

test('inject throws InvalidArgumentException for invalid workspace_id', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    try {
        $injector->inject($messages, ['workspace_id' => 'not-a-valid-uuid']);
        expect(true)->toBe(false);
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Invalid workspace_id format');
    }
});

test('inject throws InvalidArgumentException for invalid user_id format', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    // Should fail: not numeric, not UUID, not a simple string like "abc"
    try {
        $injector->inject($messages, ['user_id' => 'invalid-format-with-dashes']);
        expect(true)->toBe(false);
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Invalid user_id format');
    }
});

test('inject accepts numeric user_id (like User auto-increment ID)', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    // Realistic: both projekt_id (UUID) and user_id (numeric, from User model)
    $validUuid = '550e8400-e29b-41d4-a716-446655440000';
    $result = $injector->inject($messages, [
        'projekt_id' => $validUuid,
        'user_id' => 42,  // Numeric ID from User model
    ]);

    expect($result)->toHaveCount(2);
    expect($result[0]['role'])->toBe('system');
    expect($result[0]['parts'][0]['text'])->toContain('app.current_projekt_id');
    expect($result[0]['parts'][0]['text'])->toContain($validUuid);
    expect($result[0]['parts'][0]['text'])->toContain('"user_id":42');
});

test('inject accepts uuid user_id as well', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    // UUID format
    $validUuid = '550e8400-e29b-41d4-a716-446655440000';
    $result = $injector->inject($messages, ['user_id' => $validUuid]);

    expect($result)->toHaveCount(2);
    expect($result[0]['role'])->toBe('system');
});

test('inject accepts null values as optional context fields', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    $result = $injector->inject($messages, ['projekt_id' => null, 'workspace_id' => null, 'user_id' => null]);

    // No context message added when all fields are null
    expect($result)->toBe($messages);
});

test('inject rejects sql injection attempts in projekt_id', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    $malicious = "'; DROP TABLE users; --";

    try {
        $injector->inject($messages, ['projekt_id' => $malicious]);
        expect(true)->toBe(false);
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Invalid projekt_id format');
        expect($e->getMessage())->toContain($malicious);
    }
});

test('inject works with multiple valid uuids', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    $projektId = '550e8400-e29b-41d4-a716-446655440000';
    $userId = '660e8400-e29b-41d4-a716-446655440000';

    $result = $injector->inject($messages, ['projekt_id' => $projektId, 'user_id' => $userId]);

    expect($result)->toHaveCount(2);
    expect($result[0]['parts'][0]['text'])->toContain($projektId);
    expect($result[0]['parts'][0]['text'])->toContain($userId);
});

test('inject correctly formats sql set statement with valid uuid', function () {
    $injector = new LangdockContextInjector();
    $messages = [['id' => 'msg1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello']]]];

    $validUuid = '550e8400-e29b-41d4-a716-446655440000';
    $result = $injector->inject($messages, ['projekt_id' => $validUuid]);

    // Check that the SQL statement is correctly formatted
    $text = $result[0]['parts'][0]['text'];
    expect($text)->toContain("SET LOCAL app.current_projekt_id = '{$validUuid}';");
});
