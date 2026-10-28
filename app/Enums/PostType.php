<?php

namespace App\Enums;

enum PostType: string
{
    case Video = 'video';
    case Image = 'image';
    case Carousel = 'carousel';
    case Text = 'text';
}
