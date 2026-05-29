<?php

declare(strict_types=1);

namespace App\Enums;

enum AddonType: string
{
    case Hot                    = 'hot';
    case Top                    = 'top';
    case CvAccess              = 'cv_access';
    case AnonymousPublication  = 'anonymous_publication';

    public function label(): string
    {
        return match($this) {
            self::Hot                   => 'HOT — підняття вакансії вгору',
            self::Top                   => 'TOP — закріплення у топових позиціях',
            self::CvAccess              => 'Доступ до бази CV',
            self::AnonymousPublication  => 'Анонімна публікація',
        };
    }

    public function price(): int
    {
        return \App\Models\AddonSetting::priceFor($this);
    }

    public function defaultPrice(): int
    {
        return match($this) {
            self::Hot                   => 199,
            self::Top                   => 299,
            self::CvAccess              => 990,
            self::AnonymousPublication  => 599,
        };
    }

    public function durationDays(): int
    {
        return match($this) {
            self::Hot                   => 7,
            self::Top                   => 7,
            self::CvAccess              => 30,
            self::AnonymousPublication  => 0,
        };
    }
}
