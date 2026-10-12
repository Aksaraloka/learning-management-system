<?php

namespace App\Enums;

enum MaterialType: string
{
    case Text = 'text';
    case File = 'file';
    case Link = 'link';
    case Video = 'video';

    public function label(): string
    {
        return __('courses.types.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Text => 'file-text',
            self::File => 'paperclip',
            self::Link => 'link',
            self::Video => 'video',
        };
    }
}
