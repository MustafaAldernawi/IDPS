<?php

use App\Jobs\ReforecastEvent;
use App\Models\User;
use App\Services\Governance;
use App\Services\HistoricalImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('idps:bootstrap-local', function () {
    if (! app()->environment('local') || config('database.connections.mysql.database') !== 'idps_dev') {
        throw new RuntimeException('Local IDPS database only');
    }
    $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
    $root = base_path('../..');
    $path = $root.'/.local/accounts.json';
    $accounts = is_file($path) ? json_decode(file_get_contents($path), true) : [];
    foreach (DB::table('roles')->get() as $role) {
        $email = strtolower($role->code).'@idps.local';
        $user = User::where('email', $email)->first();
        if (! $user) {
            $password = bin2hex(random_bytes(12)).'!Aa1';
            $user = User::create(['name' => $role->name_ar, 'email' => $email, 'password' => $password]);
            $accounts[$role->code] = ['email' => $email, 'password' => $password];
        }
        DB::table('user_roles')->insertOrIgnore(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => $role->id]);
    }
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    file_put_contents($path, json_encode($accounts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $this->info('Local role accounts prepared; credentials saved only in git-ignored .local/accounts.json');
});

Artisan::command('idps:stage-history', function () {
    if (! app()->environment('local') || config('database.connections.mysql.database') !== 'idps_dev') {
        throw new RuntimeException('Local IDPS database only');
    }
    $root = base_path('../..');
    $manifest = json_decode(file_get_contents($root.'/data/raw-sources/SOURCE_MANIFEST.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($manifest as $source) {
        $path = $root.'/data/raw-sources/'.$source['alias'];
        if (! is_file($path) || hash_file('sha256', $path) !== $source['sha256'] || filesize($path) !== $source['bytes']) {
            throw new RuntimeException('Source manifest verification failed');
        }
    }
    $actor = User::where('email', 'data_steward@idps.local')->firstOrFail();
    $request = Request::create('/local/stage-history');
    $request->setUserResolver(fn () => $actor);
    Governance::authorize($request, 'imports.write');
    $facility = DB::table('facilities')->where('code', 'ZAWIYA')->value('id');
    foreach ($manifest as $source) {
        DB::table('source_files')->insertOrIgnore(['id' => (string) Str::uuid(), 'sha256' => $source['sha256'], 'original_name' => $source['alias'], 'storage_path' => $root.'/data/raw-sources/'.$source['alias'], 'bytes' => $source['bytes'], 'created_at' => now()]);
    }
    foreach ([2021, 2022, 2023] as $year) {
        $name = $year.'_actual_target.xlsx';
        $result = app(HistoricalImporter::class)->stage($request, $root.'/data/raw-sources/'.$name, $name, $facility, $year);
        $this->info($year.': '.$result['status'].'; replay='.($result['idempotent'] ? 'yes' : 'no'));
    }
    $this->info('All 12 source hashes verified; historical facts remain unapproved. No missing days or availability timestamps invented.');
});

Artisan::command('idps:process-outbox', function () {
    $count = 0;
    foreach (DB::table('operations_outbox')->whereNull('processed_at')->orderBy('created_at')->limit(100)->get() as $entry) {
        ReforecastEvent::dispatch($entry->id);
        $count++;
    }
    $this->info('Queued '.$count.' durable outbox entries; workers enforce idempotency.');
});
Schedule::command('idps:process-outbox')->everyMinute()->withoutOverlapping();
