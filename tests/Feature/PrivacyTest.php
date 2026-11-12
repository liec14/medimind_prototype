<?php

use App\Models\User;

it('keeps roles out of each other\'s areas', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();

    $this->actingAs($patient)->get('/doctor')->assertForbidden();
    $this->actingAs($patient)->get('/admin/approvals')->assertForbidden();
    $this->actingAs($doctor)->get('/patient')->assertForbidden();
});

it('lets a linked doctor view the patient record', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();
    $patient->doctors()->attach($doctor->id, ['status' => 'active']);

    $this->actingAs($doctor)->get(route('doctor.patients.show', $patient))->assertOk();
});

it('blocks an unlinked doctor from a patient record', function () {
    $patient = User::factory()->patient()->create();
    $stranger = User::factory()->doctor()->create();

    $this->actingAs($stranger)->get(route('doctor.patients.show', $patient))->assertForbidden();
});

it('blocks a doctor whose link is still pending or declined', function (string $status) {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();
    $patient->doctors()->attach($doctor->id, ['status' => $status]);

    $this->actingAs($doctor)->get(route('doctor.patients.show', $patient))->assertForbidden();
})->with(['pending', 'declined']);

it('does not expose another doctor\'s patients or non-patient users', function () {
    $doctor = User::factory()->doctor()->create();
    $otherDoctor = User::factory()->doctor()->create();

    $this->actingAs($doctor)->get(route('doctor.patients.show', $otherDoctor))->assertForbidden();
});

it('blocks one patient from viewing another patient record', function () {
    [$a, $b] = User::factory()->patient()->count(2)->create();

    expect($a->can('viewRecord', $b))->toBeFalse()->and($a->can('viewRecord', $a))->toBeTrue();
});

it('runs the invite and accept flow', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();

    $this->actingAs($patient)->post('/patient/doctors', ['email' => $doctor->email])->assertSessionHasNoErrors();
    expect($patient->doctors()->first()->pivot->status)->toBe('pending');
    expect($patient->can('viewRecord', $patient))->toBeTrue();
    $this->actingAs($doctor)->get(route('doctor.patients.show', $patient))->assertForbidden();

    $this->actingAs($doctor)->patch(route('doctor.patients.respond', $patient), ['decision' => 'accept']);
    $this->actingAs($doctor)->get(route('doctor.patients.show', $patient))->assertOk();
});

it('cannot invite an unapproved doctor or a non-doctor', function () {
    $patient = User::factory()->patient()->create();
    $pending = User::factory()->doctor()->pending()->create();
    $other = User::factory()->patient()->create();

    $this->actingAs($patient)->post('/patient/doctors', ['email' => $pending->email])->assertSessionHasErrors('email');
    $this->actingAs($patient)->post('/patient/doctors', ['email' => $other->email])->assertSessionHasErrors('email');
});

it('lets a patient remove doctor access', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();
    $patient->doctors()->attach($doctor->id, ['status' => 'active']);

    $this->actingAs($patient)->delete(route('patient.doctors.destroy', $doctor));

    $this->actingAs($doctor)->get(route('doctor.patients.show', $patient))->assertForbidden();
});

it('shares limited info with a pharmacy only after consent', function () {
    $patient = User::factory()->patient()->create();
    $pharmacyUser = User::factory()->pharmacy()->create();
    $pharmacy = $pharmacyUser->pharmacy;

    expect($pharmacyUser->can('viewShared', $patient))->toBeFalse();

    $this->actingAs($patient)->post(route('patient.pharmacies.consent', $pharmacy));
    expect($pharmacyUser->fresh()->can('viewShared', $patient))->toBeTrue();
    expect($pharmacyUser->can('viewRecord', $patient))->toBeFalse(); // never the full record

    $this->actingAs($patient)->delete(route('patient.pharmacies.revoke', $pharmacy));
    expect($pharmacyUser->fresh()->can('viewShared', $patient))->toBeFalse();
});

it('lets an admin approve a doctor', function () {
    $admin = User::factory()->admin()->create();
    $doctor = User::factory()->doctor()->pending()->create();

    $this->actingAs($admin)->patch(route('admin.approvals.approve', $doctor));

    expect($doctor->fresh()->isApproved())->toBeTrue();
});

it('stops non-admins from approving accounts', function () {
    $doctor = User::factory()->doctor()->pending()->create();
    $patient = User::factory()->patient()->create();

    $this->actingAs($patient)->patch(route('admin.approvals.approve', $doctor))->assertForbidden();
});
