<?php

namespace Database\Seeders;

use App\Settings\ContactSettings;
use Illuminate\Database\Seeder;

class ContactSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(ContactSettings::class);

        $defaults = [
            'email' => 'info@followly.test',
            'mobile' => '+965 0000 0000',
            'whatsapp' => '+965 0000 0000',
            'address' => 'Kuwait City, Kuwait',
            'youtube' => 'https://youtube.com/@followly',
            'instagram' => 'https://instagram.com/followly',
            'tiktok' => 'https://tiktok.com/@followly',
            'x' => 'https://x.com/followly',
        ];

        // Only fill blanks so values saved from the admin panel are never overwritten.
        foreach ($defaults as $key => $value) {
            $settings->{$key} = $settings->{$key} ?: $value;
        }

        $settings->save();
    }
}
