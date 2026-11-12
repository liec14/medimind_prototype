<?php

use App\Models\CatalogMedicine;
use App\Models\PharmacyStock;
use App\Models\User;
use App\Services\PharmacyComparisonService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->pharmacyUser = User::factory()->pharmacy()->create();
    $this->pharmacy = $this->pharmacyUser->pharmacy;
    $this->medicine = CatalogMedicine::factory()->create(['generic_name' => 'Testamol']);
});

// --- pharmacy side -------------------------------------------------------------

it('lets a pharmacy add, update and remove items on its price list', function () {
    $this->actingAs($this->pharmacyUser)->get(route('pharmacy.prices'))->assertOk()->assertSee('Testamol');

    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.stock.store'), [
        'catalog_medicine_id' => $this->medicine->id, 'price' => '4.50', 'in_stock' => '1',
    ])->assertRedirect();
    $stock = $this->pharmacy->stock()->first();
    expect($stock->price)->toBe('4.50')->and($stock->in_stock)->toBeTrue();

    $this->actingAs($this->pharmacyUser)->put(route('pharmacy.stock.update', $stock), ['price' => '5.25'])->assertRedirect();
    expect($stock->fresh())->price->toBe('5.25')->in_stock->toBeFalse(); // unchecked box = out of stock

    $this->actingAs($this->pharmacyUser)->delete(route('pharmacy.stock.destroy', $stock))->assertRedirect();
    expect($this->pharmacy->stock()->count())->toBe(0);
});

it('updates the existing row instead of duplicating when a medicine is added twice', function () {
    foreach (['4.00', '6.00'] as $price) {
        $this->actingAs($this->pharmacyUser)->post(route('pharmacy.stock.store'), ['catalog_medicine_id' => $this->medicine->id, 'price' => $price, 'in_stock' => '1']);
    }

    expect($this->pharmacy->stock()->count())->toBe(1)->and($this->pharmacy->stock()->first()->price)->toBe('6.00');
});

it('refreshes the "updated" time when prices are re-confirmed', function () {
    $stock = PharmacyStock::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'catalog_medicine_id' => $this->medicine->id, 'price' => 4]);
    $stock->forceFill(['updated_at' => now()->subDays(40)])->saveQuietly();
    expect($stock->fresh()->isStale())->toBeTrue();

    $this->actingAs($this->pharmacyUser)->put(route('pharmacy.stock.update', $stock), ['price' => '4.00', 'in_stock' => '1']);

    expect($stock->fresh()->isStale())->toBeFalse();
});

it('validates prices', function (mixed $price) {
    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.stock.store'), ['catalog_medicine_id' => $this->medicine->id, 'price' => $price])
        ->assertSessionHasErrors('price');
    expect(PharmacyStock::count())->toBe(0);
})->with(['-1', 'abc', '', '100000']);

it('rejects unknown medicines', function () {
    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.stock.store'), ['catalog_medicine_id' => 99999, 'price' => '1'])
        ->assertSessionHasErrors('catalog_medicine_id');
});

it('stops a pharmacy touching another pharmacy\'s prices', function () {
    $other = User::factory()->pharmacy()->create();
    $theirs = PharmacyStock::factory()->create(['pharmacy_id' => $other->pharmacy->id, 'catalog_medicine_id' => $this->medicine->id, 'price' => 9]);

    $this->actingAs($this->pharmacyUser)->put(route('pharmacy.stock.update', $theirs), ['price' => '1'])->assertForbidden();
    $this->actingAs($this->pharmacyUser)->delete(route('pharmacy.stock.destroy', $theirs))->assertForbidden();
    expect($theirs->fresh()->price)->toBe('9.00');
});

it('keeps the price list to pharmacies', function () {
    $patient = User::factory()->patient()->create();

    $this->actingAs($patient)->get(route('pharmacy.dashboard'))->assertForbidden();
    $this->actingAs($patient)->post(route('pharmacy.stock.store'), ['catalog_medicine_id' => $this->medicine->id, 'price' => '1'])->assertForbidden();
});

