{{--
    A table of rows, each with a record. Every group's columns are rendered, and
    the surrounding `peopleTable` shows one group at a time.
    $table: name for sorting; $heading: first column; $name: closure rendering the first cell.
--}}
@php
    use App\Support\PeopleReportColumns as Columns;

    $firstGroup = array_key_first(Columns::GROUPS);
    $warn = ['overdue', 'blockedNow'];
@endphp
<div class="overflow-x-auto" data-table="{{ $table }}">
    <table class="table-base [&_td]:!px-3 [&_td]:!py-2.5 [&_th]:!px-3">
        <thead>
            <tr>
                <th scope="col" class="sticky left-0 z-10 min-w-[9rem] bg-slate-50 sm:min-w-[13rem]" :aria-sort="ariaSort('{{ $table }}', 'name')">
                    <button type="button" class="font-medium hover:text-slate-800" @click="sortBy('{{ $table }}', 'name', true)">{{ $heading }}</button>
                </th>
                @foreach (array_keys(Columns::GROUPS) as $group)
                    @foreach (Columns::visible($group) as $column)
                        <th scope="col" class="text-right" title="{{ $column['hint'] ?? '' }}" x-show="group === '{{ $group }}'" @unless ($group === $firstGroup) x-cloak @endunless
                            :aria-sort="ariaSort('{{ $table }}', '{{ $column['key'] }}')">
                            <button type="button" class="font-medium hover:text-slate-800" @click="sortBy('{{ $table }}', '{{ $column['key'] }}', {{ $column['type'] === 'text' ? 'true' : 'false' }})">{{ $column['label'] }}</button>
                        </th>
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="sticky left-0 z-10 bg-white" data-col="name" data-sort="{{ $sortName($row) }}">{{ $name($row) }}</td>
                    @foreach (array_keys(Columns::GROUPS) as $group)
                        @foreach (Columns::visible($group) as $column)
                            @php
                                $few = Columns::tooFew($column, $row['record']);
                                $value = Columns::value($column, $row['record']);
                            @endphp
                            <td data-col="{{ $column['key'] }}" data-sort="{{ $value }}"
                                x-show="group === '{{ $group }}'" @unless ($group === $firstGroup) x-cloak @endunless
                                @class(['tabular whitespace-nowrap text-right',
                                        'text-slate-400' => $value === 0 || $value === null || $value === 0.0,
                                        'font-medium text-warning-700' => $value > 0 && in_array($column['key'], $warn, true),
                                        'text-slate-700' => ! ($value === 0 || $value === null || $value === 0.0) && ! ($value > 0 && in_array($column['key'], $warn, true))])>
                                @if ($few !== null)
                                    <span class="text-xs text-slate-500" title="Only {{ $few }} {{ Str::plural($column['unit'] ?? 'item', $few) }}, too few to quote">Too few ({{ $few }})</span>
                                @else
                                    {{ Columns::display($column, $row['record']) }}
                                @endif
                            </td>
                        @endforeach
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        @isset($total)
            <tfoot class="border-t border-slate-200 bg-slate-50/80 text-sm font-medium">
                <tr>
                    <th scope="row" class="sticky left-0 z-10 bg-slate-50 !py-2.5 text-left font-semibold text-slate-700">{{ $totalLabel }}</th>
                    @foreach (array_keys(Columns::GROUPS) as $group)
                        @foreach (Columns::visible($group) as $column)
                            <td class="tabular whitespace-nowrap text-right text-slate-800" x-show="group === '{{ $group }}'" @unless ($group === $firstGroup) x-cloak @endunless>
                                @if (($few = Columns::tooFew($column, $total)) !== null)
                                    <span class="text-xs font-normal text-slate-500">Too few ({{ $few }})</span>
                                @else
                                    {{ Columns::display($column, $total) }}
                                @endif
                            </td>
                        @endforeach
                    @endforeach
                </tr>
            </tfoot>
        @endisset
    </table>
</div>
