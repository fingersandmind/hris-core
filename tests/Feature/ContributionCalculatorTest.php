<?php

use Jmal\Hris\Contracts\ContributionCalculatorInterface;
use Jmal\Hris\Contracts\TaxCalculatorInterface;
use Jmal\Hris\Database\Seeders\HrisSssContributionSeeder;
use Jmal\Hris\Database\Seeders\HrisTaxTableSeeder;
use Jmal\Hris\Services\BirTaxCalculator;
use Jmal\Hris\Services\PagIbigCalculator;
use Jmal\Hris\Services\PhilHealthCalculator;
use Jmal\Hris\Services\SssCalculator;

beforeEach(function () {
    (new HrisSssContributionSeeder)->run();
    (new HrisTaxTableSeeder)->run();
});

// --- SSS ---

test('SSS: correct bracket for minimum salary (4000)', function () {
    $calc = app('hris.sss');
    $result = $calc->calculate(4000, 2025);

    expect($result->employeeShare)->toBe(180.00)
        ->and($result->employerShare)->toBe(390.00)
        ->and($result->name)->toBe('sss');
});

test('SSS: correct bracket for 25000 salary', function () {
    $calc = app('hris.sss');
    $result = $calc->calculate(25000, 2025);

    expect($result->employeeShare)->toBe(1125.00)
        ->and($result->employerShare)->toBe(2385.00);
});

test('SSS: max bracket for salary above ceiling', function () {
    $calc = app('hris.sss');
    $result = $calc->calculate(50000, 2025);

    // Should use the highest bracket (30000)
    expect($result->employeeShare)->toBe(1350.00)
        ->and($result->employerShare)->toBe(2860.00);
});

test('SSS: returns zero when no brackets seeded for year', function () {
    $calc = app('hris.sss');
    $result = $calc->calculate(25000, 2099);

    expect($result->employeeShare)->toBe(0.0)
        ->and($result->employerShare)->toBe(0.0);
});

// --- PhilHealth ---

test('PhilHealth: 50/50 split for 25000 salary', function () {
    $calc = app('hris.philhealth');
    $result = $calc->calculate(25000, 2025);

    // 25000 * 0.05 = 1250, split = 625 each
    expect($result->employeeShare)->toBe(625.00)
        ->and($result->employerShare)->toBe(625.00)
        ->and($result->total)->toBe(1250.00)
        ->and($result->name)->toBe('philhealth');
});

test('PhilHealth: uses floor for salary below 10000', function () {
    $calc = app('hris.philhealth');
    $result = $calc->calculate(5000, 2025);

    // Floor is 10000, so base = 10000
    // 10000 * 0.05 = 500, split = 250 each
    expect($result->employeeShare)->toBe(250.00)
        ->and($result->employerShare)->toBe(250.00);
});

test('PhilHealth: uses ceiling for salary above 100000', function () {
    $calc = app('hris.philhealth');
    $result = $calc->calculate(200000, 2025);

    // Ceiling is 100000, so base = 100000
    // 100000 * 0.05 = 5000, split = 2500 each
    expect($result->employeeShare)->toBe(2500.00)
        ->and($result->employerShare)->toBe(2500.00);
});

// --- Pag-IBIG ---

test('PagIBIG: 1% employee rate for salary <= 1500', function () {
    $calc = app('hris.pagibig');
    $result = $calc->calculate(1500, 2025);

    // ee = max(100, 1500 * 0.01) = max(100, 15) = 100
    // er = max(100, 1500 * 0.02) = max(100, 30) = 100
    expect($result->employeeShare)->toBe(100.00)
        ->and($result->employerShare)->toBe(100.00)
        ->and($result->name)->toBe('pagibig');
});

test('PagIBIG: 2% employee rate for salary > 1500', function () {
    $calc = app('hris.pagibig');
    $result = $calc->calculate(25000, 2025);

    // ee = min(5000, max(100, 25000 * 0.02)) = min(5000, 500) = 500
    // er = max(100, 25000 * 0.02) = 500
    expect($result->employeeShare)->toBe(500.00)
        ->and($result->employerShare)->toBe(500.00);
});

test('PagIBIG: enforces minimum 100 contribution', function () {
    $calc = app('hris.pagibig');
    $result = $calc->calculate(3000, 2025);

    // ee = max(100, 3000 * 0.02) = max(100, 60) = 100
    // er = max(100, 3000 * 0.02) = max(100, 60) = 100
    expect($result->employeeShare)->toBe(100.00)
        ->and($result->employerShare)->toBe(100.00);
});

