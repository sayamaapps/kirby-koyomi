<?php

namespace Koyomi;

/**
 * schema.org Event as JSON-LD, Kirby-free.        OWNER: task `seo`
 *
 * Contract: SPEC.md §8.3 (full example array there). Rules in short:
 * - Only for the single event page (snippet koyomi/jsonld), never for lists.
 * - Keys with empty values are left out.
 * - VirtualLocation.url is the EVENT PAGE URL — never a meeting link (Event
 *   has none).
 */
final class JsonLd
{
    public const CONTEXT = 'https://schema.org';

    /**
     * @param array{language?: string} $options  language for nothing visible yet; reserved
     * @return array<string,mixed>  plain array, see SPEC.md §8.3
     */
    public static function event(Event $event, array $options = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** "https://schema.org/EventScheduled" … per Status (see src/Status.php table). */
    public static function eventStatus(string $status): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** "https://schema.org/OfflineEventAttendanceMode" | Online… | Mixed… for an Attendance value. */
    public static function attendanceMode(string $attendance): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** "https://schema.org/InStock" | LimitedAvailability | SoldOut for an Availability value. */
    public static function availability(string $availability): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * json_encode with JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE |
     * JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRESERVE_ZERO_FRACTION |
     * JSON_THROW_ON_ERROR. JSON_HEX_TAG turns "<" ">" into < >, so
     * "</script>" in a title can never close the script element.
     */
    public static function encode(array $data): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** '<script type="application/ld+json">' . encode($data) . '</script>' */
    public static function script(array $data): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}
