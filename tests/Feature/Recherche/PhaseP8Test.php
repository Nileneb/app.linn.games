<?php

use App\Models\Recherche\P8Limitation;
use App\Models\Recherche\P8Reproduzierbarkeitspruefung;
use App\Models\Recherche\P8Suchprotokoll;
use App\Models\Recherche\P8UpdatePlan;
use App\Models\Recherche\Projekt;
use App\Models\User;
use Livewire\Volt\Volt;

// ─── Suchprotokoll ───────────────────────────────────────────

test('P8: kann suchprotokoll erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('newSp')
        ->assertSet('showSpForm', true)
        ->set('spDatenbank', 'PubMed')
        ->set('spSuchstringFinal', '(diabetes OR insulin) AND RCT')
        ->set('spTrefferGesamt', 234)
        ->set('spTrefferEindeutig', 198)
        ->call('saveSp')
        ->assertSet('showSpForm', false);

    $this->assertDatabaseHas('p8_suchprotokoll', [
        'datenbank' => 'PubMed',
        'treffer_gesamt' => 234,
    ]);
});

test('P8: suchprotokoll validiert pflichtfelder', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('newSp')
        ->set('spDatenbank', '')
        ->set('spSuchstringFinal', '')
        ->call('saveSp')
        ->assertHasErrors(['spDatenbank', 'spSuchstringFinal']);
});

test('P8: kann suchprotokoll bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $sp = P8Suchprotokoll::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'suchstring_final' => 'alt',
        'treffer_gesamt' => 100,
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('editSp', $sp->id)
        ->assertSet('spDatenbank', 'PubMed')
        ->set('spTrefferGesamt', 200)
        ->call('saveSp');

    $this->assertDatabaseHas('p8_suchprotokoll', ['id' => $sp->id, 'treffer_gesamt' => 200]);
});

test('P8: kann suchprotokoll löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $sp = P8Suchprotokoll::create(['projekt_id' => $projekt->id, 'datenbank' => 'PubMed', 'suchstring_final' => 'test']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('deleteSp', $sp->id);

    $this->assertDatabaseMissing('p8_suchprotokoll', ['id' => $sp->id]);
});

// ─── Limitation ──────────────────────────────────────────────

test('P8: kann limitation erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('newLim')
        ->assertSet('showLimForm', true)
        ->set('limTyp', 'Sprachbias')
        ->set('limBeschreibung', 'Nur englische Studien eingeschlossen')
        ->set('limAuswirkung', 'Mögliche Verzerrung der Ergebnisse')
        ->call('saveLim')
        ->assertSet('showLimForm', false);

    $this->assertDatabaseHas('p8_limitationen', [
        'projekt_id' => $projekt->id,
        'limitationstyp' => 'Sprachbias',
    ]);
});

test('P8: limitation validiert typ', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('newLim')
        ->set('limTyp', '')
        ->call('saveLim')
        ->assertHasErrors(['limTyp']);
});

test('P8: kann limitation löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $lim = P8Limitation::create(['projekt_id' => $projekt->id, 'limitationstyp' => 'Test']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('deleteLim', $lim->id);

    $this->assertDatabaseMissing('p8_limitationen', ['id' => $lim->id]);
});

// ─── Reproduzierbarkeit ──────────────────────────────────────

test('P8: kann reproduzierbarkeitsprüfung erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('newRep')
        ->assertSet('showRepForm', true)
        ->set('repPruefpunkt', 'Suchstrings dokumentiert')
        ->set('repErfuellt', true)
        ->set('repAnmerkung', 'Vollständig')
        ->call('saveRep')
        ->assertSet('showRepForm', false);

    $this->assertDatabaseHas('p8_reproduzierbarkeitspruefung', [
        'projekt_id' => $projekt->id,
        'pruefpunkt' => 'Suchstrings dokumentiert',
        'erfuellt' => true,
    ]);
});

test('P8: reproduzierbarkeit validiert pruefpunkt', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('newRep')
        ->set('repPruefpunkt', '')
        ->call('saveRep')
        ->assertHasErrors(['repPruefpunkt']);
});

test('P8: kann reproduzierbarkeitsprüfung löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $rep = P8Reproduzierbarkeitspruefung::create(['projekt_id' => $projekt->id, 'pruefpunkt' => 'Test']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('deleteRep', $rep->id);

    $this->assertDatabaseMissing('p8_reproduzierbarkeitspruefung', ['id' => $rep->id]);
});

// ─── Update-Plan ─────────────────────────────────────────────

test('P8: kann update-plan erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('newUp')
        ->assertSet('showUpForm', true)
        ->set('upTyp', 'periodisch')
        ->set('upIntervall', '6 Monate')
        ->set('upVerantwortlich', 'Forschungsteam')
        ->call('saveUp')
        ->assertSet('showUpForm', false);

    $this->assertDatabaseHas('p8_update_plan', [
        'projekt_id' => $projekt->id,
        'update_typ' => 'periodisch',
        'intervall' => '6 Monate',
    ]);
});

test('P8: kann update-plan löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $up = P8UpdatePlan::create(['projekt_id' => $projekt->id, 'update_typ' => 'periodisch']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->call('deleteUp', $up->id);

    $this->assertDatabaseMissing('p8_update_plan', ['id' => $up->id]);
});

// ─── Auth ────────────────────────────────────────────────────

test('P8: fremder user bekommt 403', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    Volt::test('recherche.phase-p8', ['projekt' => $projekt])
        ->assertStatus(403);
});
