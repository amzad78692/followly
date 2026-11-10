<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Max 10 per user, checked in code.
        Schema::table('follows', function (Blueprint $table) {
            $table->timestamp('watchlisted_at')->nullable()->after('pinned_at');
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('share_token')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('collection_influencer', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('influencer_id')->constrained()->cascadeOnDelete();

            $table->primary(['collection_id', 'influencer_id']);
        });

        // SmartLink: posts telling the same story share a group id.
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->index();
        });

        Schema::create('keyword_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->timestamps();
        });

        // One row per user per day they used the site, for daily and monthly active users.
        Schema::create('user_activity_days', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();

            $table->primary(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_days');
        Schema::dropIfExists('keyword_alerts');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });

        Schema::dropIfExists('collection_influencer');
        Schema::dropIfExists('collections');

        Schema::table('follows', function (Blueprint $table) {
            $table->dropColumn('watchlisted_at');
        });
    }
};
