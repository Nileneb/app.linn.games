<?php

use App\Models\Recherche\{Projekt, P5Treffer, P6Qualitaetsbewertung, P6Luckenanalyse};
use App\Models\User;
use Livewire\Volt\Volt;

// ─── Qualitätsbewertung ──────────────────────────────────────

test('P6: kann qualitaetsbewertung erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $treffer = P5Treffer::create([
        'projekt_id' => $projekt->id,
        'record_id' => 'REC-QB-001',
        'titel' => 'Bewertungs-Studie',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p6', ['projekt' => $projekt])
        ->call('newQb')
        ->assertSet('showQbForm', true)
        ->set('qbTrefferId', $treffer->id)
        ->set('qbStudientyp', 'RCT')
        ->set('qbRobTool', 'RoB2')
        ->set('qbGesamturteil', 'niedrig')
        ->set('qbHauptproblem', 'Kein Blinding')
        ->set('qbImReviewBehalten', true)
        ->call('saveQb')
        ->assertSet('showQbForm', false);

    $this->assertDatabaseHas('p6_qualitaetsbewertung', [
        'treffer_id' => $treffer->id,
        'studientyp' => 'RCT',
        'gesamturteil' => 'niedrig',
    ]);
});

test('P6: qualitaetsbewertung validiert pflichtfelder', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p6', ['projekt' => $projekt])
        ->call('newQb')
        ->set('qbTrefferId', '')
        ->call('saveQb')
        ->assertHasErrors(['qbTrefferId']);
});

test('P6: kann qualitaetsbewertung bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $treffer = P5Treffer::create(['projekt_id' => $projekt->id, 'record_id' => 'REC-QB-002', 'titel' => 'Edit-Studie']);
    $qb = P6Qualitaetsbewertung::create([
        'treffer_id' => $treffer->id,
        'studientyp' => 'RCT',
        'rob_tool' => 'RoB2',
        'gesamturteil' => 'moderat',
        'bewertet_am' => now()->toDateString(),
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p6', ['projekt' => $projekt])
        ->call('editQb', $qb->id)
        ->assertSet('showQbForm', true)
        ->assertSet('qbGesamturteil', 'moderat')
        ->set('qbGesamturteil', 'niedrig')
        ->call('saveQb');

    $this->assertDatabaseHas('p6_qualitaetsbewertung', ['id' => $qb->id, 'gesamturteil' => 'niedrig']);
});

test('P6: kann qualitaetsbewertung löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $treffer = P5Treffer::create(['projekt_id' => $projekt->id, 'record_id' => 'REC-QB-003', 'titel' => 'Delete-Studie']);
    $qb = P6Qualitaetsbewertung::create([
        'treffer_id' => $treffer->id,
        'studientyp' => 'RCT',
        'rob_tool' => 'RoB2',
        'gesamturteil' => 'hoch',
        'bewertet_am' => now()->toDateString(),
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p6', ['projekt' => $projekt])
        ->call('deleteQb', $qb->id);

    $this->assertDatabaseMissing('p6_qualitaetsbewertung', ['id' => $qb->id]);
});

// ─── Lückenanalyse ───────────────────────────────────────────

test('P6: kann lückenanalyse erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p6', ['projekt' => $projekt])
        ->call('newLa')
        ->assertSet('showLaForm', true)
        ->set('laFehlenderAspekt', 'Langzeit-Follow-Up fehlt')
        ->set('laFehlenderStudientyp', 'RCT')
        ->set('laMoeglicheKonsequenz', 'Unsichere Evidenz')
        ->set('laEmpfehlung', 'Weitere Studien nötig')
        ->call('saveLa')
        ->assertSet('showLaForm', false);

    $this->assertDatabaseHas('p6_luckenanalyse', [
        'projekt_id' => $projekt->id,
        'fehlender_aspekt' => 'Langzeit-Follow-Up fehlt',
    ]);
});

test('P6: lückenanalyse validiert fehlender_aspekt', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p6', ['projekt' => $projekt])
        ->call('newLa')
        ->set('laFehlenderAspekt', '')
        ->call('saveLa')
        ->assertHasErrors(['laFehlenderAspekt']);
});

test('P6: kann lückenanalyse bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $la = P6Luckenanalyse::create([
        'projekt_id' => $projekt->id,
        'fehlender_aspekt' => 'Alt',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p6', ['projekt' => $projekt])
        ->call('editLa', $la->id)
        ->set('laFehlenderAspekt', 'Aktualisiert')
        ->call('saveLa');

    $this->assertDatabaseHas('p6_luckenanalyse', ['id' => $la->id, 'fehlender_aspekt' => 'Aktualisiert']);
});

test('P6: kann lückenanalyse löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $la = P6Luckenanalyse::create(['projekt_id' => $projekt->id, 'fehlender_aspekt' => 'Wird gelöscht']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p6', ['projekt' => $projekt])
        ->call('deleteLa', $la->id);

    $this->assertDatabaseMissing('p6_luckenanalyse', ['id' => $la->id]);
});

// ─── Auth ────────────────────────────────────────────────────

test('P6: fremder user bekommt 403', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    Volt::test('recherche.phase-p6', ['projekt' => $projekt])
        ->assertStatus(403);
});
