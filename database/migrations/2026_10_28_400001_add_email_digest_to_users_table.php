<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // App\Enums\DigestFrequency; null means no email digest.
            $table->string('email_digest', 10)->nullable()->after('password');
            $table->timestamp('digest_sent_at')->nullable()->after('email_digest');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_digest', 'digest_sent_at']);
        });
    }
};
