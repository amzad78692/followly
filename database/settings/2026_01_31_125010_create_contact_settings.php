<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('contact.email');
        $this->migrator->add('contact.mobile');
        $this->migrator->add('contact.whatsapp');
        $this->migrator->add('contact.address');
    }
};
