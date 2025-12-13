<?php

use App\Models\User;

test('admin users can access the filament panel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful();
});

test('viewer users cannot access the filament panel', function () {
    $viewer = User::factory()->viewer()->create();

    $this->actingAs($viewer)
        ->get('/admin')
        ->assertForbidden();
});

test('viewer users can access the records page', function () {
    $viewer = User::factory()->viewer()->create();

    $this->actingAs($viewer)
        ->get('/records')
        ->assertSuccessful();
});

test('user model correctly identifies admin status', function () {
    $admin = User::factory()->admin()->create();
    $viewer = User::factory()->viewer()->create();

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isViewer())->toBeFalse();

    expect($viewer->isAdmin())->toBeFalse();
    expect($viewer->isViewer())->toBeTrue();
});
