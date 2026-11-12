<?php

use App\Enums\Role;
use App\Models\Pharmacy;
use App\Models\User;

it('registers a patient who is approved immediately', function () {
    $this->post('/register', [
        'role' => 'patient', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com',
        'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
    ])->assertRedirect(route('dashboard'));

    $user = User::firstWhere('email', 'juan@example.com');
    expect($user->role)->toBe(Role::Patient)->and($user->isApproved())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('registers a doctor as pending and blocks the doctor area', function () {
    $this->post('/register', [
        'role' => 'doctor', 'name' => 'Dr. Who', 'email' => 'doc@example.com',
        'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'license_no' => 'PRC-1',
    ]);

    $doctor = User::firstWhere('email', 'doc@example.com');
    expect($doctor->isApproved())->toBeFalse();

    $this->actingAs($doctor)->get('/doctor')->assertRedirect(route('pending'));
});

it('creates a pharmacy profile when a pharmacy registers', function () {
    $this->post('/register', [
        'role' => 'pharmacy', 'name' => 'Pat Pharmacist', 'email' => 'ph@example.com',
        'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
        'license_no' => 'PRC-2', 'pharmacy_name' => 'Botica Bayan',
    ]);

    expect(Pharmacy::where('name', 'Botica Bayan')->exists())->toBeTrue();
});

it('does not allow self-registering as admin', function () {
    $this->post('/register', [
        'role' => 'admin', 'name' => 'Sneaky', 'email' => 'x@example.com',
        'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
    ])->assertSessionHasErrors('role');

    expect(User::where('email', 'x@example.com')->exists())->toBeFalse();
});

it('logs in and sends each role to its own dashboard', function (string $factoryState, string $route) {
    $user = User::factory()->{$factoryState}()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->get('/dashboard')->assertRedirect(route($route));
})->with([
    ['patient', 'patient.dashboard'],
    ['doctor', 'doctor.dashboard'],
    ['pharmacy', 'pharmacy.dashboard'],
    ['admin', 'admin.approvals'],
]);

it('rejects wrong passwords', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('requires login for app pages', function () {
    $this->get('/patient')->assertRedirect('/login');
});
