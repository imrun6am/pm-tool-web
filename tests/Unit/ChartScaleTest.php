<?php

namespace Tests\Unit;

use App\Support\ChartScale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChartScaleTest extends TestCase
{
    #[DataProvider('peaks')]
    public function test_it_picks_a_round_top_just_above_the_peak(float $peak, int $top): void
    {
        $this->assertSame($top, ChartScale::top($peak));
    }

    public static function peaks(): array
    {
        return [
            'nothing' => [0, 1],
            'one' => [1, 1],
            'three' => [3, 3],
            'seven' => [7, 8],
            'nine' => [9, 10],
            'ten' => [10, 10],
            'eleven' => [11, 12],
            'fifty-seven' => [57, 60],
            'a thousand' => [1000, 1000],
            'a fraction' => [8.1, 10],
        ];
    }
}
