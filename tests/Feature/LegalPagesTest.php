<?php

test('impressum page can be rendered', function () {
    $response = $this->get(route('impressum'));

    $response->assertStatus(200);
    $response->assertSee('Benedikt Linn');
    $response->assertSee('Erzbergerstraße 9a');
});

test('dsgvo page can be rendered', function () {
    $response = $this->get(route('dsgvo'));

    $response->assertStatus(200);
    $response->assertSee('Datenschutz');
});

test('agb page can be rendered', function () {
    $response = $this->get(route('agb'));

    $response->assertStatus(200);
    $response->assertSee('Geltungsbereich');
});
