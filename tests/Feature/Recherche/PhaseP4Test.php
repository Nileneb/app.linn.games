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
        ->set('ssDatenbank', 'PubMed')
        ->set('ssSuchstring', '(Population OR Patients) AND (Intervention)')
        ->set('ssVersion', 'v1.0')
        ->set('ssTrefferAnzahl', 512)
        ->set('ssSuchdatum', '2026-03-20')
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
        ->set('ssDatenbank', '')
        ->call('saveSs')
        ->assertHasErrors(['ssDatenbank']);
});

test('P4: suchstring filter werden als array gespeichert', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p4', ['projekt' => $projekt])
        ->call('newSs')
        ->set('ssDatenbank', 'CINAHL')
        ->set('ssSuchstring', 'test query')
        ->set('ssFilter', 'English, 2020-2026, Peer Reviewed')
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
        ->assertSet('ssDatenbank', 'PubMed')
        ->set('ssDatenbank', 'Embase')
        ->set('ssVersion', 'v2.0')
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
        ->set('thFreitextDe', 'Bluthochdruck')
        ->set('thFreitextEn', 'Hypertension')
        ->set('thMesh', 'Hypertension')
        ->set('thEmtree', 'hypertension')
        ->set('thPsycinfo', '')
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
        ->set('thFreitextDe', '')
        ->call('saveTh')
        ->assertHasErrors(['thFreitextDe']);
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
        ->assertSet('thFreitextDe', 'Alt')
        ->set('thFreitextDe', 'Neu')
        ->set('thMesh', 'NewMeSH')
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
        ->assertSet('apSuchstringId', $ss->id)
        ->set('apVersion', 'v1.1')
        ->set('apDatum', '2026-03-25')
        ->set('apAenderung', 'MeSH-Term hinzugefügt')
        ->set('apGrund', 'Zu wenig Treffer')
        ->set('apTrefferVorher', 50)
        ->set('apTrefferNachher', 120)
        ->set('apEntscheidung', 'Übernommen')
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
        ->set('apSuchstringId', '')
        ->set('apAenderung', '')
        ->call('saveAp')
        ->assertHasErrors(['apSuchstringId', 'apAenderung']);
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
        ->set('apAenderung', 'Hack-Versuch')
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
        ->assertSet('apAenderung', 'Original')
        ->set('apAenderung', 'Geändert')
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
        ->set('ssDatenbank', 'Test')
        ->call('cancelSs')
        ->assertSet('showSsForm', false)
        ->assertSet('ssDatenbank', '');
});
