<?php

use Jmal\Hris\Enums\CivilStatus;
use Jmal\Hris\Enums\EmploymentStatus;
use Jmal\Hris\Enums\Gender;
use Jmal\Hris\Enums\PayFrequency;
use Jmal\Hris\Enums\PayoutMethod;
use Jmal\Hris\Enums\SplitType;

test('EmploymentStatus has all expected values', function () {
    expect(EmploymentStatus::cases())->toHaveCount(5);
    expect(EmploymentStatus::Regular->value)->toBe('regular');
    expect(EmploymentStatus::Probationary->value)->toBe('probationary');
    expect(EmploymentStatus::Contractual->value)->toBe('contractual');
    expect(EmploymentStatus::PartTime->value)->toBe('part_time');
    expect(EmploymentStatus::Consultant->value)->toBe('consultant');
});

test('EmploymentStatus label returns human-readable text', function () {
    expect(EmploymentStatus::Regular->label())->toBe('Regular');
    expect(EmploymentStatus::PartTime->label())->toBe('Part-Time');
    expect(EmploymentStatus::Consultant->label())->toBe('Consultant');
});

test('CivilStatus has all expected values', function () {
    expect(CivilStatus::cases())->toHaveCount(5);
    expect(CivilStatus::SoloParent->value)->toBe('solo_parent');
    expect(CivilStatus::SoloParent->label())->toBe('Solo Parent');
});

test('Gender has all expected values', function () {
    expect(Gender::cases())->toHaveCount(2);
    expect(Gender::Male->label())->toBe('Male');
});

test('PayFrequency has all expected values', function () {
    expect(PayFrequency::cases())->toHaveCount(4);
    expect(PayFrequency::Weekly->value)->toBe('weekly');
    expect(PayFrequency::SemiMonthly->label())->toBe('Semi-Monthly');
});

test('PayoutMethod has all expected values', function () {
    expect(PayoutMethod::cases())->toHaveCount(5);
    expect(PayoutMethod::BankTransfer->value)->toBe('bank_transfer');
    expect(PayoutMethod::GCash->label())->toBe('GCash');
});

test('SplitType has all expected values', function () {
    expect(SplitType::cases())->toHaveCount(2);
    expect(SplitType::Percentage->value)->toBe('percentage');
    expect(SplitType::FixedAmount->label())->toBe('Fixed Amount');
});
