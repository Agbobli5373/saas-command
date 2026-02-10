<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can switch locale and receive locale cookie', function () {
    $response = $this
        ->from(route('home'))
        ->post(route('locale.update'), [
            'locale' => 'de',
        ]);

    $response->assertRedirect(route('home'));
    $response->assertCookie('locale', 'de');
    $response->assertSessionHasNoErrors();

    $homeResponse = $this
        ->withCookie('locale', 'de')
        ->get(route('home'));

    $homeResponse->assertOk();
    $homeResponse->assertInertia(fn (Assert $page) => $page
        ->component('welcome')
        ->where('locale', 'de')
    );
});

test('invalid locale is rejected', function () {
    $response = $this
        ->from(route('home'))
        ->post(route('locale.update'), [
            'locale' => 'fr',
        ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasErrors('locale');
    $response->assertCookieMissing('locale');
});

test('authenticated locale switching updates stored user locale', function () {
    $user = User::factory()->create([
        'locale' => 'en',
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('home'))
        ->post(route('locale.update'), [
            'locale' => 'de',
        ]);

    $response->assertRedirect(route('home'));
    $response->assertCookie('locale', 'de');
    $response->assertSessionHasNoErrors();

    expect($user->fresh()->locale)->toBe('de');
});

test('subsequent requests resolve locale from stored user preference', function () {
    $user = User::factory()->create([
        'locale' => 'de',
    ]);

    $response = $this
        ->actingAs($user)
        ->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])
        ->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('welcome')
        ->where('locale', 'de')
    );
    $response->assertSessionHas('locale', 'de');
});
