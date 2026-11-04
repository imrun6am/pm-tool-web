{{-- Chooses which figures the tables below show. --}}
<div class="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1" role="group" aria-label="Figures to show">
    @foreach (\App\Support\PeopleReportColumns::GROUPS as $key => $label)
        <button type="button" @click="show('{{ $key }}')" :aria-pressed="group === '{{ $key }}'"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                :class="group === '{{ $key }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'">{{ $label }}</button>
    @endforeach
</div>
