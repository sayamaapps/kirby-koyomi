<?php

namespace Koyomi;

use Kirby\Cms\Page;

/**
 * Kirby hooks.                                   OWNER: task `panel`
 * Contract: SPEC.md §8.13.
 */
final class Hooks
{
    /** Field values a duplicated event gets (dates and everything else are kept — the copy is a draft). */
    public const DUPLICATE_RESET = [
        'eventStatus' => Status::SCHEDULED,
        'previousStart' => '',
        'statusNote' => '',
        'availability' => Availability::AVAILABLE,
    ];

    /**
     * page.duplicate:after. Only when Options::active() and
     * Options::duplicateReset(), and only for intendedTemplate koyomi-event:
     * $duplicatePage->update(self::DUPLICATE_RESET). Never throws (errors go
     * to error_log): a failing reset must not break duplicating. Returns the
     * updated page (or the unchanged one).
     */
    public static function duplicateAfter(Page $duplicatePage, Page $originalPage): Page
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}
