<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('handle');
            $table->string('external_id')->nullable();
            $table->string('url', 2048)->nullable();
            $table->unsignedBigInteger('followers')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->string('sync_status', 20)->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['influencer_id', 'platform']);
            $table->unique(['platform', 'external_id']);
            $table->index(['platform', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_accounts');
    }
};
