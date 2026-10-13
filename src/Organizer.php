<?php

namespace Koyomi;

/**
 * Who runs an event, Kirby-free. Read-only.
 *
 * `email` is the PUBLIC address only: Mapper passes the email field only when
 * the organizer's `showEmail` toggle is on, so a hidden address never enters
 * this object and therefore can never reach any output.
 */
final class Organizer
{
    public const PERSON = 'person';
    public const ORGANIZATION = 'organization';

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $kind = self::ORGANIZATION,
        public readonly string $url = '',
        public readonly string $website = '',
        public readonly string $email = '',
        public readonly string $image = '',
        public readonly string $imageAlt = '',
    ) {
    }

    /** Keys as in the constructor; URLs must be http(s), email must be valid, kind person|organization. */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Normalize::string($data['id'] ?? ''),
            name: Normalize::string($data['name'] ?? ''),
            kind: Normalize::choice($data['kind'] ?? '', [self::PERSON, self::ORGANIZATION], self::ORGANIZATION),
            url: Normalize::url($data['url'] ?? ''),
            website: Normalize::url($data['website'] ?? ''),
            email: Normalize::email($data['email'] ?? ''),
            image: Normalize::url($data['image'] ?? ''),
            imageAlt: Normalize::string($data['imageAlt'] ?? ''),
        );
    }

    /** Website, else the organizer page URL, else "". */
    public function link(): string
    {
        return $this->website !== '' ? $this->website : $this->url;
    }

    /**
     * ['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person', 'url' => 'https://…/organizers/anna',
     *  'website' => 'https://…', 'email' => ''|'public@…', 'image' => 'https://…'|'', 'imageAlt' => '…']
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'url' => $this->url,
            'website' => $this->website,
            'email' => $this->email,
            'image' => $this->image,
            'imageAlt' => $this->imageAlt,
        ];
    }
}
