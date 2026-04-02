<?php

use App\Models\Recherche\{Projekt, P1Strukturmodellwahl, P1Komponente, P1Kriterium, P1Warnsignal};
use App\Models\User;
use Livewire\Volt\Volt;

// ─── Strukturmodellwahl ──────────────────────────────────────

test('P1: kann strukturmodellwahl erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newSmw')
        ->assertSet('showSmwForm', true)
        ->set('smwModell', 'PICO')
        ->set('smwGewaehlt', true)
        ->set('smwBegruendung', 'Bewährt für klinische Fragen')
        ->call('saveSmw')
        ->assertSet('showSmwForm', false);

    $this->assertDatabaseHas('p1_strukturmodell_wahl', [
        'projekt_id' => $projekt->id,
        'modell' => 'PICO',
        'gewaehlt' => true,
    ]);
});

test('P1: strukturmodellwahl validiert modell als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newSmw')
        ->set('smwModell', '')
        ->call('saveSmw')
        ->assertHasErrors(['smwModell']);
});

test('P1: kann strukturmodellwahl bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $smw = P1Strukturmodellwahl::create([
        'projekt_id' => $projekt->id,
        'modell' => 'PICO',
        'gewaehlt' => false,
        'begruendung' => 'alt',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('editSmw', $smw->id)
        ->assertSet('showSmwForm', true)
        ->assertSet('smwModell', 'PICO')
        ->set('smwModell', 'SPIDER')
        ->set('smwBegruendung', 'Besser für qualitative Fragen')
        ->call('saveSmw')
        ->assertSet('showSmwForm', false);

    $this->assertDatabaseHas('p1_strukturmodell_wahl', [
        'id' => $smw->id,
        'modell' => 'SPIDER',
        'begruendung' => 'Besser für qualitative Fragen',
    ]);
});

test('P1: kann strukturmodellwahl löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $smw = P1Strukturmodellwahl::create([
        'projekt_id' => $projekt->id,
        'modell' => 'PICO',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('deleteSmw', $smw->id);

    $this->assertDatabaseMissing('p1_strukturmodell_wahl', ['id' => $smw->id]);
});

test('P1: fremder user bekommt 403', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->assertStatus(403);
});

// ─── Komponente ──────────────────────────────────────────────

test('P1: kann komponente erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newKomp')
        ->set('kompModell', 'PICO')
        ->set('kompKuerzel', 'P')
        ->set('kompLabel', 'Population')
        ->set('kompSynonyme', 'Patienten, Betroffene')
        ->set('kompBegriffDe', 'Patientengruppe')
        ->set('kompEnglisch', 'Population')
        ->call('saveKomp')
        ->assertSet('showKompForm', false);

    $this->assertDatabaseHas('p1_komponenten', [
        'projekt_id' => $projekt->id,
        'modell' => 'PICO',
        'komponente_label' => 'Population',
    ]);
});

test('P1: komponente synonyme werden als array gespeichert', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newKomp')
        ->set('kompModell', 'PICO')
        ->set('kompKuerzel', 'P')
        ->set('kompLabel', 'Population')
        ->set('kompSynonyme', 'Patienten, Betroffene, Erkrankte')
        ->call('saveKomp');

    $komp = P1Komponente::where('projekt_id', $projekt->id)->first();
    expect($komp->synonyme)->toBeArray()
        ->and($komp->synonyme)->toContain('Patienten')
        ->and($komp->synonyme)->toContain('Betroffene')
        ->and($komp->synonyme)->toContain('Erkrankte');
});

test('P1: komponente validiert label als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newKomp')
        ->set('kompLabel', '')
        ->call('saveKomp')
        ->assertHasErrors(['kompLabel']);
});

test('P1: kann komponente bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $komp = P1Komponente::create([
        'projekt_id' => $projekt->id,
        'modell' => 'PICO',
        'komponente_kuerzel' => 'P',
        'komponente_label' => 'Population',
        'synonyme' => ['Patienten'],
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('editKomp', $komp->id)
        ->assertSet('kompLabel', 'Population')
        ->set('kompLabel', 'Patientengruppe')
        ->call('saveKomp');

    $this->assertDatabaseHas('p1_komponenten', [
        'id' => $komp->id,
        'komponente_label' => 'Patientengruppe',
    ]);
});

