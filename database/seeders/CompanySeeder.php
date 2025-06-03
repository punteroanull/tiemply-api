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
                // Configuración de geolocalización
                'geolocation_enabled' => true,
                'geolocation_required' => true,
                'geolocation_radius' => 100.0, // 100 metros de radio
                'office_latitude' => 40.4168, // Madrid centro (ejemplo)
                'office_longitude' => -3.7038,
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
                // Configuración de geolocalización opcional
                'geolocation_enabled' => true,
                'geolocation_required' => false,
                'geolocation_radius' => null, // Sin restricción de radio
                'office_latitude' => 41.3851, // Barcelona centro (ejemplo)
                'office_longitude' => 2.1734,
            ]
        );

        $this->command->info("Secondary company created with ID: {$secondCompany->id}");

                // Empresa con geolocalización opcional
        $thirdCompany = Company::updateOrCreate([
            'name' => 'FlexiWork Remote',
            'tax_id' => 'B87623421',
            'contact_email' => 'hr@flexiwork.com',
            'contact_person' => 'Sarah Director',
            'address' => 'Avenida Diagonal 456, 08008 Barcelona, España',
            'phone' => '+34 93 987 6543',
            'vacation_type' => 'calendar_days',
            'max_vacation_days' => 25,
            
            // Configuración de geolocalización opcional
            'geolocation_enabled' => true,
            'geolocation_required' => false,
            'geolocation_radius' => null, // Sin restricción de radio
            'office_latitude' => 41.3851, // Barcelona centro (ejemplo)
            'office_longitude' => 2.1734,
        ]);

        $this->command->info('FlexiWork Remote company created with ID: ' . $thirdCompany->id);

        

    }
}
