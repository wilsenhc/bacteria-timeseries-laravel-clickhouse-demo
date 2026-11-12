<?php

use App\Models\Bacteria;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected to the login page', function () {
    $this->get(route('bacteria.index'))->assertRedirect(route('login'));
});

test('authenticated users can list bacteria', function () {
    $this->actingAs(User::factory()->create());

    Bacteria::factory()->create(['name' => 'Salmonella']);

    $this->get(route('bacteria.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bacteria/Index')
            ->has('bacteria', 1)
            ->where('bacteria.0.name', 'Salmonella'));
});

test('authenticated users can view a bacterium', function () {
    $this->actingAs(User::factory()->create());

    $bacteria = Bacteria::factory()->create(['name' => 'Salmonella']);

    $this->get(route('bacteria.show', $bacteria))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bacteria/Show')
            ->where('bacteria.name', 'Salmonella'));
});
