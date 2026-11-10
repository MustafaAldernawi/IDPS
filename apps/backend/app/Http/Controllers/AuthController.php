<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Governance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();
        abort_unless($user && $user->active && Hash::check($data['password'], $user->password), 401, 'Invalid credentials');
        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($request, $user, $token) {
            DB::table('api_tokens')->where('user_id', $user->id)->where('expires_at', '<', now())->delete();
            DB::table('api_tokens')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(8), 'created_at' => now()]);
            $request->setUserResolver(fn () => $user);
            Governance::audit($request, 'login', 'users', $user->id, null, null);
        });

        return response()->json(['token' => $token, 'user' => Governance::identity($user)])->header('Cache-Control', 'no-store');
    }

    public function me(Request $request)
    {
        return response()->json(['user' => Governance::identity($request->user())])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request)
    {
        DB::transaction(function () use ($request) {
            DB::table('api_tokens')->where('token_hash', hash('sha256', $request->bearerToken() ?? ''))->delete();
            Governance::audit($request, 'logout', 'users', $request->user()->id, null, null);
        });

        return response()->json(['data' => ['logged_out' => true]]);
    }
}
