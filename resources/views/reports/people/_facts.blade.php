{{-- One group of a person's figures as a label and value list. --}}
@php use App\Support\PeopleReportColumns as Columns; @endphp
<section class="card min-w-0" aria-labelledby="facts-{{ $group }}">
    <div class="card-header"><h2 id="facts-{{ $group }}" class="card-title">{{ Columns::GROUPS[$group] }}</h2></div>
    <dl class="divide-y divide-slate-100">
        @foreach (Columns::visible($group) as $column)
            @php $few = Columns::tooFew($column, $record); @endphp
            <div class="flex items-baseline justify-between gap-4 px-5 py-2 text-sm" @isset($column['hint']) title="{{ $column['hint'] }}" @endisset>
                <dt class="text-slate-600">{{ $column['label'] }}</dt>
                @if ($few !== null)
                    <dd class="whitespace-nowrap text-xs text-slate-500">Too few ({{ $few }} {{ Str::plural($column['unit'] ?? 'item', $few) }})</dd>
                @else
                    <dd class="tabular whitespace-nowrap font-medium text-slate-800">{{ Columns::display($column, $record) }}</dd>
                @endif
            </div>
        @endforeach
    </dl>
    {{ $slot ?? '' }}
</section>
