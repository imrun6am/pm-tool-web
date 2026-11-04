{{-- The headline figures. `$extra` adds the "now" counts on the report. --}}
@php
    use App\Support\PeopleReportColumns as Columns;

    $find = fn (string $key) => collect(Columns::all())->firstWhere('key', $key);
    $hoursChange = $compare['logged']['change'] === null ? null : round($compare['logged']['change'] / 60, 1);
    $points = fn (?int $change) => abs((int) $change) === 1 ? ' pt' : ' pts';
@endphp
<dl class="card grid grid-cols-2 gap-x-4 gap-y-5 p-5 sm:grid-cols-3 lg:grid-cols-6">
    @include('reports.people._tile', ['column' => $find('completed'), 'record' => $record, 'change' => $compare['completed']['change']])
    @include('reports.people._tile', ['column' => $find('onTime'), 'record' => $record, 'change' => $compare['onTime']['change'], 'deltaUnit' => $points($compare['onTime']['change'])])
    @include('reports.people._tile', ['column' => $find('cycle'), 'record' => $record, 'change' => $compare['cycle']['change'], 'deltaUnit' => ' d', 'lowerIsBetter' => true])
    @include('reports.people._tile', ['column' => $find('logged'), 'record' => $record, 'label' => 'Time logged', 'change' => $hoursChange, 'deltaUnit' => 'h'])
    @include('reports.people._tile', ['column' => $find('rate'), 'record' => $record, 'label' => 'Tasksheet filled', 'change' => $compare['tasksheet']['change'], 'deltaUnit' => $points($compare['tasksheet']['change'])])
    @include('reports.people._tile', ['column' => $find('rating'), 'record' => $record, 'change' => $compare['rating']['change']])
</dl>
