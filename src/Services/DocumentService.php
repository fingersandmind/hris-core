<?php

namespace Jmal\Hris\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Jmal\Hris\Events\DocumentDeleted;
use Jmal\Hris\Events\DocumentUploaded;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\EmployeeDocument;

class DocumentService
{
    /**
     * Upload and attach a document to an employee.
     */
    public function upload(Employee $employee, array $data, UploadedFile $file): EmployeeDocument
    {
        $path = $file->store("hris/documents/{$employee->id}", 'public');

        $document = EmployeeDocument::create(array_merge($data, [
            $employee->scopeColumn() => $employee->{$employee->scopeColumn()},
            'employee_id' => $employee->id,
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]));

        event(new DocumentUploaded($document));

        return $document;
    }

    /**
     * List all documents for an employee, optionally filtered by category.
     */
    public function listForEmployee(Employee $employee, ?string $category = null): Collection
    {
        return EmployeeDocument::forEmployee($employee)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Delete a document and remove file from storage.
     */
    public function delete(EmployeeDocument $document): void
    {
        $name = $document->name;
        $employee = $document->employee;

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        event(new DocumentDeleted($employee, $name));
    }

    /**
     * Get documents expiring within N days (for alerts).
     */
    public function getExpiringSoon(int $scopeId, int $daysAhead = 30): Collection
    {
        return EmployeeDocument::withoutGlobalScopes()
            ->where(EmployeeDocument::scopeColumn(), $scopeId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays($daysAhead))
            ->with('employee')
            ->orderBy('expiry_date')
            ->get();
    }
}
