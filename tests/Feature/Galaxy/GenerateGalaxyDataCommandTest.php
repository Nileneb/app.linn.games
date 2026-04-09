<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('galaxy:generate command exists and accepts a projekt-id argument', function () {
    expect(Artisan::all())->toHaveKey('galaxy:generate');
});

test('galaxy:generate outputs error when projekt not found', function () {
    $this->artisan('galaxy:generate', ['projekt_id' => 'non-existent-uuid'])
        ->expectsOutput('Projekt nicht gefunden: non-existent-uuid')
        ->assertExitCode(1);
});

test('galaxy:generate outputs error when python script not found', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    config(['galaxy.python_script' => '/nonexistent/path/generate_galaxy.py']);

    $this->artisan('galaxy:generate', ['projekt_id' => $projekt->id])
        ->expectsOutput('Python-Script nicht gefunden: /nonexistent/path/generate_galaxy.py')
        ->assertExitCode(1);
});
