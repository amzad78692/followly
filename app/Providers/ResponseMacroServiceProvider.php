<?php

namespace App\Providers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ResponseMacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Response::macro('success', function ($data = null, $total = null, $message = 'Success', $code = 200) {
            $fields = [
                'success' => true,
                'message' => $message,
                'data' => $data,
            ];

            if (isset($total)) {
                $fields['total'] = $total;
            }

            return response()->json($fields, $code);
        });

        Response::macro('error', function ($message = 'Something went wrong', $errors = [], $code = 500) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], $code);
        });

        Response::macro('validationError', function ($errors, $message = 'Validation failed', $code = 422) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], $code);
        });
    }
}
