<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_observations', function (Blueprint $t) {
            $t->char('approved_scope_key', 64)->charset('ascii')->collation('ascii_bin')->nullable()->storedAs("CASE WHEN status = 'approved' THEN SHA2(CONCAT_WS('|',facility_id,period_month,metric_code,item_code,actual_or_target,report_status),256) ELSE NULL END");
            $t->unique('approved_scope_key', 'uq_monthly_approved_scope');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_observations', function (Blueprint $t) {
            $t->dropUnique('uq_monthly_approved_scope');
            $t->dropColumn('approved_scope_key');
        });
    }
};
