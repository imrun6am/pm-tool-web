<?php

namespace App\Services\Calendar;

use DateTimeInterface;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;
use XMLReader;

/**
 * Turns the formats Claude can't read natively into plain text: Word (.docx),
 * Excel (.xlsx), CSV and plain text. Everything is streamed (XMLReader over
 * the zip entry, openspout row iterators), and output is capped, so a large
 * upload never loads whole into the worker's memory. Over the cap it fails
 * loudly rather than silently dropping the rest of the document.
 */
class DocumentTextConverter
{
    public const MAX_CHARS = 1000000;

    public const EXTENSIONS = ['docx', 'xlsx', 'csv', 'txt', 'md', 'ics'];

    public function convert(string $path, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'docx' => $this->docx($path),
            'xlsx' => $this->spreadsheet(new XlsxReader, $path, true),
            'csv' => $this->spreadsheet(new CsvReader, $path, false),
            default => $this->plain($path),
        };

        $text = trim(preg_replace("/[ \t]+\n/", "\n", preg_replace("/\n{3,}/", "\n\n", $text)));
        if ($text === '') {
            throw new UnreadableDocumentException('No readable text was found in this file.');
        }

        return $text;
    }

    private function docx(string $path): string
    {
        $xml = new XMLReader;
        if (! @$xml->open('zip://'.$path.'#word/document.xml', null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new UnreadableDocumentException('This Word file could not be opened. Save it as .docx and try again.');
        }

        $out = new TextBuffer(self::MAX_CHARS);
        try {
            while (@$xml->read()) {
                $name = $xml->name;
                if ($xml->nodeType === XMLReader::ELEMENT) {
                    match ($name) {
                        'w:t' => $out->add($xml->readString()),
                        'w:tab' => $out->add("\t"),
                        'w:br', 'w:cr' => $out->add("\n"),
                        default => null,
                    };
                } elseif ($xml->nodeType === XMLReader::END_ELEMENT) {
                    match ($name) {
                        'w:p' => $out->add("\n"),
                        'w:tc' => $out->add(' | '),
                        'w:tr' => $out->add("\n"),
                        default => null,
                    };
                }
            }
        } finally {
            $xml->close();
        }

        return (string) $out;
    }

    private function spreadsheet(XlsxReader|CsvReader $reader, string $path, bool $namedSheets): string
    {
        try {
            $reader->open($path);
        } catch (Throwable) {
            throw new UnreadableDocumentException('This spreadsheet could not be opened.');
        }

        $out = new TextBuffer(self::MAX_CHARS);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($namedSheets) {
                    $out->add("\n## Sheet: ".$sheet->getName()."\n");
                }
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($v) => $this->cell($v), $row->toArray());
                    while ($cells !== [] && end($cells) === '') {
                        array_pop($cells);
                    }
                    if ($cells !== []) {
                        $out->add(implode(' | ', $cells)."\n");
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return (string) $out;
    }

    private function cell(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format('Y-m-d H:i');
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    private function plain(string $path): string
    {
        $size = @filesize($path);
        if ($size === false) {
            throw new UnreadableDocumentException('The uploaded file is missing.');
        }
        // UTF-8 text is at most 4 bytes a character; anything bigger is over the cap.
        if ($size > self::MAX_CHARS * 4) {
            throw TextBuffer::tooLong(self::MAX_CHARS);
        }

        $text = (string) file_get_contents($path);
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        } elseif (str_starts_with($text, "\xFF\xFE") || str_starts_with($text, "\xFE\xFF")) {
            $text = mb_convert_encoding(substr($text, 2), 'UTF-8', str_starts_with($text, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        } elseif (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (mb_strlen($text) > self::MAX_CHARS) {
            throw TextBuffer::tooLong(self::MAX_CHARS);
        }

        return $text;
    }
}
