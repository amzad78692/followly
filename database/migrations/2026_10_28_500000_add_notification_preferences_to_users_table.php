<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Show new post alerts in the app (bell, sidebar badge).
            $table->boolean('notify_in_app')->default(true)->after('password');
            // App\Enums\Platform values the user gets no new post notifications for.
            $table->json('muted_platforms')->nullable()->after('notify_in_app');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_in_app', 'muted_platforms']);
        });
    }
};