it('keeps unapproved pharmacies out of the system until approved', function () {
    $pending = User::factory()->pharmacy()->pending()->create();

    $this->actingAs($pending)->get(route('pharmacy.dashboard'))->assertRedirect(route('pending'));
});

// --- patient comparison --------------------------------------------------------

function listed(CatalogMedicine $medicine, string $price, bool $inStock = true, bool $approved = true): PharmacyStock
{
    $user = User::factory()->pharmacy()->create($approved ? [] : ['approved_at' => null]);

    return PharmacyStock::factory()->create([
        'pharmacy_id' => $user->pharmacy->id, 'catalog_medicine_id' => $medicine->id, 'price' => $price, 'in_stock' => $inStock,
    ]);
}

it('sorts in-stock pharmacies by price and puts out-of-stock ones last', function () {
    $mid = listed($this->medicine, '10.00');
    $cheapOut = listed($this->medicine, '2.00', inStock: false);
    $cheap = listed($this->medicine, '5.00');
    $dear = listed($this->medicine, '100.00');

    $rows = app(PharmacyComparisonService::class)->compare($this->medicine);

    expect($rows->pluck('id')->all())->toBe([$cheap->id, $mid->id, $dear->id, $cheapOut->id])
        ->and(app(PharmacyComparisonService::class)->cheapest($rows)->id)->toBe($cheap->id);
});

it('leaves out unapproved pharmacies and pharmacies that do not carry the medicine', function () {
    listed($this->medicine, '5.00');
    listed($this->medicine, '1.00', approved: false);
    listed(CatalogMedicine::factory()->create(), '1.00');

    expect(app(PharmacyComparisonService::class)->compare($this->medicine))->toHaveCount(1);
});

it('has no cheapest when everything is out of stock', function () {
    listed($this->medicine, '5.00', inStock: false);

    $service = app(PharmacyComparisonService::class);
    expect($service->cheapest($service->compare($this->medicine)))->toBeNull();
});

it('shows the comparison page with the lowest price, stock and stale warnings', function () {
    $patient = User::factory()->patient()->create();
    $cheap = listed($this->medicine, '5.00');
    $cheap->forceFill(['updated_at' => CarbonImmutable::now()->subDays(45)])->saveQuietly();
    listed($this->medicine, '8.50', inStock: false);

    $this->actingAs($patient)->get(route('patient.compare'))->assertOk()->assertSee('Choose a medicine')->assertDontSee('Lowest price');

    $this->actingAs($patient)->get(route('patient.compare', ['medicine_id' => $this->medicine->id]))
        ->assertOk()->assertSee('₱5.00')->assertSee('₱8.50')->assertSee('Lowest price')
        ->assertSee('Out of stock')->assertSee('may be out of date');
});

it('says so when no pharmacy lists the medicine, and rejects unknown ids', function () {
    $patient = User::factory()->patient()->create();

    $this->actingAs($patient)->get(route('patient.compare', ['medicine_id' => $this->medicine->id]))
        ->assertSee('No registered pharmacy has listed this medicine');
    $this->actingAs($patient)->get(route('patient.compare', ['medicine_id' => 99999]))->assertSessionHasErrors('medicine_id');
});

it('seeds two demo pharmacies with comparable prices', function () {
    $this->seed(DatabaseSeeder::class);

    $paracetamol = CatalogMedicine::where('generic_name', 'Paracetamol')->first();
    $rows = app(PharmacyComparisonService::class)->compare($paracetamol);
    expect($rows)->toHaveCount(2)->and($rows->first()->price)->toBeLessThan($rows->last()->price);

    $loratadine = CatalogMedicine::where('generic_name', 'Loratadine')->first();
    expect(app(PharmacyComparisonService::class)->compare($loratadine)->last()->in_stock)->toBeFalse();
});
