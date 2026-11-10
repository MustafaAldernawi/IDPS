<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function id(Blueprint $table, string $name = 'id'): ColumnDefinition
    {
        return $table->uuid($name)->charset('ascii')->collation('ascii_bin');
    }

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $this->id($t);
            $t->primary('id');
            $t->string('code', 64)->unique();
            $t->string('name_ar');
        });
        Schema::create('permissions', function (Blueprint $t) {
            $this->id($t);
            $t->primary('id');
            $t->string('code', 64)->unique();
        });
        Schema::create('role_permissions', function (Blueprint $t) {
            $this->id($t, 'role_id');
            $this->id($t, 'permission_id');
            $t->primary(['role_id', 'permission_id']);
            $t->foreign('role_id')->references('id')->on('roles');
            $t->foreign('permission_id')->references('id')->on('permissions');
        });
        Schema::create('facilities', function (Blueprint $t) {
            $this->id($t);
            $t->primary('id');
            $t->string('code', 64)->unique();
            $t->string('name_ar');
            $t->string('timezone_name')->default('Africa/Tripoli');
            $t->boolean('active')->default(true);
        });
        Schema::create('refinery_units', function (Blueprint $t) {
            $this->id($t);
            $t->primary('id');
            $this->id($t, 'facility_id');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->string('code', 64);
            $t->string('name_ar');
            $t->boolean('active')->default(true);
            $t->unique(['facility_id', 'code']);
        });
        foreach (['product_catalog', 'crude_catalog'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $this->id($t);
                $t->primary('id');
                $t->string('code', 64)->unique();
                $t->string('name_ar');
                if ($table === 'product_catalog') {
                    $t->string('stage', 40);
                } $t->boolean('active')->default(true);
            });
        }
        Schema::create('measurement_units', function (Blueprint $t) {
            $t->string('code', 64)->primary();
            $t->string('dimension', 32);
            $t->string('display_symbol', 32);
        });
        Schema::create('user_roles', function (Blueprint $t) {
            $this->id($t);
            $t->primary('id');
            foreach (['user_id', 'role_id', 'facility_id', 'unit_id'] as $column) {
                $this->id($t, $column)->nullable(in_array($column, ['facility_id', 'unit_id']));
            }$t->foreign('user_id')->references('id')->on('users');
            $t->foreign('role_id')->references('id')->on('roles');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->foreign('unit_id')->references('id')->on('refinery_units');
            $t->string('facility_scope_key', 36)->storedAs("COALESCE(facility_id,'GLOBAL')");
            $t->string('unit_scope_key', 36)->storedAs("COALESCE(unit_id,'GLOBAL')");
            $t->unique(['user_id', 'role_id', 'facility_scope_key', 'unit_scope_key'], 'uq_user_role_scope');
        });
        Schema::create('api_tokens', function (Blueprint $t) {
            $this->id($t);
            $t->primary('id');
            $this->id($t, 'user_id');
            $t->foreign('user_id')->references('id')->on('users');
            $t->char('token_hash', 64)->unique();
            $t->dateTime('expires_at', 6);
            $t->dateTime('created_at', 6);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $this->id($t);
            $t->primary('id');
            $this->id($t, 'actor_id')->nullable();
            $t->foreign('actor_id')->references('id')->on('users');
            $t->string('action', 64);
            $t->string('resource_type', 80);
            $t->string('resource_id', 64);
            $t->json('before_json')->nullable();
            $t->json('after_json')->nullable();
            $t->text('reason')->nullable();
            $t->string('request_id', 64);
            $t->dateTime('created_at', 6);
            $t->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'api_tokens', 'user_roles', 'measurement_units', 'crude_catalog', 'product_catalog', 'refinery_units', 'facilities', 'role_permissions', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
