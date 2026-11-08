<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follows', function (Blueprint $table) {
            $table->boolean('notify')->default(true)->after('influencer_id');
            $table->timestamp('pinned_at')->nullable()->after('notify');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_push')->default(true)->after('notify_in_app');
            $table->boolean('push_daily_summary')->default(false)->after('notify_push');
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Disconnecting a device deletes its access token, and this row goes with it.
            $table->foreignId('personal_access_token_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20); // App\Enums\DevicePlatform
            $table->string('token', 512)->unique();
            $table->string('timezone', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_push', 'push_daily_summary']);
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->dropColumn(['notify', 'pinned_at']);
        });
    }
};
