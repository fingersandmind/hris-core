<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Jmal\Hris\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Unit');
