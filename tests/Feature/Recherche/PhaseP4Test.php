<?php

use App\Models\Recherche\{Projekt, P4Suchstring, P4ThesaurusMapping, P4Anpassungsprotokoll};
use App\Models\User;
use Livewire\Volt\Volt;

// ─── Suchstring ──────────────────────────────────────────────

test('P4: kann suchstring erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newSs')
        ->set('ss.datenbank', 'PubMed')
        ->set('ss.suchstring', '(Population OR Patients) AND (Intervention)')
        ->set('ss.version', 'v1.0')
        ->set('ss.trefferAnzahl', 512)
        ->set('ss.suchdatum', '2026-03-20')
        ->call('saveSs')
        ->assertSet('showSsForm', false);

    $this->assertDatabaseHas('p4_suchstrings', [
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'treffer_anzahl' => 512,
    ]);
});

test('P4: suchstring validiert datenbank als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newSs')
        ->set('ss.datenbank', '')
        ->call('saveSs')
        ->assertHasErrors(['ss.datenbank']);
});

test('P4: suchstring filter werden als array gespeichert', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newSs')
        ->set('ss.datenbank', 'CINAHL')
        ->set('ss.suchstring', 'test query')
        ->set('ss.filter', 'English, 2020-2026, Peer Reviewed')
        ->call('saveSs');

    $ss = P4Suchstring::where('projekt_id', $projekt->id)->first();
    expect($ss->gesetzte_filter)->toBeArray()
        ->and($ss->gesetzte_filter)->toContain('English')
        ->and($ss->gesetzte_filter)->toContain('Peer Reviewed');
});

test('P4: kann suchstring bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $ss = P4Suchstring::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'suchstring' => 'alt',
        'version' => 'v1.0',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('editSs', $ss->id)
        ->assertSet('ss.datenbank', 'PubMed')
        ->set('ss.datenbank', 'Embase')
        ->set('ss.version', 'v2.0')
        ->call('saveSs');

    $this->assertDatabaseHas('p4_suchstrings', ['id' => $ss->id, 'datenbank' => 'Embase', 'version' => 'v2.0']);
});

test('P4: kann suchstring löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $ss = P4Suchstring::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'suchstring' => 'test',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('deleteSs', $ss->id);

    $this->assertDatabaseMissing('p4_suchstrings', ['id' => $ss->id]);
});

// ─── ThesaurusMapping ────────────────────────────────────────

test('P4: kann thesaurus-mapping erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newTh')
        ->set('th.freitextDe', 'Bluthochdruck')
        ->set('th.freitextEn', 'Hypertension')
        ->set('th.mesh', 'Hypertension')
        ->set('th.emtree', 'hypertension')
        ->set('th.psycinfo', '')
        ->call('saveTh')
        ->assertSet('showThForm', false);

    $this->assertDatabaseHas('p4_thesaurus_mapping', [
        'projekt_id' => $projekt->id,
        'freitext_de' => 'Bluthochdruck',
        'mesh_term' => 'Hypertension',
    ]);
});

test('P4: thesaurus validiert freitext_de als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newTh')
        ->set('th.freitextDe', '')
        ->call('saveTh')
        ->assertHasErrors(['th.freitextDe']);
});

test('P4: kann thesaurus bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $th = P4ThesaurusMapping::create([
        'projekt_id' => $projekt->id,
        'freitext_de' => 'Alt',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('editTh', $th->id)
        ->assertSet('th.freitextDe', 'Alt')
        ->set('th.freitextDe', 'Neu')
        ->set('th.mesh', 'NewMeSH')
        ->call('saveTh');

    $this->assertDatabaseHas('p4_thesaurus_mapping', ['id' => $th->id, 'freitext_de' => 'Neu', 'mesh_term' => 'NewMeSH']);
});

test('P4: kann thesaurus löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $th = P4ThesaurusMapping::create([
        'projekt_id' => $projekt->id,
        'freitext_de' => 'Test',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('deleteTh', $th->id);

    $this->assertDatabaseMissing('p4_thesaurus_mapping', ['id' => $th->id]);
});

