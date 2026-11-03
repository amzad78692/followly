<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('contact.youtube');
        $this->migrator->add('contact.instagram');
        $this->migrator->add('contact.tiktok');
        $this->migrator->add('contact.x');
    }
};
