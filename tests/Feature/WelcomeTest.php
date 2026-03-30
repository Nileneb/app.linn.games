<?php

use App\Models\PageView;
use App\Models\User;

test('welcome page returns successful response', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(200);
});

test('welcome page increments visit counter', function () {
    $this->get(route('home'));

    $this->assertDatabaseHas('page_views', ['visits' => 1]);

    $this->get(route('home'));

    $this->assertDatabaseHas('page_views', ['visits' => 2]);
});

test('welcome page creates page_view record if none exists', function () {
    expect(PageView::count())->toBe(0);

    $this->get(route('home'));

    expect(PageView::count())->toBe(1);
    expect(PageView::first()->visits)->toBe(1);
});

test('guest sees login and register links', function () {
    $response = $this->get(route('home'));

    $response->assertSee('Login');
    $response->assertSee('Registrieren');
});

test('authenticated user sees dashboard link', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertSee('Dashboard');
});
