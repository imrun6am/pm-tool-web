{{--
    Inline-SVG trend line for a small series of scores.
    @include('partials.spark-line', ['points' => [['label'=>'Jul 1','value'=>3.2], ...], 'max' => 5])
    `value` may be null (gap). Line colour follows the latest non-null value.
--}}
@php
    use App\Support\ScoreColor;
    $points = array_values($points ?? []);
    $max = $max ?? 5;
    $w = $w ?? 320;
    $h = $h ?? 64;
    $pad = 6;
    $n = count($points);
    $step = $n > 1 ? ($w - 2 * $pad) / ($n - 1) : 0;

    $coords = [];
    foreach ($points as $i => $p) {
        $v = $p['value'] ?? null;
        if ($v === null) {
            $coords[] = null;
            continue;
        }
        $x = $pad + $i * $step;
        $y = $h - $pad - ($v / $max) * ($h - 2 * $pad);
        $coords[] = ['x' => round($x, 1), 'y' => round($y, 1), 'v' => $v];
    }
    $filled = array_values(array_filter($coords));
    $last = collect($points)->pluck('value')->filter(fn ($v) => $v !== null)->last();
    $stroke = ScoreColor::hex($last !== null ? (float) $last : null);
    $poly = collect($filled)->map(fn ($c) => $c['x'].','.$c['y'])->implode(' ');
@endphp

@if (empty($filled))
    <div class="flex h-16 items-center justify-center text-xs text-slate-300">No trend data yet</div>
@else
    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-16 w-full" preserveAspectRatio="none" role="img" aria-label="Score trend">
        {{-- midline at "Meets" (3/5) --}}
        <line x1="0" x2="{{ $w }}" y1="{{ $h - $pad - (3 / $max) * ($h - 2 * $pad) }}" y2="{{ $h - $pad - (3 / $max) * ($h - 2 * $pad) }}"
              stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3 3" />
        @if (count($filled) > 1)
            <polyline points="{{ $poly }}" fill="none" stroke="{{ $stroke }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        @endif
        @foreach ($filled as $c)
            <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="2.5" fill="{{ $stroke }}" />
        @endforeach
    </svg>
@endif
