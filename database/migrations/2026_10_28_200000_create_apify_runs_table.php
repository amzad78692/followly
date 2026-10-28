<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apify_runs', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->nullable();
            $table->string('actor');
            $table->string('run_id')->nullable()->unique();
            $table->string('status', 20);
            $table->unsignedInteger('items_count')->default(0);
            $table->decimal('usage_usd', 10, 4)->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apify_runs');
    }
};
