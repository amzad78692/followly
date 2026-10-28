<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('influencer_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('external_id');
            $table->string('type', 20)->default('video');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->json('media')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('permalink', 2048)->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('published_at')->index();
            $table->timestamps();

            $table->unique(['platform', 'external_id']);
            $table->index(['influencer_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
