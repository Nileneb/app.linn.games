<?php

use App\Models\Recherche\{Projekt, P5PrismaZahlen, P5ScreeningKriterium, P5ToolEntscheidung, P5Treffer, P5ScreeningEntscheidung};
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

// ─── PRISMA Zahlen ───────────────────────────────────────────

test('P5: kann prisma-zahlen erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('newPrisma')
        ->assertSet('showPrismaForm', true)
        ->set('prismaIdentGesamt', 500)
        ->set('prismaDatenbankTreffer', 400)
        ->set('prismaGraueLit', 100)
        ->set('prismaNachDedup', 350)
        ->set('prismaAusgeschlossenL1', 200)
        ->set('prismaVolltextGeprueft', 150)
        ->set('prismaAusgeschlossenL2', 50)
        ->set('prismaEingeschlossen', 100)
        ->call('savePrisma')
        ->assertSet('showPrismaForm', false);

    $this->assertDatabaseHas('p5_prisma_zahlen', [
        'projekt_id' => $projekt->id,
        'identifiziert_gesamt' => 500,
        'eingeschlossen_final' => 100,
    ]);
});

test('P5: kann prisma-zahlen bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $pz = P5PrismaZahlen::create([
        'projekt_id' => $projekt->id,
        'identifiziert_gesamt' => 500,
        'eingeschlossen_final' => 100,
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('editPrisma', $pz->id)
        ->assertSet('showPrismaForm', true)
        ->assertSet('prismaIdentGesamt', 500)
        ->set('prismaEingeschlossen', 200)
        ->call('savePrisma')
        ->assertSet('showPrismaForm', false);

    $this->assertDatabaseHas('p5_prisma_zahlen', ['id' => $pz->id, 'eingeschlossen_final' => 200]);
});

test('P5: kann prisma-zahlen löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $pz = P5PrismaZahlen::create(['projekt_id' => $projekt->id, 'identifiziert_gesamt' => 100]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('deletePrisma', $pz->id);

    $this->assertDatabaseMissing('p5_prisma_zahlen', ['id' => $pz->id]);
});

// ─── Screening-Kriterien ─────────────────────────────────────

test('P5: kann screening-kriterium erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('newSk')
        ->assertSet('showSkForm', true)
        ->set('skLevel', 'L1_titel_abstract')
        ->set('skTyp', 'einschluss')
        ->set('skBeschreibung', 'Nur RCTs ab 2015')
        ->set('skBeispiel', 'z. B. keine Fallstudien')
        ->call('saveSk')
        ->assertSet('showSkForm', false);

    $this->assertDatabaseHas('p5_screening_kriterien', [
        'projekt_id' => $projekt->id,
        'beschreibung' => 'Nur RCTs ab 2015',
    ]);
});

test('P5: screening-kriterium validiert beschreibung', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('newSk')
        ->set('skBeschreibung', '')
        ->call('saveSk')
        ->assertHasErrors(['skBeschreibung']);
});

test('P5: kann screening-kriterium bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $sk = P5ScreeningKriterium::create([
        'projekt_id' => $projekt->id,
        'level' => 'L1_titel_abstract',
        'kriterium_typ' => 'einschluss',
        'beschreibung' => 'Alt',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('editSk', $sk->id)
        ->assertSet('showSkForm', true)
        ->set('skBeschreibung', 'Aktualisiert')
        ->call('saveSk');

    $this->assertDatabaseHas('p5_screening_kriterien', ['id' => $sk->id, 'beschreibung' => 'Aktualisiert']);
});

test('P5: kann screening-kriterium löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $sk = P5ScreeningKriterium::create([
        'projekt_id' => $projekt->id,
        'level' => 'L1_titel_abstract',
        'kriterium_typ' => 'einschluss',
        'beschreibung' => 'Wird gelöscht',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('deleteSk', $sk->id);

    $this->assertDatabaseMissing('p5_screening_kriterien', ['id' => $sk->id]);
});

// ─── Tool-Entscheidung ───────────────────────────────────────

test('P5: kann tool-entscheidung erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('newTool')
        ->set('toolName', 'Rayyan')
        ->set('toolGewaehlt', true)
        ->set('toolBegruendung', 'Kostenfrei und intuitiv')
        ->call('saveTool')
        ->assertSet('showToolForm', false);

    $this->assertDatabaseHas('p5_tool_entscheidung', [
        'projekt_id' => $projekt->id,
        'tool' => 'Rayyan',
        'gewaehlt' => true,
    ]);
});

test('P5: kann tool-entscheidung löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $tool = P5ToolEntscheidung::create(['projekt_id' => $projekt->id, 'tool' => 'Covidence', 'gewaehlt' => false]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('deleteTool', $tool->id);

    $this->assertDatabaseMissing('p5_tool_entscheidung', ['id' => $tool->id]);
});

// ─── Screening-Entscheidung ──────────────────────────────────

test('P5: kann screening-entscheidung erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $treffer = P5Treffer::create([
        'projekt_id' => $projekt->id,
        'record_id' => 'REC-001',
        'titel' => 'Test-Studie',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('openScreen', $treffer->id)
        ->assertSet('showScreenForm', true)
        ->assertSet('screenTrefferId', $treffer->id)
        ->set('screenLevel', 'L1_titel_abstract')
        ->set('screenEntscheidung', 'eingeschlossen')
        ->set('screenReviewer', 'Reviewer A')
        ->call('saveScreen')
        ->assertSet('showScreenForm', false);

    $this->assertDatabaseHas('p5_screening_entscheidungen', [
        'treffer_id' => $treffer->id,
        'entscheidung' => 'eingeschlossen',
    ]);
});

test('P5: screening-entscheidung validiert pflichtfelder', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $treffer = P5Treffer::create([
        'projekt_id' => $projekt->id,
        'record_id' => 'REC-VAL',
        'titel' => 'Validierungstest',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('openScreen', $treffer->id)
        ->set('screenEntscheidung', '')
        ->call('saveScreen')
        ->assertHasErrors(['screenEntscheidung']);
});

test('P5: kann retrieval-agent triggern und ergebnis speichern', function () {
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.retrieval_agent', 'retrieval-uuid');

    Http::fake([
        'app.langdock.com/*' => Http::response([
            'content' => json_encode([
                'downloaded' => true,
                'source_url' => 'https://publisher.example/studie-123',
                'storage_path' => '/data/papers/studie-123.pdf',
                'note' => 'Download erfolgreich',
            ]),
        ], 200),
    ]);

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $treffer = P5Treffer::create([
        'projekt_id' => $projekt->id,
        'record_id' => 'REC-RTR-001',
        'titel' => 'Retrieval-Studie',
        'doi' => '10.1000/test-doi',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->call('triggerRetrieval', $treffer->id)
        ->assertSet('retrievalLoadingTrefferId', null);

    $this->assertDatabaseHas('p5_treffer', [
        'id' => $treffer->id,
        'retrieval_downloaded' => true,
        'retrieval_source_url' => 'https://publisher.example/studie-123',
        'retrieval_storage_path' => '/data/papers/studie-123.pdf',
        'retrieval_status' => 'heruntergeladen',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'retrieval-uuid/completions')
            && $request->hasHeader('Authorization', 'Bearer test-api-key');
    });
});

// ─── Auth ────────────────────────────────────────────────────

test('P5: fremder user bekommt 403', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    Volt::test('recherche.phase-p5', ['projekt' => $projekt])
        ->assertStatus(403);
});
