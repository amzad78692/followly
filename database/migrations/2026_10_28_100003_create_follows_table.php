<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('influencer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'influencer_id']);
        });

        // Which of the influencer's platforms a user chose to track.
        Schema::create('follow_accounts', function (Blueprint $table) {
            $table->foreignId('follow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('influencer_account_id')->constrained()->cascadeOnDelete();

            $table->primary(['follow_id', 'influencer_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_accounts');
        Schema::dropIfExists('follows');
    }
};
