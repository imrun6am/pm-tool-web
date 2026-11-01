<?php

namespace App\Services\Calendar;

use Stringable;

/** Appends text up to a character cap, failing the import once it is exceeded. */
class TextBuffer implements Stringable
{
    private string $text = '';

    private int $length = 0;

    public function __construct(private readonly int $max) {}

    public function add(string $text): void
    {
        $this->length += mb_strlen($text);
        if ($this->length > $this->max) {
            throw self::tooLong($this->max);
        }
        $this->text .= $text;
    }

    public static function tooLong(int $max): UnreadableDocumentException
    {
        return new UnreadableDocumentException(sprintf(
            'This document has more than %s characters of text. Split it into smaller files and import them one at a time.',
            number_format($max),
        ));
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
