<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageType: string
{
    case Text        = 'text';
    case Image       = 'image';
    case Document    = 'document';
    case Audio       = 'audio';
    case Video       = 'video';
    case Sticker     = 'sticker';
    case Location    = 'location';
    case Contacts    = 'contacts';
    case Template    = 'template';
    case Interactive = 'interactive';
    case Button      = 'button';
    case Reaction    = 'reaction';

    /** Type reçu de Meta que la Phase 1 ne sait pas interpréter. */
    case Unsupported = 'unsupported';

    public function isMedia(): bool
    {
        return in_array($this, [
            self::Image, self::Document, self::Audio, self::Video, self::Sticker,
        ], true);
    }

    /** Ce type peut-il alimenter le contexte textuel envoyé au LLM ? */
    public function hasTextContent(): bool
    {
        return in_array($this, [
            self::Text, self::Button, self::Interactive, self::Template,
        ], true);
    }

    public static function fromMeta(string $type): self
    {
        return self::tryFrom($type) ?? self::Unsupported;
    }
}
