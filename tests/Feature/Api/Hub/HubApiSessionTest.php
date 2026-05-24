<?php

use App\Models\HubAppLoginLog;
use App\Models\User;
use App\Support\Hub\HubApiTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renews biometric hub sessions for 30 days on authenticated use', function () {
    $user = User::factory()->create();
    $issued = app(HubApiTokenManager::class)->issue($user, [
        'biometric_enabled' => true,
        'platform' => 'android',
    ]);

    $token = $issued['token'];
    $token->forceFill([
        'last_used_at' => now()->subDays(3),
        'expires_at' => now()->addHours(2),
    ])->save();

    $this->withHeader('Authorization', 'Bearer ' . $issued['plain_text_token'])
        ->getJson('/api/hub/v1/me')
        ->assertOk()
        ->assertJsonPath('session_policy.biometric_enabled', true);

    $token->refresh();

    expect($token->last_used_at?->gt(now()->subMinute()))->toBeTrue();
    expect($token->expires_at?->gt(now()->addDays(29)))->toBeTrue();
});

it('renews non-biometric hub sessions for 24 hours on authenticated use', function () {
    $user = User::factory()->create();
    $issued = app(HubApiTokenManager::class)->issue($user, [
        'biometric_enabled' => false,
        'platform' => 'android',
    ]);

    $token = $issued['token'];
    $token->forceFill([
        'last_used_at' => now()->subHours(5),
        'expires_at' => now()->addMinutes(30),
    ])->save();

    $this->withHeader('Authorization', 'Bearer ' . $issued['plain_text_token'])
        ->getJson('/api/hub/v1/me')
        ->assertOk()
        ->assertJsonPath('session_policy.biometric_enabled', false);

    $token->refresh();

    expect($token->last_used_at?->gt(now()->subMinute()))->toBeTrue();
    expect($token->expires_at?->gt(now()->addHours(23)))->toBeTrue();
    expect($token->expires_at?->lt(now()->addHours(25)))->toBeTrue();
});

it('rejects expired hub sessions with an accented message', function () {
    $user = User::factory()->create();
    $issued = app(HubApiTokenManager::class)->issue($user, [
        'biometric_enabled' => false,
        'platform' => 'android',
    ]);

    $issued['token']->forceFill([
        'expires_at' => now()->subMinute(),
    ])->save();

    $this->withHeader('Authorization', 'Bearer ' . $issued['plain_text_token'])
        ->getJson('/api/hub/v1/me')
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'Sessão inválida ou expirada.',
        ]);
});

it('records successful hub login attempts in the audit log', function () {
    $user = User::factory()->create([
        'email' => 'interno@ancora.test',
        'password_hash' => password_hash('senha-segura-123', PASSWORD_DEFAULT),
    ]);

    $this->postJson('/api/hub/v1/auth/login', [
        'email' => 'interno@ancora.test',
        'password' => 'senha-segura-123',
        'device_name' => 'Pixel 8',
        'platform' => 'android',
        'app_version' => '1.0.0',
        'biometric_enabled' => true,
    ])
        ->assertOk()
        ->assertJsonPath('session_policy.biometric_enabled', true)
        ->assertJsonPath('session_policy.inactive_expires_in_label', '30 dias');

    expect(HubAppLoginLog::query()->count())->toBe(1);

    $this->assertDatabaseHas('hub_app_login_logs', [
        'user_id' => $user->id,
        'platform' => 'android',
        'device_name' => 'Pixel 8',
        'app_version' => '1.0.0',
        'success' => 1,
    ]);
});
