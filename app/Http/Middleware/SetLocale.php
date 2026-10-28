<?php

// app/Http/Middleware/SetLocale.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale(session('locale', config('app.locale')));
        View::share('lang', app()->getLocale());
        View::share('direction', config('app.directions')[app()->getLocale()]);

        return $next($request);
    }
}
