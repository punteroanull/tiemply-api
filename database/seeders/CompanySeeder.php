<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a demo company
        $demoCompany = Company::updateOrCreate(
            ['name' => 'Tiemply Demo Company'],
            [
                'name' => 'Tiemply Demo Company',
                'tax_id' => 'B12345678',
                'contact_email' => 'info@tiemply-demo.com',
                'contact_person' => 'Company Contact',
                'address' => 'Demo Street 123, 28001 Madrid',
                'phone' => '900123456',
                'vacation_type' => 'business_days',
                'max_vacation_days' => 22,
            ]
        );

        $this->command->info("Demo company created with ID: {$demoCompany->id}");

        // Create a second company (optional)
        $secondCompany = Company::updateOrCreate(
            ['name' => 'Tiemply Secondary Co.'],
            [
                'name' => 'Tiemply Secondary Co.',
                'tax_id' => 'B87654321',
                'contact_email' => 'info@tiemply-secondary.com',
                'contact_person' => 'Secondary Contact',
                'address' => 'Secondary Avenue 456, 08001 Barcelona',
                'phone' => '900654321',
                'vacation_type' => 'calendar_days',
                'max_vacation_days' => 30,
            ]
        );

        $this->command->info("Secondary company created with ID: {$secondCompany->id}");
    }
}
