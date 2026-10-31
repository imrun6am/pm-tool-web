<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes user-supplied rich text (from the Trix editor) down to a safe
 * allow-list of tags, stripping scripts, event handlers and unsafe URLs.
 * Rich-text fields store HTML, so this MUST run before the value is persisted.
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim(strip_tags($html)) === '' && trim($html) === '') {
            return '';
        }

        return self::purifier()->purify($html);
    }

    /** Does the value have any visible text (ignoring markup/whitespace)? */
    public static function isEmpty(?string $html): bool
    {
        return trim(strip_tags((string) $html)) === '';
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier) {
            return self::$purifier;
        }

        $cache = storage_path('app/htmlpurifier');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        // Tags the Trix editor emits, plus common inline formatting. `div` is Trix's
        // line wrapper; all attributes outside the allow-list are stripped.
        $config->set('HTML.Allowed', 'p,br,div,strong,b,em,i,u,s,del,h1,h2,h3,ul,ol,li,blockquote,pre,code,a[href|title]');
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Cache.SerializerPath', $cache);

        return self::$purifier = new HTMLPurifier($config);
    }
}
