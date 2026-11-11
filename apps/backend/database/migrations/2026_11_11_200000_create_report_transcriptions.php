<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_transcriptions', function (Blueprint $table): void {
            $table->uuid('id')->charset('ascii')->collation('ascii_bin')->primary();
            foreach (['facility_id' => 'facilities', 'source_file_id' => 'source_files', 'created_by' => 'users', 'reviewed_by' => 'users'] as $column => $parent) {
                $table->uuid($column)->charset('ascii')->collation('ascii_bin')->nullable($column === 'reviewed_by');
                $table->foreign($column)->references('id')->on($parent);
            }
            $table->unsignedInteger('page');
            $table->string('locator', 160);
            $table->text('source_text');
            $table->string('status', 20)->default('pending');
            $table->text('review_reason')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('reviewed_at', 6)->nullable();
            $table->index(['facility_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_transcriptions');
    }
};
