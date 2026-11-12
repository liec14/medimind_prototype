<?php

use App\Models\CatalogMedicine;
use App\Models\PatientMedicine;
use App\Models\User;
use Database\Seeders\CatalogSeeder;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->patient = User::factory()->patient()->create();
});

it('searches the catalog by generic or brand name', function () {
    $this->actingAs($this->patient)->get(route('patient.info.index', ['q' => 'Biogesic']))
        ->assertOk()->assertSee('Paracetamol')->assertDontSee('Metformin');

    $this->actingAs($this->patient)->get(route('patient.info.index', ['q' => 'metform']))
        ->assertSee('Metformin')->assertDontSee('Paracetamol');

    $this->actingAs($this->patient)->get(route('patient.info.index', ['q' => 'zzzz']))
        ->assertSee('No medicines match');
});

it('filters the catalog by category', function () {
    $category = CatalogMedicine::where('generic_name', 'Metformin')->first()->category_id;

    $this->actingAs($this->patient)->get(route('patient.info.index', ['category_id' => $category]))
        ->assertSee('Metformin')->assertSee('Gliclazide')->assertDontSee('Paracetamol');
});

it('shows what a medicine is for, how to take it, and the warnings', function () {
    $m = CatalogMedicine::where('generic_name', 'Paracetamol')->first();

    $this->actingAs($this->patient)->get(route('patient.info.show', $m))
        ->assertOk()->assertSee($m->purpose)->assertSee('What to watch out for')->assertSee('liver')
        ->assertSee('Add to my medicines')->assertSee('Compare pharmacy prices');
});

it('warns about interactions with what the patient already takes and hides the add link when listed', function () {
    $ibuprofen = CatalogMedicine::where('generic_name', 'Ibuprofen')->first();
    $aspirin = CatalogMedicine::where('generic_name', 'Aspirin')->first();
    PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Aspirin', 'catalog_medicine_id' => $aspirin->id]);

    $this->actingAs($this->patient)->get(route('patient.info.show', $ibuprofen))
        ->assertSee('Possible interactions with what you take')->assertSee('Ibuprofen + Aspirin', false);

    $this->actingAs($this->patient)->get(route('patient.info.show', $aspirin))
        ->assertSee('already have this medicine')->assertDontSee('Add to my medicines');
});

it('prefills the add form from the info page', function () {
    $this->actingAs($this->patient)->get(route('patient.medicines.create', ['name' => 'Cetirizine']))
        ->assertOk()->assertSee('value="Cetirizine"', false);
});

it('keeps medicine info for patients only', function () {
    $pharmacy = User::factory()->pharmacy()->create();

    $this->get(route('patient.info.index'))->assertRedirect('/login');
    $this->actingAs($pharmacy)->get(route('patient.info.index'))->assertForbidden();
});
