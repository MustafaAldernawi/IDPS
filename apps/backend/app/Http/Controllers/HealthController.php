<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    public function __invoke()
    {
        try {
            DB::select('SELECT 1');
            $database = true;
        } catch (\Throwable) {
            $database = false;
        }try {
            $ai = Http::timeout(2)->get(config('idps_ai.url', 'http://127.0.0.1:8001').'/health')->successful();
        } catch (\Throwable) {
            $ai = false;
        }

return response()->json(['data' => ['api' => 'up', 'database' => $database ? 'up' : 'down', 'ai' => $ai ? 'up' : 'unavailable', 'training' => 'asynchronous', 'timezone' => 'UTC', 'facility_timezone' => 'Africa/Tripoli']], $database ? 200 : 503);
    }
}
