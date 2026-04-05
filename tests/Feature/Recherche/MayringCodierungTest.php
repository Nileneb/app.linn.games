<?php

use App\Jobs\ProcessMayringBatchJob;
use App\Jobs\ProcessMayringChunkJob;
use App\Models\ChunkCodierung;
use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'editor',   'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'mitglied', 'guard_name' => 'web']);
});

test('mayring page renders for projekt owner', function () {
    $user    = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('recherche.mayring', $projekt))
        ->assertOk()
        ->assertSee('Mayring-Codierung');
});

test('mayring page is forbidden for other users', function () {
    $owner    = User::factory()->withoutTwoFactor()->create();
    $intruder = User::factory()->withoutTwoFactor()->create();
    $projekt  = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->get(route('recherche.mayring', $projekt))
        ->assertForbidden();
});

test('startCodierung dispatches ProcessMayringBatchJob', function () {
    Bus::fake();

    $user    = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    // Insert a real embedding so totalChunks > 0
    DB::table('paper_embeddings')->insert([
        'id'          => \Illuminate\Support\Str::uuid(),
        'projekt_id'  => $projekt->id,
        'source'      => 'test',
        'paper_id'    => 'p1',
        'title'       => 'Test Paper',
        'chunk_index' => 0,
        'text_chunk'  => 'Sample text.',
        'erstellt_am' => now(),
    ]);

    $this->actingAs($user);

    Volt::test('recherche.mayring-codierung', ['projekt' => $projekt])
        ->call('startCodierung');

    Bus::assertDispatched(ProcessMayringBatchJob::class, function ($job) use ($projekt) {
        return $job->projektId === $projekt->id;
    });
});

test('abortCodierung marks pending codierungen as failed', function () {
    $user    = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $embeddingId = \Illuminate\Support\Str::uuid();
    DB::table('paper_embeddings')->insert([
        'id'          => $embeddingId,
        'projekt_id'  => $projekt->id,
        'source'      => 'test',
        'paper_id'    => 'p1',
        'title'       => 'Test',
        'chunk_index' => 0,
        'text_chunk'  => 'Text',
        'erstellt_am' => now(),
    ]);

    ChunkCodierung::create([
        'projekt_id'         => $projekt->id,
        'paper_embedding_id' => $embeddingId,
        'status'             => 'pending',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.mayring-codierung', ['projekt' => $projekt])
        ->call('abortCodierung');

    expect(
        ChunkCodierung::where('projekt_id', $projekt->id)
            ->where('status', 'failed')
            ->exists()
    )->toBeTrue();
});

test('ProcessMayringBatchJob erstellt ChunkCodierung-Einträge und dispatched Chunk-Jobs', function () {
    Bus::fake();

    $user    = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $embeddingId = \Illuminate\Support\Str::uuid();
    DB::table('paper_embeddings')->insert([
        'id'          => $embeddingId,
        'projekt_id'  => $projekt->id,
        'source'      => 'test',
        'paper_id'    => 'p1',
        'title'       => 'Test',
        'chunk_index' => 0,
        'text_chunk'  => 'Sample text.',
        'erstellt_am' => now(),
    ]);

    (new ProcessMayringBatchJob($projekt->id))->handle();

    expect(ChunkCodierung::where('projekt_id', $projekt->id)->count())->toBe(1);

    Bus::assertBatched(function ($batch) {
        return count($batch->jobs) === 1;
    });
});

test('ProcessMayringBatchJob überspringt bereits codierte Chunks', function () {
    Bus::fake();

    $user    = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $embeddingId = \Illuminate\Support\Str::uuid();
    DB::table('paper_embeddings')->insert([
        'id'          => $embeddingId,
        'projekt_id'  => $projekt->id,
        'source'      => 'test',
        'paper_id'    => 'p1',
        'title'       => 'Test',
        'chunk_index' => 0,
        'text_chunk'  => 'Done.',
        'erstellt_am' => now(),
    ]);

    ChunkCodierung::create([
        'projekt_id'         => $projekt->id,
        'paper_embedding_id' => $embeddingId,
        'status'             => 'completed',
        'kategorie'          => 'A',
    ]);

    (new ProcessMayringBatchJob($projekt->id))->handle();

    Bus::assertNothingBatched();
});
