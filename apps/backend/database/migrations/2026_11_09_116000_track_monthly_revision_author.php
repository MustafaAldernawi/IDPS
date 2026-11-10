<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_observations', function (Blueprint $t) {
            $t->uuid('created_by')->charset('ascii')->collation('ascii_bin')->nullable();
            $t->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_observations', function (Blueprint $t) {
            $t->dropForeign(['created_by']);
            $t->dropColumn('created_by');
        });
    }
};