test('P1: kann komponente löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $komp = P1Komponente::create([
        'projekt_id' => $projekt->id,
        'modell' => 'PICO',
        'komponente_kuerzel' => 'P',
        'komponente_label' => 'Population',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('deleteKomp', $komp->id);

    $this->assertDatabaseMissing('p1_komponenten', ['id' => $komp->id]);
});

// ─── Kriterium ───────────────────────────────────────────────

test('P1: kann kriterium erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newKrit')
        ->set('kritTyp', 'einschluss')
        ->set('kritBeschreibung', 'Erwachsene ab 18 Jahren')
        ->set('kritBegruendung', 'Zielgruppe definiert')
        ->call('saveKrit')
        ->assertSet('showKritForm', false);

    $this->assertDatabaseHas('p1_kriterien', [
        'projekt_id' => $projekt->id,
        'kriterium_typ' => 'einschluss',
        'beschreibung' => 'Erwachsene ab 18 Jahren',
    ]);
});

test('P1: kriterium validiert beschreibung als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newKrit')
        ->set('kritBeschreibung', '')
        ->call('saveKrit')
        ->assertHasErrors(['kritBeschreibung']);
});

test('P1: kann kriterium bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $krit = P1Kriterium::create([
        'projekt_id' => $projekt->id,
        'kriterium_typ' => 'einschluss',
        'beschreibung' => 'Original',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('editKrit', $krit->id)
        ->assertSet('kritBeschreibung', 'Original')
        ->set('kritBeschreibung', 'Geändert')
        ->call('saveKrit');

    $this->assertDatabaseHas('p1_kriterien', ['id' => $krit->id, 'beschreibung' => 'Geändert']);
});

test('P1: kann kriterium löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $krit = P1Kriterium::create([
        'projekt_id' => $projekt->id,
        'kriterium_typ' => 'ausschluss',
        'beschreibung' => 'Kinder unter 18',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('deleteKrit', $krit->id);

    $this->assertDatabaseMissing('p1_kriterien', ['id' => $krit->id]);
});

// ─── Warnsignal ──────────────────────────────────────────────

test('P1: kann warnsignal erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newWarn')
        ->set('warnLfdNr', 1)
        ->set('warnWarnsignal', 'Zu breite Suchstrategie')
        ->set('warnAuswirkung', 'Hohe Trefferzahl')
        ->set('warnHandlungsempfehlung', 'Suchstring eingrenzen')
        ->call('saveWarn')
        ->assertSet('showWarnForm', false);

    $this->assertDatabaseHas('p1_warnsignale', [
        'projekt_id' => $projekt->id,
        'warnsignal' => 'Zu breite Suchstrategie',
    ]);
});

test('P1: warnsignal validiert warnsignal als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newWarn')
        ->set('warnWarnsignal', '')
        ->call('saveWarn')
        ->assertHasErrors(['warnWarnsignal']);
});

test('P1: kann warnsignal bearbeiten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $warn = P1Warnsignal::create([
        'projekt_id' => $projekt->id,
        'lfd_nr' => 1,
        'warnsignal' => 'Alt',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('editWarn', $warn->id)
        ->assertSet('warnWarnsignal', 'Alt')
        ->set('warnWarnsignal', 'Neu')
        ->call('saveWarn');

    $this->assertDatabaseHas('p1_warnsignale', ['id' => $warn->id, 'warnsignal' => 'Neu']);
});

test('P1: kann warnsignal löschen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    $warn = P1Warnsignal::create([
        'projekt_id' => $projekt->id,
        'lfd_nr' => 1,
        'warnsignal' => 'Test',
    ]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('deleteWarn', $warn->id);

    $this->assertDatabaseMissing('p1_warnsignale', ['id' => $warn->id]);
});

test('P1: cancel setzt form zurück', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('newSmw')
        ->assertSet('showSmwForm', true)
        ->set('smwModell', 'TEST')
        ->call('cancelSmw')
        ->assertSet('showSmwForm', false)
        ->assertSet('smwModell', '');
});