test('PagIBIG: enforces max 5000 employee contribution', function () {
    $calc = app('hris.pagibig');
    $result = $calc->calculate(300000, 2025);

    // ee = min(5000, max(100, 300000 * 0.02)) = min(5000, 6000) = 5000
    expect($result->employeeShare)->toBe(5000.00);
});

// --- BIR Tax ---

test('BIR: tax exempt below 20833/month', function () {
    $calc = app(TaxCalculatorInterface::class);

    expect($calc->calculate(20000, 'monthly', 2025))->toBe(0.0);
});

test('BIR: 15% bracket for 25000/month', function () {
    $calc = app(TaxCalculatorInterface::class);

    // Bracket: over 20833, rate 15%
    // tax = 0 + (25000 - 20833) * 0.15 = 4167 * 0.15 = 625.05
    expect($calc->calculate(25000, 'monthly', 2025))->toBe(625.05);
});

test('BIR: 20% bracket for 50000/month', function () {
    $calc = app(TaxCalculatorInterface::class);

    // Bracket: over 33333, fixed 1875, rate 20%
    // tax = 1875 + (50000 - 33333) * 0.20 = 1875 + 3333.40 = 5208.40
    expect($calc->calculate(50000, 'monthly', 2025))->toBe(5208.40);
});

test('BIR: 25% bracket for 100000/month', function () {
    $calc = app(TaxCalculatorInterface::class);

    // Bracket: over 66667, fixed 8541.80, rate 25%
    // tax = 8541.80 + (100000 - 66667) * 0.25 = 8541.80 + 8333.25 = 16875.05
    expect($calc->calculate(100000, 'monthly', 2025))->toBe(16875.05);
});

test('BIR: semi-monthly uses halved brackets', function () {
    $calc = app(TaxCalculatorInterface::class);

    // Semi-monthly exempt threshold is 10417
    expect($calc->calculate(10000, 'semi_monthly', 2025))->toBe(0.0);

    // 12500 semi-monthly, bracket: over 10417, rate 15%
    // tax = 0 + (12500 - 10417) * 0.15 = 2083 * 0.15 = 312.45
    expect($calc->calculate(12500, 'semi_monthly', 2025))->toBe(312.45);
});

test('BIR: weekly uses weekly brackets', function () {
    $calc = app(TaxCalculatorInterface::class);

    // Weekly exempt threshold is 4808
    expect($calc->calculate(4000, 'weekly', 2025))->toBe(0.0);

    // 6000 weekly, bracket: over 4808, rate 15%
    // tax = 0 + (6000 - 4808) * 0.15 = 1192 * 0.15 = 178.80
    expect($calc->calculate(6000, 'weekly', 2025))->toBe(178.80);
});

test('BIR: zero for negative taxable income', function () {
    $calc = app(TaxCalculatorInterface::class);

    expect($calc->calculate(-5000, 'monthly', 2025))->toBe(0.0);
});

test('BIR: highest bracket for 700000/month', function () {
    $calc = app(TaxCalculatorInterface::class);

    // Bracket: over 666667, fixed 183541.80, rate 35%
    // tax = 183541.80 + (700000 - 666667) * 0.35 = 183541.80 + 11666.55 = 195208.35
    expect($calc->calculate(700000, 'monthly', 2025))->toBe(195208.35);
});

// --- Interface compliance ---

test('all contribution calculators implement ContributionCalculatorInterface', function () {
    expect(app('hris.sss'))->toBeInstanceOf(ContributionCalculatorInterface::class);
    expect(app('hris.philhealth'))->toBeInstanceOf(ContributionCalculatorInterface::class);
    expect(app('hris.pagibig'))->toBeInstanceOf(ContributionCalculatorInterface::class);
});

test('BIR tax calculator implements TaxCalculatorInterface', function () {
    expect(app(TaxCalculatorInterface::class))->toBeInstanceOf(TaxCalculatorInterface::class);
});

test('tagged calculators are iterable', function () {
    $calculators = app()->tagged('hris.contribution_calculators');
    $names = [];
    foreach ($calculators as $calc) {
        $names[] = $calc->name();
    }

    expect($names)->toContain('sss', 'philhealth', 'pagibig');
});
