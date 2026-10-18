<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Semua test fitur memakai database in-memory (SQLite) yang di-refresh tiap test.
| Catatan: perhitungan DECIMAL sebaiknya juga diuji di MySQL sebelum rilis.
|
*/

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');
