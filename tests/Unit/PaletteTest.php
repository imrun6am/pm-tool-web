<?php

namespace Tests\Unit;

use App\Support\Palette;
use PHPUnit\Framework\TestCase;

/** The semantic color palette (openspec/changes/vibrant-blue-brand-and-semantic-icons). */
class PaletteTest extends TestCase
{
    public function test_meanings_resolve_to_their_hues(): void
    {
        $this->assertSame('text-emerald-600', Palette::text('success'));
        $this->assertSame('bg-amber-50 text-amber-700', Palette::soft('warning'));
        $this->assertSame('hover:bg-rose-50 hover:text-rose-600', Palette::hover('danger'));
        $this->assertSame('text-brand-600', Palette::text('info'));
    }

    public function test_areas_resolve_to_one_hue_each(): void
    {
        $this->assertSame('rose', Palette::hue('calendar'));
        $this->assertSame('violet', Palette::hue('releases'));
        $this->assertSame(Palette::hue('board'), Palette::hue('tasks'));
    }

    public function test_plain_hues_pass_through_and_unknown_tones_fall_back_to_neutral(): void
    {
        $this->assertSame('teal', Palette::hue('teal'));
        $this->assertSame('slate', Palette::hue('nonsense'));
        $this->assertSame('slate', Palette::hue(null));
    }

    public function test_every_alias_and_area_points_at_a_defined_hue(): void
    {
        foreach (array_merge(Palette::ALIASES, Palette::DOMAINS) as $name => $hue) {
            $this->assertArrayHasKey($hue, Palette::TONES, $name);
        }
        foreach (Palette::TONES as $hue => $classes) {
            $this->assertSame(['text', 'soft', 'solid', 'ring', 'hover', 'back'], array_keys($classes), $hue);
        }
    }
}
