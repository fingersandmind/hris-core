<?php

namespace Jmal\Hris\Services;

use Jmal\Hris\Models\Employee;
use Jmal\Hris\Support\ImportResult;

class EmployeeImportService
{
    private const REQUIRED_COLUMNS = ['first_name', 'last_name', 'department', 'position', 'basic_salary', 'date_hired'];

    private const OPTIONAL_COLUMNS = [
        'middle_name', 'suffix', 'employee_number', 'contact_number', 'email',
        'tin', 'sss_number', 'philhealth_number', 'pagibig_number',
        'pay_frequency', 'work_days_per_week', 'gender', 'civil_status',
        'birth_date', 'nationality', 'street', 'barangay', 'municipality', 'province', 'zip_code',
    ];

    public function __construct(
        protected EmployeeService $employeeService,
    ) {}

    /**
     * @param  callable(int): string|null  $employeeNumberGenerator  Optional function to generate employee numbers
     */
    public function importFromCsv(int $scopeId, string $filePath, ?callable $employeeNumberGenerator = null): ImportResult
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return new ImportResult(0, 0, [['row' => 0, 'field' => 'file', 'message' => 'Could not open file.']]);
        }

        // Read and clean header (handle BOM)
        $headerLine = fgets($handle);
        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
        $headers = array_map('trim', str_getcsv($headerLine));
        $headers = array_map('strtolower', $headers);

        // Validate required columns
        $missing = array_diff(self::REQUIRED_COLUMNS, $headers);
        if ($missing) {
            fclose($handle);

            return new ImportResult(0, 0, [
                ['row' => 0, 'field' => 'headers', 'message' => 'Missing required columns: '.implode(', ', $missing)],
            ]);
        }

        $validColumns = array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS);
        $errors = [];
        $created = 0;
        $skipped = 0;
        $row = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;

            // Skip empty rows
            if (count($line) === 1 && $line[0] === null) {
                continue;
            }

            $data = array_combine($headers, array_pad($line, count($headers), ''));
            if (! $data) {
                $errors[] = ['row' => $row, 'field' => 'row', 'message' => 'Column count does not match header.'];
                $skipped++;

                continue;
            }

            // Filter to valid columns only
            $data = array_intersect_key($data, array_flip($validColumns));

            // Validate required fields
            $rowErrors = $this->validateRow($data, $row, $scopeId);
            if ($rowErrors) {
                $errors = array_merge($errors, $rowErrors);
                $skipped++;

                continue;
            }

            // Clean data
            $data = array_map(fn ($v) => is_string($v) && $v === '' ? null : $v, $data);
            $data['basic_salary'] = (float) $data['basic_salary'];

            if (isset($data['work_days_per_week']) && $data['work_days_per_week'] !== null) {
                $data['work_days_per_week'] = (int) $data['work_days_per_week'];
            }

            try {
                if (empty($data['employee_number']) && $employeeNumberGenerator) {
                    $data['employee_number'] = $employeeNumberGenerator($scopeId);
                }

                $this->employeeService->create($scopeId, $data);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $row, 'field' => 'system', 'message' => $e->getMessage()];
                $skipped++;
            }
        }

        fclose($handle);

        return new ImportResult($created, $skipped, $errors);
    }

    /**
     * @return array<int, array{row: int, field: string, message: string}>
     */
    protected function validateRow(array $data, int $row, int $scopeId): array
    {
        $errors = [];

        foreach (self::REQUIRED_COLUMNS as $col) {
            if (empty($data[$col])) {
                $errors[] = ['row' => $row, 'field' => $col, 'message' => "{$col} is required."];
            }
        }

        if (! empty($data['basic_salary']) && ! is_numeric($data['basic_salary'])) {
            $errors[] = ['row' => $row, 'field' => 'basic_salary', 'message' => 'basic_salary must be a number.'];
        }

        if (! empty($data['date_hired']) && ! strtotime($data['date_hired'])) {
            $errors[] = ['row' => $row, 'field' => 'date_hired', 'message' => 'date_hired is not a valid date.'];
        }

        if (! empty($data['employee_number'])) {
            $exists = Employee::withoutGlobalScopes()
                ->where('branch_id', $scopeId)
                ->where('employee_number', $data['employee_number'])
                ->exists();

            if ($exists) {
                $errors[] = ['row' => $row, 'field' => 'employee_number', 'message' => "employee_number '{$data['employee_number']}' already exists."];
            }
        }

        return $errors;
    }

    /**
     * Return a CSV header string for the import template.
     */
    public static function templateHeaders(): string
    {
        return implode(',', array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS));
    }
}
