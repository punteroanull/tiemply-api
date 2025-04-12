<?php

namespace Database\Seeders;

use App\Models\AbsenceType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AbsenceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create common absence types
        $types = [
            [
                'name' => 'Vacation',
                'code' => 'vacation',
                'description' => 'Paid time off for rest and leisure',
                'requires_approval' => true,
                'affects_vacation_balance' => true,
                'is_paid' => true,
            ],
            [
                'name' => 'Sick Leave',
                'code' => 'sick_leave',
                'description' => 'Time off due to illness or injury',
                'requires_approval' => false,
                'affects_vacation_balance' => false,
                'is_paid' => true,
            ],
            [
                'name' => 'Unpaid Leave',
                'code' => 'unpaid_leave',
                'description' => 'Time off without pay',
                'requires_approval' => true,
                'affects_vacation_balance' => false,
                'is_paid' => false,
            ],
            [
                'name' => 'Parental Leave',
                'code' => 'parental_leave',
                'description' => 'Time off for the birth or adoption of a child',
                'requires_approval' => true,
                'affects_vacation_balance' => false,
                'is_paid' => true,
            ],
            [
                'name' => 'Bereavement',
                'code' => 'bereavement',
                'description' => 'Time off due to a death in the family',
                'requires_approval' => true,
                'affects_vacation_balance' => false,
                'is_paid' => true,
            ],
            [
                'name' => 'Jury Duty',
                'code' => 'jury_duty',
                'description' => 'Time off to serve on a jury',
                'requires_approval' => true,
                'affects_vacation_balance' => false,
                'is_paid' => true,
            ],
            [
                'name' => 'Training',
                'code' => 'training',
                'description' => 'Time off for professional development',
                'requires_approval' => true,
                'affects_vacation_balance' => false,
                'is_paid' => true,
            ],
        ];

        foreach ($types as $type) {
            AbsenceType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
        
        $this->command->info('Absence types created successfully.');
    }
}
