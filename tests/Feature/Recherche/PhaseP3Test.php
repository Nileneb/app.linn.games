<?php

use App\Models\Recherche\P3Datenbankmatrix;
use App\Models\Recherche\P3Disziplin;
use App\Models\Recherche\P3GeografischerFilter;
use App\Models\Recherche\P3GraueLiteratur;
use App\Models\Recherche\Projekt;
use App\Models\User;
use Livewire\Volt\Volt;

// ─── Datenbankmatrix ─────────────────────────────────────────

test('P3: kann datenbankmatrix erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('newDb')
        ->set('dbDatenbank', 'PubMed')
        ->set('dbDisziplin', 'Medizin')
        ->set('dbAbdeckung', 'Umfassend für biomedizinische Literatur')
        ->set('dbZugang', 'frei')
        ->set('dbEmpfohlen', true)
        ->call('saveDb')
        ->assertSet('showDbForm', false);

    $this->assertDatabaseHas('p3_datenbankmatrix', [
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'zugang' => 'frei',
    ]);
});

test('P3: datenbankmatrix validiert datenbank als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('newDb')
        ->set('dbDatenbank', '')
        ->call('saveDb')
        ->assertHasErrors(['dbDatenbank']);
});

test('P3: kann datenbankmatrix bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $db = P3Datenbankmatrix::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'zugang' => 'frei',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('editDb', $db->id)
        ->assertSet('dbDatenbank', 'PubMed')
        ->set('dbDatenbank', 'CINAHL')
        ->set('dbZugang', 'kostenpflichtig')
        ->call('saveDb');

    $this->assertDatabaseHas('p3_datenbankmatrix', ['id' => $db->id, 'datenbank' => 'CINAHL', 'zugang' => 'kostenpflichtig']);
});

test('P3: kann datenbankmatrix löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $db = P3Datenbankmatrix::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'Cochrane',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('deleteDb', $db->id);

    $this->assertDatabaseMissing('p3_datenbankmatrix', ['id' => $db->id]);
});

// ─── Disziplin ───────────────────────────────────────────────

test('P3: kann disziplin erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('newDis')
        ->set('disDisziplin', 'Pflegewissenschaft')
        ->set('disArt', 'angrenzend')
        ->set('disRelevanz', 'hoch')
        ->call('saveDis')
        ->assertSet('showDisForm', false);

    $this->assertDatabaseHas('p3_disziplinen', [
        'projekt_id' => $projekt->id,
        'disziplin' => 'Pflegewissenschaft',
    ]);
});

test('P3: disziplin validiert disziplin als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('newDis')
        ->set('disDisziplin', '')
        ->call('saveDis')
        ->assertHasErrors(['disDisziplin']);
});

test('P3: kann disziplin bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $dis = P3Disziplin::create([
        'projekt_id' => $projekt->id,
        'disziplin' => 'Medizin',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('editDis', $dis->id)
        ->assertSet('disDisziplin', 'Medizin')
        ->set('disDisziplin', 'Psychologie')
        ->call('saveDis');

    $this->assertDatabaseHas('p3_disziplinen', ['id' => $dis->id, 'disziplin' => 'Psychologie']);
});

test('P3: kann disziplin löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $dis = P3Disziplin::create([
        'projekt_id' => $projekt->id,
        'disziplin' => 'Test',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('deleteDis', $dis->id);

    $this->assertDatabaseMissing('p3_disziplinen', ['id' => $dis->id]);
});

// ─── GeografischerFilter ─────────────────────────────────────

test('P3: kann geografischen filter erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('newGeo')
        ->set('geoRegion', 'Deutschland')
        ->set('geoFilterVorhanden', true)
        ->set('geoFiltername', 'Cochrane LMIC Filter')
        ->set('geoSensitivitaet', 95.5)
        ->call('saveGeo')
        ->assertSet('showGeoForm', false);

    $this->assertDatabaseHas('p3_geografische_filter', [
        'projekt_id' => $projekt->id,
        'region_land' => 'Deutschland',
        'validierter_filter_vorhanden' => true,
    ]);
});

test('P3: geo-filter validiert region als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('newGeo')
        ->set('geoRegion', '')
        ->call('saveGeo')
        ->assertHasErrors(['geoRegion']);
});

test('P3: kann geo-filter bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $geo = P3GeografischerFilter::create([
        'projekt_id' => $projekt->id,
        'region_land' => 'Deutschland',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('editGeo', $geo->id)
        ->assertSet('geoRegion', 'Deutschland')
        ->set('geoRegion', 'Österreich')
        ->call('saveGeo');

    $this->assertDatabaseHas('p3_geografische_filter', ['id' => $geo->id, 'region_land' => 'Österreich']);
});

test('P3: kann geo-filter löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $geo = P3GeografischerFilter::create([
        'projekt_id' => $projekt->id,
        'region_land' => 'Schweiz',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('deleteGeo', $geo->id);

    $this->assertDatabaseMissing('p3_geografische_filter', ['id' => $geo->id]);
});

// ─── GraueLiteratur ──────────────────────────────────────────

test('P3: kann graue literatur erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('newGrau')
        ->set('grauQuelle', 'WHO Global Index Medicus')
        ->set('grauTyp', 'Repository')
        ->set('grauUrl', 'https://www.globalindexmedicus.net/')
        ->set('grauRelevanz', 'hoch')
        ->call('saveGrau')
        ->assertSet('showGrauForm', false);

    $this->assertDatabaseHas('p3_graue_literatur', [
        'projekt_id' => $projekt->id,
        'quelle' => 'WHO Global Index Medicus',
    ]);
});

test('P3: graue literatur validiert quelle als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('newGrau')
        ->set('grauQuelle', '')
        ->call('saveGrau')
        ->assertHasErrors(['grauQuelle']);
});

test('P3: kann graue literatur bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $grau = P3GraueLiteratur::create([
        'projekt_id' => $projekt->id,
        'quelle' => 'OpenGrey',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('editGrau', $grau->id)
        ->assertSet('grauQuelle', 'OpenGrey')
        ->set('grauQuelle', 'DART-Europe')
        ->call('saveGrau');

    $this->assertDatabaseHas('p3_graue_literatur', ['id' => $grau->id, 'quelle' => 'DART-Europe']);
});

test('P3: kann graue literatur löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $grau = P3GraueLiteratur::create([
        'projekt_id' => $projekt->id,
        'quelle' => 'Test',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('deleteGrau', $grau->id);

    $this->assertDatabaseMissing('p3_graue_literatur', ['id' => $grau->id]);
});

// ─── Authorization ───────────────────────────────────────────

test('P3: fremder user bekommt 403', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->assertStatus(403);
});

test('P3: cancel setzt form zurück', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p3', ['projekt' => $projekt])
        ->call('newDb')
        ->assertSet('editingDbId', 'new')
        ->set('dbDatenbank', 'Test')
        ->call('cancelDb')
        ->assertSet('editingDbId', null)
        ->assertSet('dbDatenbank', '');
});
