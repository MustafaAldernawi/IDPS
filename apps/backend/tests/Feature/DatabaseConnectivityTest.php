<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectivityTest extends TestCase
{
    public function test_dedicated_mysql_database_uses_strict_utf8mb4_and_preserves_decimal_null_and_utc(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('idps_test', DB::connection()->getDatabaseName());
        $settings = DB::selectOne('SELECT @@version AS version, @@sql_mode AS mode, @@character_set_connection AS charset, @@session.time_zone AS tz');
        $this->assertStringStartsWith('8.4.', $settings->version);
        $this->assertStringContainsString('STRICT_TRANS_TABLES', $settings->mode);
        $this->assertSame('utf8mb4', $settings->charset);
        $this->assertSame('+00:00', $settings->tz);
        DB::statement('CREATE TEMPORARY TABLE idps_precision_probe (quantity DECIMAL(20,6) NULL, occurred_at DATETIME(6)) ENGINE=InnoDB');
        try {
            DB::insert('INSERT INTO idps_precision_probe VALUES (?, ?), (?, ?)', ['12345678901234.123456', '2026-11-09 20:00:00.123456', null, '2026-11-09 20:00:00.000001']);
            $rows = DB::select('SELECT * FROM idps_precision_probe ORDER BY occurred_at DESC');
            $this->assertSame('12345678901234.123456', $rows[0]->quantity);
            $this->assertNull($rows[1]->quantity);
            $this->assertSame('2026-11-09 20:00:00.123456', $rows[0]->occurred_at);
        } finally {
            DB::statement('DROP TEMPORARY TABLE idps_precision_probe');
        }
    }
}
