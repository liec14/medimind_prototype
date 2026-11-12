<?php

use App\Models\TrustedContact;
use App\Models\User;

beforeEach(function () {
    $this->patient = User::factory()->patient()->create();
});

it('lets a patient add a trusted contact', function () {
    $this->actingAs($this->patient)->post(route('patient.contacts.store'), [
        'name' => 'Ana Cruz', 'email' => 'ana@example.com', 'relationship' => 'Daughter',
    ])->assertRedirect();

    $contact = $this->patient->trustedContacts()->sole();
    expect($contact)->name->toBe('Ana Cruz')->relationship->toBe('Daughter')->notify->toBeTrue();

    $this->actingAs($this->patient)->get(route('patient.contacts'))
        ->assertOk()->assertSee('ana@example.com')->assertSee('Only add people who agreed');
});

it('validates contacts and refuses duplicates', function () {
    $this->actingAs($this->patient)->post(route('patient.contacts.store'), ['name' => '', 'email' => 'nope'])
        ->assertSessionHasErrors(['name', 'email']);

    TrustedContact::factory()->create(['patient_id' => $this->patient->id, 'email' => 'ana@example.com']);
    $this->actingAs($this->patient)->post(route('patient.contacts.store'), ['name' => 'Ana', 'email' => 'ana@example.com'])
        ->assertSessionHasErrors('email');

    expect($this->patient->trustedContacts()->count())->toBe(1);
});

it('limits how many contacts a patient can add', function () {
    config(['medimind.emergency.max_contacts' => 2]);
    TrustedContact::factory()->count(2)->create(['patient_id' => $this->patient->id]);

    $this->actingAs($this->patient)->post(route('patient.contacts.store'), ['name' => 'Third', 'email' => 'third@example.com'])
        ->assertSessionHasErrors('email');

    expect($this->patient->trustedContacts()->count())->toBe(2);
});

it('lets a patient turn alerts on and off, and remove a contact', function () {
    $contact = TrustedContact::factory()->create(['patient_id' => $this->patient->id]);

    $this->actingAs($this->patient)->put(route('patient.contacts.update', $contact))->assertRedirect(); // box unchecked
    expect($contact->fresh()->notify)->toBeFalse();

    $this->actingAs($this->patient)->put(route('patient.contacts.update', $contact), ['notify' => '1']);
    expect($contact->fresh()->notify)->toBeTrue();

    $this->actingAs($this->patient)->delete(route('patient.contacts.destroy', $contact))->assertRedirect();
    expect(TrustedContact::count())->toBe(0);
});

it('keeps one patient\'s contacts away from other users', function () {
    $contact = TrustedContact::factory()->create(['patient_id' => $this->patient->id, 'email' => 'private@example.com']);
    $other = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();
    $this->patient->doctors()->attach($doctor->id, ['status' => 'active']);

    $this->actingAs($other)->put(route('patient.contacts.update', $contact), ['notify' => '1'])->assertForbidden();
    $this->actingAs($other)->delete(route('patient.contacts.destroy', $contact))->assertForbidden();
    $this->actingAs($other)->get(route('patient.contacts'))->assertDontSee('private@example.com');
    $this->actingAs($doctor)->get(route('patient.contacts'))->assertForbidden();
    expect(TrustedContact::count())->toBe(1);
});

it('lists the linked doctors who will also be told', function () {
    $doctor = User::factory()->doctor()->create(['name' => 'Dr. Reyes']);
    $this->patient->doctors()->attach($doctor->id, ['status' => 'active']);

    $this->actingAs($this->patient)->get(route('patient.contacts'))->assertSee('Dr. Reyes');
});
