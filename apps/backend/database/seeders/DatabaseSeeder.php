<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'SYSTEM_ADMIN' => ['users.manage', 'catalogs.manage', 'catalogs.read', 'audit.read'],
            'PRODUCTION_ENGINEER' => ['catalogs.read', 'production.write', 'production.read', 'events.write', 'engineering.read'],
            'PROCESS_ENGINEER' => ['catalogs.read', 'production.read', 'production.approve', 'imports.read', 'imports.approve', 'engineering.read', 'quality.read', 'quality.approve', 'events.approve', 'ai.read', 'ai.promote', 'reports.export'],
            'PLANNING_ENGINEER' => ['catalogs.read', 'planning.write', 'planning.read', 'events.write', 'engineering.read', 'ai.read'],
            'OPERATIONS_MANAGER' => ['catalogs.read', 'production.read', 'planning.read', 'planning.approve', 'events.read', 'engineering.read', 'quality.read', 'ai.read', 'ai.promote', 'reports.export', 'audit.read'],
            'ML_OPERATOR' => ['catalogs.read', 'ai.read', 'ai.train', 'dataset.write', 'dataset.read', 'production.read'],
            'DATA_STEWARD' => ['catalogs.read', 'imports.write', 'imports.read', 'dataset.write', 'dataset.read'],
            'LAB_ENGINEER' => ['catalogs.read', 'quality.write', 'quality.read'],
            'AUDITOR' => ['catalogs.read', 'audit.read', 'engineering.read'],
            'MANAGEMENT_VIEWER' => ['catalogs.read', 'production.read', 'planning.read', 'events.read', 'engineering.read', 'quality.read', 'ai.read'],
        ];
        foreach ($matrix as $code => $permissions) {
            DB::table('roles')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => $code, 'name_ar' => $code]);
            $role = DB::table('roles')->where('code', $code)->value('id');
            foreach ($permissions as $permission) {
                DB::table('permissions')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => $permission]);
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => DB::table('permissions')->where('code', $permission)->value('id')]);
            }
        }
        DB::table('facilities')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => 'ZAWIYA', 'name_ar' => 'مصفاة الزاوية', 'timezone_name' => 'Africa/Tripoli']);
        foreach ([['t', 'mass', 'طن'], ['kg', 'mass', 'كجم'], ['kWh', 'energy', 'kWh'], ['GJ', 'energy', 'GJ'], ['h', 'time', 'ساعة'], ['percent', 'ratio', '%'], ['L', 'volume', 'لتر']] as [$code,$dimension,$symbol]) {
            DB::table('measurement_units')->insertOrIgnore(['code' => $code, 'dimension' => $dimension, 'display_symbol' => $symbol]);
        }
        foreach (['LPG' => 'غاز النفط المسال', 'UNPROCESSED_NAPHTHA' => 'نافثا غير معالجة', 'GASOLINE' => 'جازولين', 'KEROSENE' => 'كيروسين', 'DIESEL' => 'ديزل', 'HEAVY_FUEL_OIL' => 'زيت وقود ثقيل'] as $code => $name) {
            DB::table('product_catalog')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => $code, 'name_ar' => $name, 'stage' => 'pending_review']);
        }
        foreach (['SHARARA' => 'الشرارة', 'HAMADA' => 'الحمادة'] as $code => $name) {
            DB::table('crude_catalog')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => $code, 'name_ar' => $name]);
        }
    }
}
