<?php

namespace Jmal\Hris\Database\Seeders;

use Illuminate\Database\Seeder;
use Jmal\Hris\Models\LeaveType;

class HrisLeaveTypeSeeder extends Seeder
{
    /**
     * Seed PH default leave types.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'sil', 'name' => 'Service Incentive Leave', 'max_days_per_year' => 5, 'min_service_months' => 12],
            ['code' => 'vl', 'name' => 'Vacation Leave', 'max_days_per_year' => null],
            ['code' => 'sl', 'name' => 'Sick Leave', 'max_days_per_year' => null],
            ['code' => 'ml', 'name' => 'Maternity Leave', 'max_days_per_year' => 105, 'gender_restriction' => 'female'],
            ['code' => 'pl', 'name' => 'Paternity Leave', 'max_days_per_year' => 7, 'gender_restriction' => 'male'],
            ['code' => 'spl', 'name' => 'Solo Parent Leave', 'max_days_per_year' => 7],
            ['code' => 'vawc', 'name' => 'VAWC Leave', 'max_days_per_year' => 10, 'gender_restriction' => 'female'],
            ['code' => 'bl', 'name' => 'Bereavement Leave', 'max_days_per_year' => null],
            ['code' => 'el', 'name' => 'Emergency Leave', 'max_days_per_year' => null],
        ];

        foreach ($types as $type) {
            LeaveType::firstOrCreate(
                ['code' => $type['code'], 'branch_id' => null],
                array_merge([
                    'is_paid' => true,
                    'is_convertible' => false,
                    'requires_attachment' => false,
                    'gender_restriction' => null,
                    'min_service_months' => 0,
                    'is_active' => true,
                ], $type),
            );
        }
    }
}
