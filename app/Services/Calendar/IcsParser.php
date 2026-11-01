<?php

namespace App\Services\Calendar;

use DateInterval;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reads iCalendar (.ics) files without Claude: an invite or exported calendar
 * already says exactly when each event is, so a model call would only add
 * cost and a chance of error.
 *
 * Handles line unfolding, all-day (VALUE=DATE, exclusive DTEND), UTC ("Z"),
 * TZID and floating times, DURATION, RFC 5545 text escapes and attendees
 * (CN + mailto). Cancelled events are skipped; recurring ones import their
 * first occurrence with a note. Times come out as wall-clock times in the
 * uploader's timezone, matching what the event form stores.
 */
class IcsParser
{
    public const MAX_EVENTS = 500;

    /**
     * Raw proposals in the extractor's shape, or an empty list if the file
     * holds no VEVENT (the caller may then fall back to Claude).
     *
     * @return list<array<string, mixed>>
     */
    public function parse(string $ics, string $timezone): array
    {
        $tz = $this->zone($timezone) ?? new DateTimeZone(config('app.timezone'));
        $events = [];
        $stack = [];
        $props = [];

        foreach ($this->lines($ics) as $line) {
            [$name, $params, $value] = $this->property($line);

            if ($name === 'BEGIN') {
                $stack[] = strtoupper($value);
                if (strtoupper($value) === 'VEVENT') {
                    $props = [];
                }

                continue;
            }
            if ($name === 'END') {
                $ended = array_pop($stack);
                if ($ended === 'VEVENT' && ($event = $this->event($props, $tz)) !== null) {
                    $events[] = $event;
                    if (count($events) >= self::MAX_EVENTS) {
                        break;
                    }
                }

                continue;
            }

            // Only the event's own properties: not those of a nested VALARM.
            if (end($stack) === 'VEVENT') {
                $props[$name][] = ['params' => $params, 'value' => $value];
            }
        }

        return $events;
    }

    /** @return list<string> */
    private function lines(string $ics): array
    {
        $ics = str_replace(["\r\n", "\r"], "\n", $ics);
        $ics = preg_replace("/\n[ \t]/", '', $ics); // unfold continuation lines

        return array_values(array_filter(explode("\n", $ics), fn ($l) => trim($l) !== ''));
    }

    /**
     * NAME;PARAM=a;PARAM2="b:c":value → [NAME, [PARAM => a, PARAM2 => b:c], value]
     *
     * @return array{0: string, 1: array<string, string>, 2: string}
     */
    private function property(string $line): array
    {
        $colon = null;
        $quoted = false;
        for ($i = 0, $n = strlen($line); $i < $n; $i++) {
            if ($line[$i] === '"') {
                $quoted = ! $quoted;
            } elseif ($line[$i] === ':' && ! $quoted) {
                $colon = $i;
                break;
            }
        }
        if ($colon === null) {
            return [strtoupper(trim($line)), [], ''];
        }

        $head = explode(';', substr($line, 0, $colon));
        $params = [];
        foreach (array_slice($head, 1) as $param) {
            [$key, $val] = array_pad(explode('=', $param, 2), 2, '');
            $params[strtoupper($key)] = trim($val, '"');
        }

        return [strtoupper($head[0]), $params, substr($line, $colon + 1)];
    }

