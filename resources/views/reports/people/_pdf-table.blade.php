{{-- One group's figures as a plain table; dompdf supports a subset of CSS. --}}
@php use App\Support\PeopleReportColumns as Columns; @endphp
<table>
    <thead>
        <tr>
            <th>{{ $heading }}</th>
            @foreach (Columns::visible($group) as $column)
                <th class="num">{{ $column['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr @class(['total' => $row['total'] ?? false])>
                <td>{{ $row['label'] }}@if (! empty($row['sub']))<br><span class="muted">{{ $row['sub'] }}</span>@endif</td>
                @foreach (Columns::visible($group) as $column)
                    @php $few = Columns::tooFew($column, $row['record']); @endphp
                    <td class="num">@if ($few !== null)<span class="few">Too few ({{ $few }})</span>@else{{ Columns::display($column, $row['record']) }}@endif</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
