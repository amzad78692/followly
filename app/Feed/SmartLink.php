<?php

namespace App\Feed;

use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Groups posts that tell the same story across platforms (Phase 0 rule, no AI): same influencer,
 * a different account, published within 24 hours, and either a shared link or captions at least
 * 80% similar after cleanup. Grouped posts share posts.group_id (the first grouped post's id).
 */
class SmartLink
{
    public const WINDOW_HOURS = 24;

    public const SIMILARITY = 80;

    /** Long YouTube descriptions would make similar_text() slow; the opening is enough to compare. */
    protected const COMPARE_CHARS = 300;

    /**
     * Put the post in the group of the first matching post. Returns the group id, or null.
     */
    public function group(Post $post): ?int
    {
        if (! $post->published_at || $post->group_id) {
            return $post->group_id;
        }

        $match = Post::query()
            ->where('influencer_id', $post->influencer_id)
            ->where('influencer_account_id', '!=', $post->influencer_account_id)
            ->whereBetween('published_at', [
                $post->published_at->copy()->subHours(self::WINDOW_HOURS),
                $post->published_at->copy()->addHours(self::WINDOW_HOURS),
            ])
            ->orderBy('published_at')
            ->get()
            ->first(fn (Post $other) => $this->sameStory($post, $other));

        if (! $match) {
            return null;
        }

        $groupId = $match->group_id ?? min($match->id, $post->id);
        $match->group_id ??= $groupId;
        $match->saveQuietly();
        $post->group_id = $groupId;
        $post->saveQuietly();

        return $groupId;
    }

    public function sameStory(Post $a, Post $b): bool
    {
        if (array_intersect($this->links($a), $this->links($b))) {
            return true;
        }

        [$x, $y] = [$this->caption($a), $this->caption($b)];

        // Very short captions ("🔥", "New video!") say too little to call it the same story.
        if (mb_strlen($x) < 10 || mb_strlen($y) < 10) {
            return false;
        }

        similar_text($x, $y, $percent);

        return $percent >= self::SIMILARITY;
    }

    /**
     * Links in the caption plus the post's own address, without scheme, www and trailing slash.
     *
     * @return array<int, string>
     */
    public function links(Post $post): array
    {
        preg_match_all('~https?://\S+~i', "{$post->title} {$post->body}", $found);

        return collect([...$found[0], $post->permalink])
            ->filter()
            ->map(fn (string $url) => rtrim(preg_replace('~^https?://(www\.)?~i', '', Str::lower(rtrim($url, '.,!?)'))), '/'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Title and body in lower case without links, mentions, hashtags' # sign, emoji and punctuation.
     */
    public function caption(Post $post): string
    {
        $text = Str::lower("{$post->title} {$post->body}");
        $text = preg_replace(['~https?://\S+~u', '~@\w+~u', '~[^\p{L}\p{N}\s]+~u', '~\s+~u'], ['', '', ' ', ' '], $text);

        return mb_substr(trim($text), 0, self::COMPARE_CHARS);
    }
}
