<?php

use App\Models\Bacteria;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('the welcome page shows the catalogued bacteria', function () {
    Bacteria::factory()->count(3)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('bacteriaCount', 3)
            ->has('featuredBacteria', 3));
});
