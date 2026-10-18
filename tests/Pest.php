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

// Tidak ada permintaan HTTP sungguhan ke lokal_pos dari test: semua harus lewat Http::fake().
beforeEach(fn () => \Illuminate\Support\Facades\Http::preventStrayRequests())->in('Feature');

uses(Tests\TestCase::class)->in('Unit');