    /** @param  array<string, list<array{params: array<string, string>, value: string}>>  $props */
    private function event(array $props, DateTimeZone $tz): ?array
    {
        $first = fn (string $name) => $props[$name][0] ?? null;

        if (strtoupper(trim($first('STATUS')['value'] ?? '')) === 'CANCELLED' || ! $first('DTSTART')) {
            return null;
        }

        $start = $this->dateTime($first('DTSTART'), $tz);
        if ($start === null) {
            return null;
        }
        [$startAt, $allDay] = $start;

        $endAt = null;
        if ($end = $first('DTEND') ?? $first('DUE')) {
            $endAt = $this->dateTime($end, $tz)[0] ?? null;
            if ($endAt && $allDay) {
                $endAt = $endAt->copy()->subDay(); // DTEND is exclusive for all-day events
            }
        } elseif ($duration = $first('DURATION')) {
            $endAt = $this->addDuration($startAt, $duration['value']);
            if ($endAt && $allDay) {
                $endAt = $endAt->subDay();
            }
        }
        if ($endAt && $endAt->lt($startAt)) {
            $endAt = null;
        }

        $title = $this->text($first('SUMMARY')['value'] ?? '') ?: 'Untitled event';
        $description = $this->text($first('DESCRIPTION')['value'] ?? '');
        if ($first('RRULE')) {
            $description = trim($description."\n\nRepeats in the source calendar (".$first('RRULE')['value'].'); only the first occurrence was imported.');
        }

        $attendees = [];
        foreach ($props['ATTENDEE'] ?? [] as $attendee) {
            $email = preg_replace('/^mailto:/i', '', trim($attendee['value']));
            $name = trim($attendee['params']['CN'] ?? '');
            $attendees[] = $name !== '' && strcasecmp($name, $email) !== 0 ? "{$name} <{$email}>" : $email;
        }

        return [
            'title' => $title,
            'type' => $this->guessType($title),
            'start_date' => $startAt->toDateString(),
            'start_time' => $allDay ? null : $startAt->format('H:i'),
            'end_date' => $endAt && $endAt->toDateString() !== $startAt->toDateString() ? $endAt->toDateString() : null,
            'end_time' => $endAt && ! $allDay ? $endAt->format('H:i') : null,
            'all_day' => $allDay,
            'description' => $description !== '' ? $description : null,
            'location' => $this->text($first('LOCATION')['value'] ?? '') ?: null,
            'attendees' => array_values(array_filter($attendees)),
            'release_hint' => null,
            'confidence' => 1.0,
        ];
    }

    /**
     * @param  array{params: array<string, string>, value: string}  $prop
     * @return array{0: Carbon, 1: bool}|null [wall-clock time in $tz, all-day]
     */
    private function dateTime(array $prop, DateTimeZone $tz): ?array
    {
        $value = trim($prop['value']);

        try {
            if (($prop['params']['VALUE'] ?? null) === 'DATE' || preg_match('/^\d{8}$/', $value)) {
                return [Carbon::createFromFormat('!Ymd', substr($value, 0, 8), $tz), true];
            }

            if (! preg_match('/^(\d{8}T\d{4,6})(Z?)$/i', $value, $m)) {
                return null;
            }
            $stamp = str_pad(strtoupper($m[1]), 15, '0');
            $source = $m[2] !== '' ? new DateTimeZone('UTC') : ($this->zone($prop['params']['TZID'] ?? '') ?? $tz);

            return [Carbon::createFromFormat('Ymd\THis', $stamp, $source)->setTimezone($tz), false];
        } catch (Throwable) {
            return null;
        }
    }

    private function addDuration(Carbon $start, string $duration): ?Carbon
    {
        try {
            $negative = str_starts_with($duration, '-');
            $interval = new DateInterval(ltrim($duration, '+-'));

            return $negative ? $start->copy()->sub($interval) : $start->copy()->add($interval);
        } catch (Throwable) {
            return null;
        }
    }

    private function zone(string $name): ?DateTimeZone
    {
        try {
            return $name !== '' ? new DateTimeZone($name) : null;
        } catch (Throwable) {
            return null; // e.g. Outlook's "Pacific Standard Time": fall back to the uploader's zone
        }
    }

    private function text(string $value): string
    {
        return trim(strtr($value, ['\\n' => "\n", '\\N' => "\n", '\\,' => ',', '\;' => ';', '\\\\' => '\\']));
    }

    private function guessType(string $title): string
    {
        $t = mb_strtolower($title);

        return match (true) {
            (bool) preg_match('/\b(release|launch|go[- ]live|ship|deploy)/', $t) => 'release',
            (bool) preg_match('/\b(deadline|due|cut-?off|freeze)/', $t) => 'deadline',
            (bool) preg_match('/\b(review|retro|demo|audit)/', $t) => 'review',
            default => 'meeting',
        };
    }
}
