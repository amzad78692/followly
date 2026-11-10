<?php

namespace App\Console\Commands;

use App\Feed\SmartLink;
use App\Models\Post;
use Illuminate\Console\Command;

class BackfillSmartLink extends Command
{
    protected $signature = 'posts:smartlink';

    protected $description = 'Group existing posts that tell the same story across platforms (SmartLink)';

    public function handle(SmartLink $smartLink): int
    {
        $before = Post::whereNotNull('group_id')->count();

        // Reloaded per chunk: an earlier post in the run may already have grouped some of these.
        Post::whereNull('group_id')->select('id')->chunkById(500, function ($ids) use ($smartLink) {
            Post::whereKey($ids->pluck('id'))->whereNull('group_id')->get()->each(fn (Post $post) => $smartLink->group($post));
        });

        $this->info((Post::whereNotNull('group_id')->count() - $before).' posts grouped.');

        return self::SUCCESS;
    }
}
