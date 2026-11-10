<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records that the signed-in user was active today. The session remembers it, so only the first
 * request of the day (per session) touches the database.
 */
class RecordActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('user'); // not the default guard: admin pages switch it to Admin

        if ($user && $request->hasSession()) {
            $key = $user->id.':'.today()->toDateString();

            if ($request->session()->get('activity_recorded') !== $key) {
                DB::table('user_activity_days')->insertOrIgnore(['user_id' => $user->id, 'date' => today()->toDateString()]);
                $request->session()->put('activity_recorded', $key);
            }
        }

        return $next($request);
    }
}
