<?php

namespace Jmal\Hris\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Jmal\Hris\Events\EmployeeCreated;
use Jmal\Hris\Events\EmployeeSeparated;
use Jmal\Hris\Events\EmployeeUpdated;
use Jmal\Hris\Models\Employee;

class EmployeeService
{
    /**
     * Create a new employee record.
     */
    public function create(int $scopeId, array $data): Employee
    {
        $scopeColumn = Employee::scopeColumn();
        $employee = Employee::create(array_merge($data, [$scopeColumn => $scopeId]));

        event(new EmployeeCreated($employee));

        return $employee;
    }

    /**
     * Update employee details.
     */
    public function update(Employee $employee, array $data): Employee
    {
        // Convert current attributes to plain values for comparison (enums → strings)
        $current = array_map(
            fn ($v) => $v instanceof \BackedEnum ? $v->value : $v,
            $employee->only(array_keys($data)),
        );
        $incoming = array_intersect_key($data, array_flip($employee->getFillable()));
        $changes = array_diff_assoc($incoming, $current);

        $employee->update($data);

        event(new EmployeeUpdated($employee->fresh(), $changes));

        return $employee->fresh();
    }

    /**
     * Deactivate (separate) an employee.
     */
    public function deactivate(Employee $employee, string $reason, ?Carbon $separationDate = null): Employee
    {
        $employee->update([
            'is_active' => false,
            'separation_reason' => $reason,
            'date_separated' => $separationDate ?? now(),
        ]);

        event(new EmployeeSeparated($employee, $reason));

        return $employee;
    }

    /**
     * Reactivate a separated employee.
     */
    public function reactivate(Employee $employee): Employee
    {
        $employee->update([
            'is_active' => true,
            'separation_reason' => null,
            'date_separated' => null,
        ]);

        return $employee;
    }

    /**
     * Find an employee by their employee number within a scope.
     */
    public function findByEmployeeNumber(int $scopeId, string $employeeNumber): ?Employee
    {
        return Employee::withoutGlobalScopes()
            ->where(Employee::scopeColumn(), $scopeId)
            ->where('employee_number', $employeeNumber)
            ->first();
    }

    /**
     * List active employees, optionally filtered by department.
     */
    public function listActive(int $scopeId, ?string $department = null): Collection
    {
        return Employee::withoutGlobalScopes()
            ->where(Employee::scopeColumn(), $scopeId)
            ->where('is_active', true)
            ->when($department, fn ($q) => $q->where('department', $department))
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Link an employee to a host app User.
     */
    public function linkToUser(Employee $employee, int $userId): Employee
    {
        $employee->update(['user_id' => $userId]);

        return $employee;
    }
}
