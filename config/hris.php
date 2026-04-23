<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy / Branch Scoping
    |--------------------------------------------------------------------------
    */
    'scope' => [
        'column' => 'branch_id',
        'user_scope_attribute' => 'branch_id',
        'resolver' => \Jmal\Hris\Support\DefaultScopeResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */
    'authorization' => [
        'resolver' => \Jmal\Hris\Support\DefaultAuthorizationResolver::class,
        'user_role_attribute' => 'user_type',
        'roles' => [
            'manage_employees' => ['admin', 'partner'],
            'manage_attendance' => ['admin', 'partner'],
            'manage_leave' => ['admin', 'partner'],
            'manage_payroll' => ['admin', 'partner'],
            'approve_leave' => ['admin', 'partner'],
            'approve_loans' => ['admin', 'partner'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Host App User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'prefix' => 'hris',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payroll Configuration
    |--------------------------------------------------------------------------
    */
    'payroll' => [
        'default_pay_period' => 'semi_monthly',   // weekly | semi_monthly | monthly
        'semi_monthly_cutoffs' => [15, 'end'],     // 1-15, 16-end of month
        'monthly_cutoff_day' => 'end',             // day of month or 'end'
        'weekly_start_day' => 'monday',            // day of week the pay period starts
        'working_days_per_month' => 26,            // for daily rate calculation

        // Overtime rates (multiplied on top of hourly rate)
        'ot_regular_rate' => 0.25,                 // +25% on regular day
        'ot_restday_rate' => 0.30,                 // +30% on rest day/holiday

        // Night differential (10PM - 6AM)
        'night_diff_start' => '22:00',
        'night_diff_end' => '06:00',
        'night_diff_rate' => 0.10,                 // +10%

        // Holiday premiums (on top of daily rate)
        'regular_holiday_premium' => 1.00,         // 100% = double pay
        'special_holiday_premium' => 0.30,         // 30%

        // Overtime approval
        'require_ot_approval' => true,             // if false, use raw attendance OT

        // Tardiness deduction rules
        'tardiness' => [
            'grace_period_minutes' => 5,           // late < 5 min = no deduction
            'deduction_mode' => 'proportional',    // proportional | fixed | tiered
            'fixed_deduction_amount' => 50,        // used when mode = 'fixed'
            'tiered_brackets' => [                 // used when mode = 'tiered'
                ['min' => 6, 'max' => 15, 'deduction' => 50],
                ['min' => 16, 'max' => 30, 'deduction' => 100],
                ['min' => 31, 'max' => 60, 'deduction' => 200],
                ['min' => 61, 'max' => null, 'deduction' => 'half_day'],
            ],
        ],

        // Undertime deduction rules
        'undertime' => [
            'deduction_mode' => 'proportional',    // proportional | none
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Leave Configuration
    |--------------------------------------------------------------------------
    */
    'leave' => [
        'sil_days' => 5,                           // Service Incentive Leave
        'sil_eligibility_months' => 12,            // Must work 12 months
        'exclude_weekends_in_count' => true,        // Exclude Sat/Sun when counting leave days
    ],

    /*
    |--------------------------------------------------------------------------
    | Government Contribution Configuration
    |--------------------------------------------------------------------------
    */
    'contributions' => [
        // SSS
        'sss_table_year' => 2025,

        // PhilHealth (total rate split 50/50 employee/employer)
        'philhealth_rate' => 0.05,                 // 5% total
        'philhealth_floor' => 10000,               // minimum salary base
        'philhealth_ceiling' => 100000,            // maximum salary base

        // Pag-IBIG
        'pagibig_employee_rate_low' => 0.01,       // 1% if salary <= 1500
        'pagibig_employee_rate_high' => 0.02,      // 2% if salary > 1500
        'pagibig_employer_rate' => 0.02,           // 2% always
        'pagibig_salary_threshold' => 1500,
        'pagibig_min_employee' => 100,
        'pagibig_min_employer' => 100,
        'pagibig_max_employee' => 5000,            // max monthly contribution
    ],

];
