<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Livewire\Volt\Volt;

beforeEach(function () {
    Config::set('services.anthropic.available_chat_models', [
        'claude-haiku-4-5-20251001' => [
            'label' => 'Haiku',
            'description' => 'Fast',
            'price_per_1m_input_usd' => 0.80,
            'price_per_1m_output_usd' => 4.00,
        ],
        'claude-sonnet-4-6' => [
            'label' => 'Sonnet',
            'description' => 'Balanced',
            'price_per_1m_input_usd' => 3.00,
            'price_per_1m_output_usd' => 15.00,
        ],
    ]);
    Config::set('services.anthropic.agent_models.chat-agent', 'claude-sonnet-4-6');
});

test('settings page renders with current selection', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'preferred_chat_model' => 'claude-haiku-4-5-20251001',
    ]);

    $this->actingAs($user)->get('/settings/ai-model')
        ->assertOk()
        ->assertSee('Chat-Modell')
        ->assertSee('Haiku')
        ->assertSee('Sonnet');
});

test('can save a whitelisted model preference', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    Volt::actingAs($user)
        ->test('settings.ai-model')
        ->set('preferred_chat_model', 'claude-haiku-4-5-20251001')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->preferred_chat_model)->toBe('claude-haiku-4-5-20251001');
});

test('can reset to null (use default)', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'preferred_chat_model' => 'claude-haiku-4-5-20251001',
    ]);

    Volt::actingAs($user)
        ->test('settings.ai-model')
        ->set('preferred_chat_model', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->preferred_chat_model)->toBeNull();
});

test('rejects model not in whitelist', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    Volt::actingAs($user)
        ->test('settings.ai-model')
        ->set('preferred_chat_model', 'claude-opus-4-7')
        ->call('save')
        ->assertHasErrors('preferred_chat_model');

    expect($user->fresh()->preferred_chat_model)->toBeNull();
});

test('resolvedChatModel returns preference when set and whitelisted', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'preferred_chat_model' => 'claude-haiku-4-5-20251001',
    ]);

    expect($user->resolvedChatModel())->toBe('claude-haiku-4-5-20251001');
});

test('resolvedChatModel falls back to config when preference null', function () {
    $user = User::factory()->withoutTwoFactor()->create(['preferred_chat_model' => null]);

    expect($user->resolvedChatModel())->toBe('claude-sonnet-4-6');
});

test('resolvedChatModel falls back to config when preference removed from whitelist', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'preferred_chat_model' => 'claude-opus-4-7',
    ]);

    expect($user->resolvedChatModel())->toBe('claude-sonnet-4-6');
});
