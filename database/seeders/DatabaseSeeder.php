<?php

namespace Database\Seeders;

use App\Models\CatalogMedicine;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo accounts for local development only. All use the password "password".
     */
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@medimind.test']);
        $doctor = User::factory()->doctor()->create(['name' => 'Dr. Ana Reyes', 'email' => 'doctor@medimind.test']);
        $patient = User::factory()->patient()->create(['name' => 'Lola Maria Cruz', 'email' => 'patient@medimind.test']);
        $patient->doctors()->attach($doctor->id, ['status' => 'active']);

        // Two pharmacies with slightly different sample prices, so comparison has something to show.
        // Prices are made-up demo figures, not real market prices.
        $pharmacies = [
            ['Mark Santos', 'pharmacy@medimind.test', 'Botica Santos', 1.00],
            ['Liza Rivera', 'pharmacy2@medimind.test', 'Rizal Drugstore', 1.15],
        ];
        $basePrices = [
            'Paracetamol' => 3.50, 'Ibuprofen' => 7.00, 'Cetirizine' => 9.00, 'Loratadine' => 12.00,
            'Carbocisteine' => 11.00, 'Ambroxol' => 10.00, 'Loperamide' => 8.00, 'Simethicone' => 6.50,
            'Oral rehydration salts' => 14.00, 'Vitamin C' => 5.00, 'Metformin' => 4.00, 'Amlodipine' => 6.00,
            'Losartan' => 9.00,
        ];

        foreach ($pharmacies as $i => [$owner, $email, $shopName, $factor]) {
            $user = User::factory()->pharmacy()->create(['name' => $owner, 'email' => $email]);
            $user->pharmacy->update(['name' => $shopName]);

            foreach ($basePrices as $generic => $price) {
                $medicine = CatalogMedicine::where('generic_name', $generic)->first();
                $user->pharmacy->stock()->create([
                    'catalog_medicine_id' => $medicine->id,
                    'price' => round($price * $factor, 2),
                    'in_stock' => ! ($i === 1 && $generic === 'Loratadine'), // one out-of-stock example
                ]);
            }

            if ($i === 0) {
                // The demo patient shares refill dates with the first pharmacy.
                $patient->consentedPharmacies()->attach($user->pharmacy->id);
            }
        }

        // Two medicines whose supply is running out, so the pharmacy refill list has something to show.
        foreach ([['Metformin', '500 mg, 1 tablet', 3], ['Amlodipine', '5 mg, 1 tablet', -4]] as [$generic, $dosage, $daysLeft]) {
            $catalog = CatalogMedicine::where('generic_name', $generic)->first();
            $patient->medicines()->create([
                'catalog_medicine_id' => $catalog->id,
                'name' => $generic,
                'dosage' => $dosage,
                'category_id' => $catalog->category_id,
                'purpose' => $catalog->purpose,
                'start_date' => now()->subDays(60)->toDateString(),
                'supply_until' => now()->addDays($daysLeft)->toDateString(),
                'entered_by' => $patient->id,
            ]);
        }
    }
}
