<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Influencers\InfluencerResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Follow;
use App\Models\Influencer;
use App\Models\Post;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Followly at a glance: people, follows, creators and today's synced posts.
 */
class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $newUsers = User::where('created_at', '>=', now()->subWeek())->count();
        $postsToday = Post::where('created_at', '>=', today())->count();

        return [
            Stat::make('Users', Number::format(User::count()))
                ->description($newUsers ? "{$newUsers} new this week" : 'None new this week')
                ->icon(Heroicon::OutlinedUsers)
                ->url(UserResource::getUrl('index')),
            Stat::make('Follows', Number::format(Follow::count()))
                ->description(Number::format(Follow::where('created_at', '>=', now()->subWeek())->count()).' this week')
                ->icon(Heroicon::OutlinedHeart),
            Stat::make('Influencers', Number::format(Influencer::count()))
                ->description(Number::format(Influencer::has('follows')->count()).' tracked by someone')
                ->icon(Heroicon::OutlinedUserGroup)
                ->url(InfluencerResource::getUrl('index')),
            Stat::make('Posts synced today', Number::format($postsToday))
                ->description(Number::format(Post::count()).' stored in total')
                ->icon(Heroicon::OutlinedNewspaper)
                ->url(PostResource::getUrl('index', ['filters' => ['today' => ['isActive' => true]]])),
        ];
    }
}
