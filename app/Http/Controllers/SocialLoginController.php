<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Socialite;
use Throwable;

class SocialLoginController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        return $this->driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        try {
            $social = $this->driver($provider)->user();
        } catch (Throwable $e) {
            // Cancelled login, expired state or a provider error.
            Log::info("{$provider} login failed: {$e->getMessage()}");

            return $this->fail('We could not sign you in. Please try again.');
        }

        $column = "{$provider}_id";
        $email = Str::lower((string) $social->getEmail());
        $verified = filter_var($social->getRaw()['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $user = User::where($column, $social->getId())->first();

        if (! $user && $email !== '') {
            $user = User::where('email', $email)->first();

            // Only link to an existing account when the provider has verified that the email belongs to this person.
            if ($user && ! $verified) {
                return $this->fail('Sign in with your password, then try again.');
            }

            $user?->forceFill([$column => $social->getId()]);
        }

        if (! $user) {
            if ($email === '') {
                return $this->fail('Your account did not share an email address, so we could not sign you in.');
            }

            // Apple sends the name on the first login only, and it is user input.
            $name = Str::limit(trim(strip_tags((string) $social->getName())), 255, '') ?: Str::before($email, '@');

            $user = User::create(['name' => $name, 'email' => $email]);
            $user->forceFill([$column => $social->getId()]);
            $isNew = true;
        }

        if ($verified && ! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()]);
        }

        $user->save();

        if ($isNew ?? false) {
            event(new Registered($user));
        }

        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function driver(string $provider): Provider
    {
        $driver = Socialite::driver($provider);

        // Apple posts the callback cross-site, so the session cookie is not sent; the provider carries the nonce in its own cookie instead.
        return $provider === 'apple' ? $driver->stateless()->cookieNonce() : $driver;
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['social' => $message]);
    }
}
