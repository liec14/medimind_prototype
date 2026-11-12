<?php

use App\Models\CatalogMedicine;
use App\Models\OtcRule;
use App\Models\PatientMedicine;
use App\Models\User;
use App\Services\OtcRecommendationService;
use Database\Seeders\CatalogSeeder;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->patient = User::factory()->patient()->create();
    $this->otc = app(OtcRecommendationService::class);
});

function taking(User $patient, string $generic): void
{
    PatientMedicine::factory()->for($patient, 'patient')->create([
        'name' => $generic, 'catalog_medicine_id' => CatalogMedicine::where('generic_name', $generic)->value('id'),
    ]);
}

function names(array $result, string $key = 'suggestions'): array
{
    return collect($result[$key])->map(fn ($s) => $s['medicine']->generic_name)->all();
}

it('only ever suggests over-the-counter medicines', function () {
    expect(OtcRule::count())->toBeGreaterThan(15)
        ->and(OtcRule::with('catalogMedicine')->get()->every(fn ($r) => $r->catalogMedicine->is_otc))->toBeTrue()
        ->and($this->otc->symptoms())->toContain('Headache', 'Diarrhea', 'Fever');
});

it('suggests the first choice before the alternatives', function () {
    $result = $this->otc->recommend($this->patient, 'Headache', null);

    expect(names($result))->toBe(['Paracetamol', 'Ibuprofen'])
        ->and($result['matched'])->toBe(['Headache'])
        ->and($result['see_doctor_if'])->toHaveKey('Headache');
});

it('understands plain-language descriptions, including Filipino words', function () {
    expect(names($this->otc->recommend($this->patient, null, 'my head hurts')))->toContain('Paracetamol')
        ->and($this->otc->recommend($this->patient, null, 'my head hurts')['matched'])->toContain('Headache')
        ->and($this->otc->recommend($this->patient, null, 'sipon')['matched'])->toContain('Colds and flu')
        ->and($this->otc->recommend($this->patient, null, 'kabag')['matched'])->toBe(['Gas and bloating']);
});

it('combines a chosen complaint with free text and does not repeat a medicine', function () {
    $result = $this->otc->recommend($this->patient, 'Fever', 'headache');

    expect($result['matched'])->toContain('Fever', 'Headache')
        ->and(names($result))->toBe(collect(names($result))->unique()->values()->all())
        ->and(names($result))->toContain('Paracetamol', 'Ibuprofen');
});

it('keeps a moderate interaction in the list with a caution', function () {
    taking($this->patient, 'Losartan');

    $result = $this->otc->recommend($this->patient, 'Headache', null);
    $ibuprofen = collect($result['suggestions'])->firstWhere(fn ($s) => $s['medicine']->generic_name === 'Ibuprofen');

    expect($ibuprofen)->not->toBeNull()->and($ibuprofen['cautions'])->toHaveCount(1)
        ->and(names($result, 'excluded'))->toBeEmpty();
});

it('moves a medicine with a high-severity interaction to not suggested, with the reason', function () {
    taking($this->patient, 'Naproxen sodium');

    $result = $this->otc->recommend($this->patient, 'Headache', null);

    expect(names($result))->toBe(['Paracetamol'])
        ->and(names($result, 'excluded'))->toBe(['Ibuprofen'])
        ->and($result['excluded'][0]['reasons'][0]['medicines'])->toContain('Naproxen sodium');
});

it('tells the patient when they already take a suggested medicine', function () {
    taking($this->patient, 'Paracetamol');

    $paracetamol = collect($this->otc->recommend($this->patient, 'Fever', null)['suggestions'])
        ->firstWhere(fn ($s) => $s['medicine']->generic_name === 'Paracetamol');

    expect($paracetamol['already_taking'])->toBeTrue();
});

it('returns nothing for an unknown complaint', function () {
    $result = $this->otc->recommend($this->patient, null, 'qwertyuiop');

    expect($result['matched'])->toBeEmpty()->and($result['suggestions'])->toBeEmpty();
});

it('ignores very short words and blank input', function () {
    expect($this->otc->recommend($this->patient, null, 'a my is')['matched'])->toBeEmpty()
        ->and($this->otc->recommend($this->patient, '', '')['matched'])->toBeEmpty();
});

it('renders the OTC page with its disclaimer and results', function () {
    $this->actingAs($this->patient)->get(route('patient.otc'))
        ->assertOk()->assertSee('not a diagnosis')->assertDontSee('Suggestions for');

    $this->actingAs($this->patient)->get(route('patient.otc', ['symptom' => 'Diarrhea']))
        ->assertOk()->assertSee('Suggestions for Diarrhea')->assertSee('Oral rehydration salts')->assertSee('See a doctor if');

    $this->actingAs($this->patient)->get(route('patient.otc', ['q' => 'qwertyuiop']))
        ->assertOk()->assertSee('could not match');
});

it('shows excluded medicines on the page', function () {
    taking($this->patient, 'Naproxen sodium');

    $this->actingAs($this->patient)->get(route('patient.otc', ['symptom' => 'Headache']))
        ->assertOk()->assertSee('Not suggested for you')->assertSee('Ibuprofen + Naproxen sodium', false);
});

it('keeps OTC help for patients only', function () {
    $doctor = User::factory()->doctor()->create();

    $this->actingAs($doctor)->get(route('patient.otc'))->assertForbidden();
});
