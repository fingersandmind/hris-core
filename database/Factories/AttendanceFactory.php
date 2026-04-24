<?php

namespace Jmal\Hris\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Jmal\Hris\Models\Attendance;
use Jmal\Hris\Models\Employee;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');
        $clockIn = (clone $date)->setTime(8, 0);
        $clockOut = (clone $date)->setTime(17, 0);

        return [
            'branch_id' => 1,
            'employee_id' => Employee::factory(),
            'date' => $date->format('Y-m-d'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'hours_worked' => 8.0,
            'status' => 'present',
        ];
    }

    public function withOvertime(float $hours = 2): static
    {
        return $this->state(function (array $attributes) use ($hours) {
            $clockOut = Carbon::parse($attributes['clock_out'])->addHours((int) $hours);

            return [
                'clock_out' => $clockOut,
                'hours_worked' => 8 + $hours,
                'overtime_hours' => $hours,
            ];
        });
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'clock_in' => null,
            'clock_out' => null,
            'hours_worked' => 0,
            'status' => 'absent',
        ]);
    }

    public function restDay(): static
    {
        return $this->state(fn () => [
            'is_rest_day' => true,
            'status' => 'rest_day',
            'clock_in' => null,
            'clock_out' => null,
            'hours_worked' => 0,
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(function () use ($date) {
            return [
                'date' => $date,
                'clock_in' => $date.' 08:00:00',
                'clock_out' => $date.' 17:00:00',
            ];
        });
    }
}
