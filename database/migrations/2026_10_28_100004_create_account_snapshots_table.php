<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('followers');
            $table->date('captured_on');

            $table->unique(['influencer_account_id', 'captured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_snapshots');
    }
};
