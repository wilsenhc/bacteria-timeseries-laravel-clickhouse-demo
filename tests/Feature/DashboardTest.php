<?php

use App\Models\User;

test('the dashboard page has been removed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertNotFound();
});
