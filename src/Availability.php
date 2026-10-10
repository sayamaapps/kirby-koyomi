<?php

namespace Koyomi;

/**
 * Ticket availability (field `availability`).
 *
 *   value      label key               schema.org availability
 *   available  availability.available  https://schema.org/InStock
 *   limited    availability.limited    https://schema.org/LimitedAvailability
 *   soldout    availability.soldout    https://schema.org/SoldOut
 */
final class Availability
{
    public const AVAILABLE = 'available';
    public const LIMITED = 'limited';
    public const SOLDOUT = 'soldout';

    public const ALL = [self::AVAILABLE, self::LIMITED, self::SOLDOUT];

    public static function normalize(mixed $value): string
    {
        return Normalize::choice($value, self::ALL, self::AVAILABLE);
    }
}
