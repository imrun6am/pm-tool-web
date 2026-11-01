<?php

namespace App\Services\Calendar;

/**
 * Splits document text into pieces small enough for one Claude call each, so
 * a long spreadsheet or agenda can't blow the token or memory budget of a
 * single request. Breaks fall on paragraph, then line boundaries where
 * possible; only a single over-long line is cut mid-text.
 */
class TextChunker
{
    /** ~10k tokens of prose: plenty of room for the events it may contain. */
    public const DEFAULT_SIZE = 40000;

    /** @return list<string> */
    public static function split(string $text, int $size = self::DEFAULT_SIZE): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= $size) {
            return [$text];
        }

        $chunks = [];
        $current = '';

        foreach (self::pieces($text, $size) as $piece) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > $size) {
                $chunks[] = trim($current);
                $current = '';
            }
            $current .= ($current === '' ? '' : "\n").$piece;
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * Paragraphs, with any paragraph longer than $size broken into lines and
     * any line longer than $size cut into $size pieces.
     *
     * @return iterable<string>
     */
    private static function pieces(string $text, int $size): iterable
    {
        foreach (preg_split("/\n\s*\n/", $text) as $paragraph) {
            if (mb_strlen($paragraph) <= $size) {
                yield $paragraph."\n";

                continue;
            }

            foreach (explode("\n", $paragraph) as $line) {
                for ($i = 0, $n = mb_strlen($line); $i < max($n, 1); $i += $size) {
                    yield mb_substr($line, $i, $size);
                }
            }
        }
    }
}
