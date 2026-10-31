{{--
    Inline-SVG radar (spider) chart for category scores.
    @include('partials.radar', ['axes' => [['label'=>'Technical','value'=>4.1], ...], 'max' => 5])
    Each axis value may be null (treated as 0 for the shape). Needs 3+ axes.
--}}
@php
    use App\Support\ScoreColor;
    $axes = array_values($axes ?? []);
    $max = $max ?? 5;
    $size = $size ?? 240;
    $cx = $cy = $size / 2;
    $r = $size / 2 - 34;
    $n = count($axes);

    $overall = collect($axes)->pluck('value')->filter(fn ($v) => $v !== null);
    $fill = ScoreColor::hex($overall->isNotEmpty() ? (float) $overall->avg() : null);

    $pt = function (float $ratio, int $i) use ($cx, $cy, $r, $n) {
        $angle = -M_PI / 2 + $i * 2 * M_PI / $n;
        return [round($cx + cos($angle) * $r * $ratio, 1), round($cy + sin($angle) * $r * $ratio, 1)];
    };
@endphp

@if ($n < 3)
    <div class="flex h-40 items-center justify-center text-xs text-slate-300">Not enough categories to chart</div>
@else
    <svg viewBox="0 0 {{ $size }} {{ $size }}" class="mx-auto h-56 w-56" role="img" aria-label="Category breakdown">
        {{-- grid rings --}}
        @foreach ([0.25, 0.5, 0.75, 1.0] as $ring)
            @php $poly = collect(range(0, $n - 1))->map(fn ($i) => implode(',', $pt($ring, $i)))->implode(' '); @endphp
            <polygon points="{{ $poly }}" fill="none" stroke="#e2e8f0" stroke-width="1" />
        @endforeach
        {{-- spokes + labels --}}
        @foreach ($axes as $i => $axis)
            @php [$ex, $ey] = $pt(1, $i); [$lx, $ly] = $pt(1.18, $i); @endphp
            <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $ex }}" y2="{{ $ey }}" stroke="#e2e8f0" stroke-width="1" />
            <text x="{{ $lx }}" y="{{ $ly }}" text-anchor="middle" dominant-baseline="middle"
                  class="fill-slate-500" style="font-size: 9px; font-weight: 600;">{{ $axis['label'] }}</text>
        @endforeach
        {{-- value polygon --}}
        @php
            $valPoly = collect($axes)->map(function ($axis, $i) use ($pt, $max) {
                $ratio = $max > 0 ? (($axis['value'] ?? 0) / $max) : 0;
                return implode(',', $pt($ratio, $i));
            })->implode(' ');
        @endphp
        <polygon points="{{ $valPoly }}" fill="{{ $fill }}" fill-opacity="0.18" stroke="{{ $fill }}" stroke-width="2" />
        @foreach ($axes as $i => $axis)
            @if (($axis['value'] ?? null) !== null)
                @php [$vx, $vy] = $pt(($axis['value'] / $max), $i); @endphp
                <circle cx="{{ $vx }}" cy="{{ $vy }}" r="3" fill="{{ $fill }}" />
            @endif
        @endforeach
    </svg>
@endif
