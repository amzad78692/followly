<?php

namespace App\Http\Controllers\Api;

use App\Enums\DevicePlatform;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Watch\Pairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * How watches and the phone companion get their access tokens.
 */
class DeviceAuthController extends Controller
{
    /**
     * Start pairing a watch. The watch shows the code and polls with the secret.
     */
    public function start(Request $request, Pairing $pairing): array
    {
        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:60'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)->only([DevicePlatform::WearOs, DevicePlatform::AppleWatch])],
        ]);

        return $pairing->start($validated['device_name'], DevicePlatform::from($validated['platform']));
    }

    /**
     * Check whether the user approved the code. The token is returned only once.
     */
    public function poll(Request $request, Pairing $pairing): JsonResponse
    {
        $validated = $request->validate(['device_secret' => ['required', 'string', 'size:64']]);

        $result = $pairing->poll($validated['device_secret']);

        return match (true) {
            $result === false => response()->json(['status' => 'pending']),
            $result === null => response()->json(['status' => 'expired'], 410),
            default => response()->json(['status' => 'approved', 'token' => $result->plainTextToken]),
        };
    }

    /**
     * Sign in the phone companion.
     */
    public function login(Request $request): array
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:60'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return ['token' => $user->createToken($validated['device_name'], ['watch'])->plainTextToken];
    }

    /**
     * Sign this device out. Its push registration goes with the token.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
