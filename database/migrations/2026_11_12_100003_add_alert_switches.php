<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master "Alerts On" switch on the Alerts page.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('alerts_enabled')->default(true);
        });

        Schema::table('keyword_alerts', function (Blueprint $table) {
            $table->boolean('enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('alerts_enabled'));
        Schema::table('keyword_alerts', fn (Blueprint $table) => $table->dropColumn('enabled'));
    }
};
