{{--
    Inline-SVG trend line for a small series of scores.
    @include('partials.spark-line', ['points' => [['label'=>'Jul 1','value'=>3.2], ...], 'max' => 5])
    `value` may be null (gap). Line colour follows the latest non-null value.
    Optional: `color` (fixed stroke hex), `guide` (value of the dashed guide
    line, default 3 = "Meets"; false hides it), `label` (aria-label), and a
    per-point `title` (hover tooltip).
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
        $coords[] = ['x' => round($x, 1), 'y' => round($y, 1), 'v' => $v, 't' => $p['title'] ?? null];
    }
    $filled = array_values(array_filter($coords));
    $last = collect($points)->pluck('value')->filter(fn ($v) => $v !== null)->last();
    $stroke = $color ?? ScoreColor::hex($last !== null ? (float) $last : null);
    $guide = $guide ?? 3;
    $poly = collect($filled)->map(fn ($c) => $c['x'].','.$c['y'])->implode(' ');
@endphp

@if (empty($filled))
    <div class="flex h-16 items-center justify-center text-xs text-slate-300">No trend data yet</div>
@else
    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-16 w-full" preserveAspectRatio="none" role="img" aria-label="{{ $label ?? 'Score trend' }}">
        {{-- guide line, by default at "Meets" (3/5) --}}
        @if ($guide !== false)
            <line x1="0" x2="{{ $w }}" y1="{{ $h - $pad - ($guide / $max) * ($h - 2 * $pad) }}" y2="{{ $h - $pad - ($guide / $max) * ($h - 2 * $pad) }}"
                  stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3 3" />
        @endif
        @if (count($filled) > 1)
            <polyline points="{{ $poly }}" fill="none" stroke="{{ $stroke }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        @endif
        @foreach ($filled as $c)
            <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="2.5" fill="{{ $stroke }}">@if ($c['t'])<title>{{ $c['t'] }}</title>@endif</circle>
        @endforeach
    </svg>
@endif
