<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PostType: string implements HasLabel
{
    case Video = 'video';
    case Image = 'image';
    case Carousel = 'carousel';
    case Text = 'text';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
