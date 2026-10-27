<?php

namespace Sayamaapps\Koyomi;

/**
 * Ticket availability (field `availability`).
 *
 *   value      label key               schema.org availability
 *   available  availability.available  https://schema.org/InStock
 *   limited    availability.limited    https://schema.org/LimitedAvailability
 *   soldout    availability.soldout    https://schema.org/SoldOut
 *
 * @package   Sayamaapps\Koyomi
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class Availability
{
	public const ALL = [self::AVAILABLE, self::LIMITED, self::SOLDOUT];
	public const AVAILABLE = 'available';
	public const LIMITED = 'limited';
	public const SOLDOUT = 'soldout';

	public static function normalize(mixed $value): string
	{
		return Normalize::choice($value, self::ALL, self::AVAILABLE);
	}
}