// ─── Anpassungsprotokoll ─────────────────────────────────────

test('P4: kann anpassungsprotokoll erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $ss = P4Suchstring::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'suchstring' => 'test query',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newAp', $ss->id)
        ->assertSet('ap.suchstringId', $ss->id)
        ->set('ap.version', 'v1.1')
        ->set('ap.datum', '2026-03-25')
        ->set('ap.aenderung', 'MeSH-Term hinzugefügt')
        ->set('ap.grund', 'Zu wenig Treffer')
        ->set('ap.trefferVorher', 50)
        ->set('ap.trefferNachher', 120)
        ->set('ap.entscheidung', 'Übernommen')
        ->call('saveAp')
        ->assertSet('showApForm', false);

    $this->assertDatabaseHas('p4_anpassungsprotokoll', [
        'suchstring_id' => $ss->id,
        'aenderung' => 'MeSH-Term hinzugefügt',
        'treffer_vorher' => 50,
        'treffer_nachher' => 120,
    ]);
});

test('P4: anpassung validiert suchstring_id und aenderung', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newAp')
        ->set('ap.suchstringId', '')
        ->set('ap.aenderung', '')
        ->call('saveAp')
        ->assertHasErrors(['ap.suchstringId', 'ap.aenderung']);
});

test('P4: anpassung gehoert zu suchstring des eigenen projekts', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $foreignProjekt = Projekt::factory()->create(['user_id' => $other->id]);
    $foreignSs = P4Suchstring::create([
        'projekt_id' => $foreignProjekt->id,
        'datenbank' => 'Embase',
        'suchstring' => 'foreign',
        'version' => 'v1.0',
    ]);

    $this->actingAs($user);

    // Versuch eine Anpassung für einen fremden Suchstring zu erstellen — wirft ModelNotFoundException
    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newAp', $foreignSs->id)
        ->set('ap.aenderung', 'Hack-Versuch')
        ->call('saveAp')
        ->assertStatus(404);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('P4: kann anpassung bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $ss = P4Suchstring::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'suchstring' => 'test',
    ]);
    $ap = P4Anpassungsprotokoll::create([
        'suchstring_id' => $ss->id,
        'version' => 'v1.0',
        'aenderung' => 'Original',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('editAp', $ap->id)
        ->assertSet('ap.aenderung', 'Original')
        ->set('ap.aenderung', 'Geändert')
        ->call('saveAp');

    $this->assertDatabaseHas('p4_anpassungsprotokoll', ['id' => $ap->id, 'aenderung' => 'Geändert']);
});

test('P4: kann anpassung löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $ss = P4Suchstring::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'suchstring' => 'test',
    ]);
    $ap = P4Anpassungsprotokoll::create([
        'suchstring_id' => $ss->id,
        'version' => 'v1.0',
        'aenderung' => 'Löschen',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('deleteAp', $ap->id);

    $this->assertDatabaseMissing('p4_anpassungsprotokoll', ['id' => $ap->id]);
});

test('P4: suchstring loeschen entfernt auch anpassungen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $ss = P4Suchstring::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'suchstring' => 'test',
    ]);
    $ap = P4Anpassungsprotokoll::create([
        'suchstring_id' => $ss->id,
        'version' => 'v1.0',
        'aenderung' => 'Cascaded',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('deleteSs', $ss->id);

    $this->assertDatabaseMissing('p4_suchstrings', ['id' => $ss->id]);
    $this->assertDatabaseMissing('p4_anpassungsprotokoll', ['id' => $ap->id]);
});

// ─── Authorization ───────────────────────────────────────────

test('P4: fremder user bekommt 403', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->assertStatus(403);
});

test('P4: cancel setzt form zurück', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newSs')
        ->assertSet('showSsForm', true)
        ->set('ss.datenbank', 'Test')
        ->call('cancelSs')
        ->assertSet('showSsForm', false)
        ->assertSet('ss.datenbank', '');
});
