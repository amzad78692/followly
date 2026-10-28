<?php

namespace App\Enums;

enum Platform: string
{
    case YouTube = 'youtube';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case X = 'x';

    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::X => 'X',
        };
    }

    /**
     * What the platform calls its audience ("Subscribers" / "Followers").
     */
    public function unit(): string
    {
        return $this === self::YouTube ? 'Subscribers' : 'Followers';
    }

    public function profileUrl(string $handle): string
    {
        $handle = ltrim($handle, '@');

        return match ($this) {
            self::YouTube => "https://www.youtube.com/@{$handle}",
            self::Instagram => "https://www.instagram.com/{$handle}",
            self::TikTok => "https://www.tiktok.com/@{$handle}",
            self::X => "https://x.com/{$handle}",
        };
    }

    /**
     * Hosts that belong to this platform, used to detect a platform from a pasted link.
     */
    public function hosts(): array
    {
        return match ($this) {
            self::YouTube => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'],
            self::Instagram => ['instagram.com', 'www.instagram.com'],
            self::TikTok => ['tiktok.com', 'www.tiktok.com', 'vm.tiktok.com'],
            self::X => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com', 'mobile.twitter.com'],
        };
    }

    /**
     * SQL that sorts a platform column in case order (YouTube first).
     */
    public static function orderSql(string $column = 'platform'): string
    {
        $cases = collect(self::cases())->map(fn (self $p, int $i) => "when '{$p->value}' then {$i}")->join(' ');

        return "case {$column} {$cases} else ".count(self::cases()).' end';
    }

    public static function fromUrl(string $url): ?self
    {
        $host = strtolower(parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST) ?? '');

        foreach (self::cases() as $platform) {
            if (in_array($host, $platform->hosts(), true)) {
                return $platform;
            }
        }

        return null;
    }
}
