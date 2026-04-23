<?php

namespace Jmal\Hris\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jmal\Hris\Models\Employee;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'branch_id' => 1,
            'employee_number' => 'EMP-'.$this->faker->unique()->numerify('####'),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional(0.7)->lastName(),
            'last_name' => $this->faker->lastName(),
            'suffix' => null,
            'birth_date' => $this->faker->dateTimeBetween('-50 years', '-18 years'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'civil_status' => $this->faker->randomElement(['single', 'married']),
            'nationality' => 'Filipino',
            'contact_number' => '09'.$this->faker->numerify('#########'),
            'department' => $this->faker->randomElement(['Engineering', 'Operations', 'Admin', 'Sales']),
            'position' => $this->faker->jobTitle(),
            'employment_status' => 'regular',
            'employment_type' => 'full_time',
            'date_hired' => $this->faker->dateTimeBetween('-5 years', '-1 month'),
            'basic_salary' => $this->faker->randomElement([15000, 18000, 20000, 25000, 30000, 35000]),
            'pay_frequency' => 'semi_monthly',
            'deduct_sss' => true,
            'deduct_philhealth' => true,
            'deduct_pagibig' => true,
            'deduct_tax' => true,
            'is_active' => true,
        ];
    }

    public function regular(): static
    {
        return $this->state(fn () => [
            'employment_status' => 'regular',
            'date_regularized' => $this->faker->dateTimeBetween('-4 years', '-6 months'),
        ]);
    }

    public function probationary(): static
    {
        return $this->state(fn () => [
            'employment_status' => 'probationary',
            'date_hired' => $this->faker->dateTimeBetween('-5 months', '-1 month'),
            'date_regularized' => null,
        ]);
    }

    public function contractual(): static
    {
        return $this->state(fn () => [
            'employment_status' => 'contractual',
        ]);
    }

    public function consultant(): static
    {
        return $this->state(fn () => [
            'employment_status' => 'consultant',
            'deduct_sss' => false,
            'deduct_philhealth' => false,
            'deduct_pagibig' => false,
            'deduct_tax' => false,
        ]);
    }

    public function separated(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'date_separated' => $this->faker->dateTimeBetween('-6 months', '-1 week'),
            'separation_reason' => $this->faker->randomElement(['resignation', 'end_of_contract', 'termination']),
        ]);
    }

    public function male(): static
    {
        return $this->state(fn () => [
            'gender' => 'male',
            'first_name' => $this->faker->firstNameMale(),
        ]);
    }

    public function female(): static
    {
        return $this->state(fn () => [
            'gender' => 'female',
            'first_name' => $this->faker->firstNameFemale(),
        ]);
    }

    public function withSalary(float $salary): static
    {
        return $this->state(fn () => [
            'basic_salary' => $salary,
        ]);
    }

    public function hiredOn(string $date): static
    {
        return $this->state(fn () => [
            'date_hired' => $date,
        ]);
    }
}
