<?php

use App\Models\PatientMedicine;
use App\Models\PharmacyQuestion;
use App\Models\User;
use App\Notifications\CareAlertNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->pharmacyUser = User::factory()->pharmacy()->create();
    $this->pharmacy = $this->pharmacyUser->pharmacy;
    $this->patient = User::factory()->patient()->create(['name' => 'Lola Maria Cruz']);
});

function askPayload(array $overrides = []): array
{
    return array_merge(['question' => 'Can I take this with paracetamol?'], $overrides);
}

it('lets a patient ask a pharmacy a question', function () {
    $medicine = PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Losartan']);

    $this->actingAs($this->patient)->post(route('patient.ask.store'), askPayload(['pharmacy_id' => $this->pharmacy->id, 'patient_medicine_id' => $medicine->id]))
        ->assertRedirect()->assertSessionHas('status');

    expect(PharmacyQuestion::sole())->patient_id->toBe($this->patient->id)->pharmacy_id->toBe($this->pharmacy->id)
        ->patient_medicine_id->toBe($medicine->id)->answered_at->toBeNull();

    $this->actingAs($this->patient)->get(route('patient.ask'))
        ->assertOk()->assertSee('not for emergencies', false)->assertSee('Waiting for a reply')->assertSee('Can I take this with paracetamol?');
});

it('validates the question, the pharmacy, and the medicine', function () {
    $others = PatientMedicine::factory()->create();
    $pending = User::factory()->pharmacy()->pending()->create();

    $post = fn (array $data) => $this->actingAs($this->patient)->post(route('patient.ask.store'), $data);

    $post(['pharmacy_id' => $this->pharmacy->id, 'question' => 'short'])->assertSessionHasErrors('question');
    $post(askPayload(['pharmacy_id' => 9999]))->assertSessionHasErrors('pharmacy_id');
    $post(askPayload(['pharmacy_id' => $pending->pharmacy->id]))->assertSessionHasErrors('pharmacy_id');
    $post(askPayload(['pharmacy_id' => $this->pharmacy->id, 'patient_medicine_id' => $others->id]))->assertSessionHasErrors('patient_medicine_id');

    expect(PharmacyQuestion::count())->toBe(0);
});

it('shows the pharmacy the patient name, question and chosen medicine, and nothing else', function () {
    $medicine = PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Losartan', 'dosage' => '999 mg hidden']);
    PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Secretium']);
    PharmacyQuestion::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'patient_id' => $this->patient->id, 'patient_medicine_id' => $medicine->id]);

    $this->actingAs($this->pharmacyUser)->get(route('pharmacy.questions'))
        ->assertOk()->assertSee('Lola Maria Cruz')->assertSee('about Losartan')->assertSee('Can I take this medicine together with paracetamol?')
        ->assertDontSee('999 mg')->assertDontSee('Secretium')->assertDontSee($this->patient->email);
});

it('only shows a pharmacy its own questions', function () {
    $other = User::factory()->pharmacy()->create();
    PharmacyQuestion::factory()->create(['pharmacy_id' => $other->pharmacy->id, 'patient_id' => $this->patient->id, 'question' => 'Question for the other pharmacy only']);

    $this->actingAs($this->pharmacyUser)->get(route('pharmacy.questions'))->assertDontSee('Question for the other pharmacy only');
});

it('lets the pharmacy answer once and tells the patient', function () {
    Notification::fake();
    $question = PharmacyQuestion::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'patient_id' => $this->patient->id]);

    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.questions.answer', $question), ['answer' => 'Yes, that is usually fine.'])
        ->assertRedirect()->assertSessionHas('status');

    expect($question->fresh())->answer->toBe('Yes, that is usually fine.')->and($question->fresh()->isAnswered())->toBeTrue();
    Notification::assertSentTo($this->patient, CareAlertNotification::class, fn ($n) => str_contains($n->subject, $this->pharmacy->name) && $n->url === route('patient.ask'));

    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.questions.answer', $question), ['answer' => 'Changed my mind'])->assertNotFound();
    expect($question->fresh()->answer)->toBe('Yes, that is usually fine.');

    $this->actingAs($this->patient)->get(route('patient.ask'))->assertSee('Yes, that is usually fine.')->assertDontSee('Waiting for a reply');
});

it('validates the answer', function () {
    $question = PharmacyQuestion::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'patient_id' => $this->patient->id]);

    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.questions.answer', $question), ['answer' => ''])->assertSessionHasErrors('answer');
    expect($question->fresh()->isAnswered())->toBeFalse();
});

it('keeps other pharmacies, patients and doctors from answering', function () {
    $question = PharmacyQuestion::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'patient_id' => $this->patient->id]);
    $otherPharmacy = User::factory()->pharmacy()->create();
    $doctor = User::factory()->doctor()->create();

    foreach ([$otherPharmacy, $this->patient, $doctor] as $user) {
        $this->actingAs($user)->post(route('pharmacy.questions.answer', $question), ['answer' => 'Hijacked'])->assertForbidden();
    }

    expect($question->fresh()->isAnswered())->toBeFalse();
});

it('shows an unanswered count in the pharmacy navigation and overview', function () {
    PharmacyQuestion::factory()->count(2)->create(['pharmacy_id' => $this->pharmacy->id, 'patient_id' => $this->patient->id]);
    PharmacyQuestion::factory()->answered()->create(['pharmacy_id' => $this->pharmacy->id, 'patient_id' => $this->patient->id]);

    $this->actingAs($this->pharmacyUser)->get(route('pharmacy.dashboard'))
        ->assertSee('2 unanswered')->assertViewHas('openQuestions', 2);
});

it('keeps the ask page for patients', function () {
    $this->actingAs($this->pharmacyUser)->get(route('patient.ask'))->assertForbidden();
});
