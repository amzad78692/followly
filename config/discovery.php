<?php

/*
|--------------------------------------------------------------------------
| Discovery and ranking
|--------------------------------------------------------------------------
|
| Settings for Explore (trending, recommended) and the global search.
|
*/

return [

    // Score = new Followly followers in the window × follow_weight
    //       + platform follower growth in percent (capped) × growth_weight.
    // influencers:rank recalculates it every hour.
    'trending' => [
        'days' => (int) env('TRENDING_DAYS', 7),
        'follow_weight' => (float) env('TRENDING_FOLLOW_WEIGHT', 1),
        'growth_weight' => (float) env('TRENDING_GROWTH_WEIGHT', 2),

        // Small accounts can double in a week, so growth counts up to this many percent (either way).
        'growth_cap' => (float) env('TRENDING_GROWTH_CAP', 50),
    ],

    // Recommendations come from this many of the user's most-followed categories.
    'recommended_categories' => (int) env('RECOMMENDED_CATEGORIES', 3),

    'search' => [
        // Shortest query the top bar searches for.
        'min_length' => 2,

        // Results per group in the top bar dropdown.
        'dropdown_limit' => 5,
    ],

];
