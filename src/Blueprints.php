<?php

namespace Koyomi;

/**
 * Page blueprints as PHP arrays, Kirby-free.      OWNER: task `panel`
 *
 * index.php registers them as closures
 *   'pages/koyomi-event' => fn () => Blueprints::event(Options::blueprintSettings(App::instance()))
 * so option-dependent choices (zones, currencies, languages) are read when
 * the Panel needs the blueprint. A site overrides one by placing
 * site/blueprints/pages/koyomi-event.yml (Kirby looks there first).
 *
 * Labels are I18n::all('<key>') arrays (['en' => …, 'de' => …]).
 * Exact field contract: SPEC.md §5 and §8.12.
 *
 * $settings shape (Options::blueprintSettings()):
 *   ['timezone' => 'Europe/Berlin', 'timezones' => ['Europe/Berlin', …],
 *    'currency' => 'EUR', 'currencies' => ['EUR', 'CZK', 'CHF'],
 *    'languages' => ['de' => '', 'en' => '', 'cs' => 'Čeština']]   // '' = translation language.<code>
 */
final class Blueprints
{
    /** Blueprint names index.php registers, all under pages/. */
    public const NAMES = ['koyomi-calendar', 'koyomi-event', 'koyomi-venues', 'koyomi-venue', 'koyomi-organizers', 'koyomi-organizer'];

    public static function calendar(array $settings): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    public static function event(array $settings): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    public static function venues(array $settings): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    public static function venue(array $settings): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    public static function organizers(array $settings): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    public static function organizer(array $settings): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Select options [['value' => 'scheduled', 'text' => I18n::all('status.scheduled')], …] for a list of values and a key prefix. */
    public static function options(array $values, string $prefix): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}
