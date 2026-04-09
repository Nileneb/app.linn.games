<?php

use App\Models\Recherche\P5Treffer;
use App\Models\Recherche\P7Datenextraktion;
use App\Models\Recherche\P7GradeEinschaetzung;
use App\Models\Recherche\P7MusterKonsistenz;
use App\Models\Recherche\P7SyntheseMethode;
use App\Models\Recherche\Projekt;
use App\Models\User;
use Livewire\Volt\Volt;

// ─── Synthese-Methode ────────────────────────────────────────

test('P7: kann synthese-methode erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('newSm')
        ->assertSet('showSmForm', true)
        ->set('smMethode', 'meta_analyse')
        ->set('smGewaehlt', true)
        ->set('smBegruendung', 'Quantitative Daten vorhanden')
        ->call('saveSm')
        ->assertSet('showSmForm', false);

    $this->assertDatabaseHas('p7_synthese_methode', [
        'projekt_id' => $projekt->id,
        'methode' => 'meta_analyse',
        'gewaehlt' => true,
    ]);
});

test('P7: kann synthese-methode bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $sm = P7SyntheseMethode::create([
        'projekt_id' => $projekt->id,
        'methode' => 'narrative_synthese',
        'gewaehlt' => false,
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('editSm', $sm->id)
        ->assertSet('smMethode', 'narrative_synthese')
        ->set('smGewaehlt', true)
        ->call('saveSm');

    $this->assertDatabaseHas('p7_synthese_methode', ['id' => $sm->id, 'gewaehlt' => true]);
});

test('P7: kann synthese-methode löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $sm = P7SyntheseMethode::create(['projekt_id' => $projekt->id, 'methode' => 'narrative_synthese']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('deleteSm', $sm->id);

    $this->assertDatabaseMissing('p7_synthese_methode', ['id' => $sm->id]);
});

// ─── Datenextraktion ─────────────────────────────────────────

test('P7: kann datenextraktion erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $treffer = P5Treffer::create(['projekt_id' => $projekt->id, 'record_id' => 'REC-DE-001', 'titel' => 'Extraktions-Studie']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('newDe')
        ->assertSet('showDeForm', true)
        ->set('deTrefferId', $treffer->id)
        ->set('deLand', 'DE')
        ->set('deHauptbefund', 'Positiver Effekt')
        ->set('deQualitaetsurteil', 'hoch')
        ->call('saveDe')
        ->assertSet('showDeForm', false);

    $this->assertDatabaseHas('p7_datenextraktion', [
        'treffer_id' => $treffer->id,
        'hauptbefund' => 'Positiver Effekt',
    ]);
});

test('P7: datenextraktion validiert pflichtfelder', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('newDe')
        ->set('deTrefferId', '')
        ->set('deHauptbefund', '')
        ->call('saveDe')
        ->assertHasErrors(['deTrefferId', 'deHauptbefund']);
});

test('P7: kann datenextraktion bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $treffer = P5Treffer::create(['projekt_id' => $projekt->id, 'record_id' => 'REC-DE-002', 'titel' => 'Edit-Studie']);
    $de = P7Datenextraktion::create([
        'treffer_id' => $treffer->id,
        'hauptbefund' => 'Alt',
        'land' => 'DE',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('editDe', $de->id)
        ->assertSet('deHauptbefund', 'Alt')
        ->set('deHauptbefund', 'Aktualisiert')
        ->call('saveDe');

    $this->assertDatabaseHas('p7_datenextraktion', ['id' => $de->id, 'hauptbefund' => 'Aktualisiert']);
});

test('P7: kann datenextraktion löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $treffer = P5Treffer::create(['projekt_id' => $projekt->id, 'record_id' => 'REC-DE-003', 'titel' => 'Delete-Studie']);
    $de = P7Datenextraktion::create(['treffer_id' => $treffer->id, 'hauptbefund' => 'Wird gelöscht']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('deleteDe', $de->id);

    $this->assertDatabaseMissing('p7_datenextraktion', ['id' => $de->id]);
});

// ─── Muster & Konsistenz ─────────────────────────────────────

test('P7: kann muster erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('newMk')
        ->assertSet('showMkForm', true)
        ->set('mkBefund', 'Konsistenter positiver Effekt')
        ->set('mkUnterstuetzend', 'Studie A, Studie B')
        ->set('mkWidersprechend', 'Studie C')
        ->set('mkErklaerung', 'Abweichung wegen kleiner Stichprobe')
        ->call('saveMk')
        ->assertSet('showMkForm', false);

    $this->assertDatabaseHas('p7_muster_konsistenz', [
        'projekt_id' => $projekt->id,
        'muster_befund' => 'Konsistenter positiver Effekt',
    ]);
});

test('P7: muster validiert befund', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('newMk')
        ->set('mkBefund', '')
        ->call('saveMk')
        ->assertHasErrors(['mkBefund']);
});

test('P7: kann muster löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $mk = P7MusterKonsistenz::create(['projekt_id' => $projekt->id, 'muster_befund' => 'Wird gelöscht']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('deleteMk', $mk->id);

    $this->assertDatabaseMissing('p7_muster_konsistenz', ['id' => $mk->id]);
});

// ─── GRADE-Einschätzung ──────────────────────────────────────

test('P7: kann grade-einschaetzung erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('newGr')
        ->assertSet('showGrForm', true)
        ->set('grOutcome', 'Mortalität')
        ->set('grStudienanzahl', 5)
        ->set('grUrteil', 'stark')
        ->set('grBegruendung', 'Konsistente Ergebnisse')
        ->call('saveGr')
        ->assertSet('showGrForm', false);

    $this->assertDatabaseHas('p7_grade_einschaetzung', [
        'projekt_id' => $projekt->id,
        'outcome' => 'Mortalität',
        'grade_urteil' => 'stark',
    ]);
});

test('P7: grade validiert pflichtfelder', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('newGr')
        ->set('grOutcome', '')
        ->call('saveGr')
        ->assertHasErrors(['grOutcome']);
});

test('P7: kann grade löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $gr = P7GradeEinschaetzung::create(['projekt_id' => $projekt->id, 'outcome' => 'Test', 'grade_urteil' => 'moderat']);

    $this->actingAs($user);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->call('deleteGr', $gr->id);

    $this->assertDatabaseMissing('p7_grade_einschaetzung', ['id' => $gr->id]);
});

// ─── Auth ────────────────────────────────────────────────────

test('P7: fremder user bekommt 403', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    Volt::test('recherche.phase-p7', ['projekt' => $projekt])
        ->assertStatus(403);
});
