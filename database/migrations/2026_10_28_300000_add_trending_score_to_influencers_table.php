<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('influencers', function (Blueprint $table) {
            // Recalculated every hour by influencers:rank (App\Discovery\TrendingRanker).
            $table->float('trending_score')->default(0)->index()->after('combined_reach');
        });
    }

    public function down(): void
    {
        Schema::table('influencers', function (Blueprint $table) {
            $table->dropIndex(['trending_score']);
            $table->dropColumn('trending_score');
        });
    }
};
