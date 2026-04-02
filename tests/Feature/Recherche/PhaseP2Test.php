<?php

use App\Models\Recherche\{Projekt, P2ReviewTypEntscheidung, P2Cluster, P2MappingSuchstringKomponente, P2Trefferliste};
use App\Models\User;
use Livewire\Volt\Volt;

// ─── ReviewTypEntscheidung ───────────────────────────────────

test('P2: kann review-typ-entscheidung erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('newRev')
        ->set('revReviewTyp', 'systematic_review')
        ->set('revPasst', true)
        ->set('revBegruendung', 'Passt zur Fragestellung')
        ->call('saveRev')
        ->assertSet('showRevForm', false);

    $this->assertDatabaseHas('p2_review_typ_entscheidung', [
        'projekt_id' => $projekt->id,
        'review_typ' => 'systematic_review',
    ]);
});

test('P2: review-typ validiert review_typ als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('newRev')
        ->set('revReviewTyp', '')
        ->call('saveRev')
        ->assertHasErrors(['revReviewTyp']);
});

test('P2: kann review-typ bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $rev = P2ReviewTypEntscheidung::create([
        'projekt_id' => $projekt->id,
        'review_typ' => 'scoping_review',
        'passt' => false,
        'begruendung' => 'alt',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('editRev', $rev->id)
        ->assertSet('revReviewTyp', 'scoping_review')
        ->set('revBegruendung', 'Doch passend')
        ->call('saveRev');

    $this->assertDatabaseHas('p2_review_typ_entscheidung', ['id' => $rev->id, 'begruendung' => 'Doch passend']);
});

test('P2: kann review-typ löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $rev = P2ReviewTypEntscheidung::create([
        'projekt_id' => $projekt->id,
        'review_typ' => 'systematic_review',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('deleteRev', $rev->id);

    $this->assertDatabaseMissing('p2_review_typ_entscheidung', ['id' => $rev->id]);
});

// ─── Cluster ─────────────────────────────────────────────────

test('P2: kann cluster erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('newClu')
        ->set('cluClusterId', 'C1')
        ->set('cluLabel', 'Klinische Studien')
        ->set('cluBeschreibung', 'RCTs und quasi-experimentelle Studien')
        ->set('cluTrefferSchaetzung', 150)
        ->set('cluRelevanz', 'hoch')
        ->call('saveClu')
        ->assertSet('showCluForm', false);

    $this->assertDatabaseHas('p2_cluster', [
        'projekt_id' => $projekt->id,
        'cluster_label' => 'Klinische Studien',
        'treffer_schaetzung' => 150,
    ]);
});

test('P2: cluster validiert label als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('newClu')
        ->set('cluLabel', '')
        ->call('saveClu')
        ->assertHasErrors(['cluLabel']);
});

test('P2: kann cluster bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $clu = P2Cluster::create([
        'projekt_id' => $projekt->id,
        'cluster_id' => 'C1',
        'cluster_label' => 'Alt',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('editClu', $clu->id)
        ->assertSet('cluLabel', 'Alt')
        ->set('cluLabel', 'Neu')
        ->call('saveClu');

    $this->assertDatabaseHas('p2_cluster', ['id' => $clu->id, 'cluster_label' => 'Neu']);
});

test('P2: kann cluster löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $clu = P2Cluster::create([
        'projekt_id' => $projekt->id,
        'cluster_id' => 'C1',
        'cluster_label' => 'Test',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('deleteClu', $clu->id);

    $this->assertDatabaseMissing('p2_cluster', ['id' => $clu->id]);
});

// ─── MappingSuchstringKomponente ─────────────────────────────

test('P2: kann mapping erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('newMap')
        ->set('mapKomponenteLabel', 'Population')
        ->set('mapSuchbegriffe', 'Patients, Adults, Humans')
        ->set('mapSprache', 'en')
        ->set('mapTrunkierung', true)
        ->call('saveMap')
        ->assertSet('showMapForm', false);

    $this->assertDatabaseHas('p2_mapping_suchstring_komponenten', [
        'projekt_id' => $projekt->id,
        'komponente_label' => 'Population',
    ]);
});

test('P2: mapping suchbegriffe werden als array gespeichert', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('newMap')
        ->set('mapKomponenteLabel', 'Intervention')
        ->set('mapSuchbegriffe', 'Therapy, Treatment, Medication')
        ->call('saveMap');

    $map = P2MappingSuchstringKomponente::where('projekt_id', $projekt->id)->first();
    expect($map->suchbegriffe)->toBeArray()
        ->and($map->suchbegriffe)->toContain('Therapy')
        ->and($map->suchbegriffe)->toContain('Treatment');
});

test('P2: mapping validiert label als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('newMap')
        ->set('mapKomponenteLabel', '')
        ->call('saveMap')
        ->assertHasErrors(['mapKomponenteLabel']);
});

test('P2: kann mapping bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $map = P2MappingSuchstringKomponente::create([
        'projekt_id' => $projekt->id,
        'komponente_label' => 'Alt',
        'suchbegriffe' => ['Term1'],
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('editMap', $map->id)
        ->assertSet('mapKomponenteLabel', 'Alt')
        ->set('mapKomponenteLabel', 'Neu')
        ->call('saveMap');

    $this->assertDatabaseHas('p2_mapping_suchstring_komponenten', ['id' => $map->id, 'komponente_label' => 'Neu']);
});

test('P2: kann mapping löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $map = P2MappingSuchstringKomponente::create([
        'projekt_id' => $projekt->id,
        'komponente_label' => 'Test',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('deleteMap', $map->id);

    $this->assertDatabaseMissing('p2_mapping_suchstring_komponenten', ['id' => $map->id]);
});

// ─── Trefferliste ────────────────────────────────────────────

test('P2: kann trefferliste erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('newTref')
        ->set('trefDatenbank', 'PubMed')
        ->set('trefSuchstring', '(Population) AND (Intervention)')
        ->set('trefTrefferGesamt', 342)
        ->set('trefEinschaetzung', 'Gute Abdeckung')
        ->set('trefAnpassungNotwendig', false)
        ->set('trefSuchdatum', '2026-03-15')
        ->call('saveTref')
        ->assertSet('showTrefForm', false);

    $this->assertDatabaseHas('p2_trefferlisten', [
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'treffer_gesamt' => 342,
    ]);
});

test('P2: trefferliste validiert datenbank als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('newTref')
        ->set('trefDatenbank', '')
        ->call('saveTref')
        ->assertHasErrors(['trefDatenbank']);
});

test('P2: kann trefferliste bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $tref = P2Trefferliste::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'treffer_gesamt' => 100,
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('editTref', $tref->id)
        ->assertSet('trefDatenbank', 'PubMed')
        ->set('trefTrefferGesamt', 200)
        ->call('saveTref');

    $this->assertDatabaseHas('p2_trefferlisten', ['id' => $tref->id, 'treffer_gesamt' => 200]);
});

test('P2: kann trefferliste löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $tref = P2Trefferliste::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'CINAHL',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->call('deleteTref', $tref->id);

    $this->assertDatabaseMissing('p2_trefferlisten', ['id' => $tref->id]);
});

// ─── Authorization ───────────────────────────────────────────

test('P2: fremder user bekommt 403', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    Volt::test('recherche.phase-p2', ['projekt' => $projekt])
        ->assertStatus(403);
});
