<?php

use App\Services\ChatTriggerwordRouter;

test('it falls back when no trigger is present', function () {
    $router = new ChatTriggerwordRouter;

    $r = $router->route('Hello world');

    expect($r['config_key'])->toBe('chat-agent')
        ->and($r['structured_output'])->toBeFalse()
        ->and($r['triggerword'])->toBeNull();
});

test('it routes mapping trigger and extracts projekt uuid', function () {
    $router = new ChatTriggerwordRouter;

    $r = $router->route('@mapping 123e4567-e89b-12d3-a456-426614174000 Meine Frage');

    expect($r['config_key'])->toBe('scoping_mapping_agent')
        ->and($r['projekt_id'])->toBe('123e4567-e89b-12d3-a456-426614174000')
        ->and($r['cleaned_message'])->toBe('Meine Frage')
        ->and($r['structured_output'])->toBeTrue();
});

test('it keeps second token as message when not a uuid', function () {
    $router = new ChatTriggerwordRouter;

    $r = $router->route('@review not-a-uuid Screen this');

    expect($r['config_key'])->toBe('review_agent')
        ->and($r['projekt_id'])->toBeNull()
        ->and($r['cleaned_message'])->toBe('not-a-uuid Screen this');
});

test('it routes evaluation trigger', function () {
    $router = new ChatTriggerwordRouter;

    $r = $router->route('@bewertung 123e4567-e89b-12d3-a456-426614174000 Bitte bewerte RoB2');

    expect($r['config_key'])->toBe('evaluation_agent')
        ->and($r['projekt_id'])->toBe('123e4567-e89b-12d3-a456-426614174000')
        ->and($r['cleaned_message'])->toBe('Bitte bewerte RoB2')
        ->and($r['structured_output'])->toBeTrue();
});
