@php
    use App\Http\Controllers\ClaudeActivityController;
    use Illuminate\Support\Carbon;

    $name = $newest?->hostname ?? 'Unknown host';
    $noId = $device === ClaudeActivityController::NO_DEVICE;
    $firstSeen = Carbon::parse($summary->first_seen);
    $lastSeen = Carbon::parse($summary->last_seen);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$name"
                       :description="$noId ? 'Events that arrived without a device id.' : collect([$newest?->os_user, $newest?->platform])->filter()->implode(' · ').' · device '.$device"
                       :breadcrumbs="['Claude activity' => route('claude-activity.index'), $name => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            <div class="stat-strip">
                <x-stat label="Events" :value="number_format($summary->events)" icon="command-line" tone="slate"
                        :sub="$summary->browser_actions ? number_format($summary->browser_actions).' in the browser' : null" />
                <x-stat label="Prompts" :value="number_format($summary->prompts)" icon="chat-bubbles" tone="info" />
                <x-stat label="Sessions" :value="number_format($summary->sessions)" icon="clock" tone="violet"
                        :sub="'Since '.$firstSeen->format('M j, Y')" />
                <x-stat label="Last seen" :value="$lastSeen->diffForHumans()" icon="globe" tone="success"
                        :sub="$lastSeen->format('M j, Y H:i')" />
            </div>

            <section class="card" aria-labelledby="device-where">
                <div class="card-header">
                    <h2 id="device-where" class="card-title">Where it reported from</h2>
                </div>
                <div class="grid gap-6 p-4 sm:p-6 md:grid-cols-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">IP addresses ({{ $ips->count() }})</h3>
                        @if ($ips->isEmpty())
                            <p class="mt-2 text-sm text-slate-500">None recorded.</p>
                        @else
                            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                                @foreach ($ips as $row)
                                    <li class="flex items-center justify-between gap-3 py-1.5">
                                        <a href="{{ ClaudeActivityController::deviceUrl($newest?->device_id, ['ip' => $row->ip]) }}"
                                           class="font-mono text-slate-700 hover:underline" title="Only events from this IP">{{ $row->ip }}</a>
                                        <span class="whitespace-nowrap text-xs text-slate-500">
                                            {{ number_format($row->events) }} {{ \Illuminate\Support\Str::plural('event', $row->events) }} ·
                                            last {{ Carbon::parse($row->last_seen)->diffForHumans() }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Models ({{ $models->count() }})</h3>
                        @if ($models->isEmpty())
                            <p class="mt-2 text-sm text-slate-500">None recorded yet.</p>
                        @else
                            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                                @foreach ($models as $row)
                                    <li class="flex items-center justify-between gap-3 py-1.5">
                                        <a href="{{ ClaudeActivityController::deviceUrl($newest?->device_id, ['model' => $row->model]) }}"
                                           class="font-mono text-slate-700 hover:underline" title="Only events with this model">{{ $row->model }}</a>
                                        <span class="whitespace-nowrap text-xs text-slate-500">
                                            {{ number_format($row->events) }} {{ \Illuminate\Support\Str::plural('event', $row->events) }} ·
                                            last {{ Carbon::parse($row->last_seen)->diffForHumans() }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Computer names ({{ $hostNames->count() }})</h3>
                        @if ($hostNames->isEmpty())
                            <p class="mt-2 text-sm text-slate-500">None recorded.</p>
                        @else
                            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                @foreach ($hostNames as $host)
                                    <li>{{ $host }}@if ($host === $newest?->hostname) <span class="text-xs text-slate-500">(latest)</span>@endif</li>
                                @endforeach
                            </ul>
                        @endif
                        <h3 class="mt-5 text-sm font-semibold text-slate-900">MAC addresses ({{ $macs->count() }})</h3>
                        @if ($macs->isEmpty())
                            <p class="mt-2 text-sm text-slate-500">None recorded yet.</p>
                        @else
                            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                                @foreach ($macs as $row)
                                    <li class="flex items-center justify-between gap-3 py-1.5">
                                        <a href="{{ ClaudeActivityController::deviceUrl($newest?->device_id, ['mac' => $row->mac_address]) }}"
                                           class="font-mono text-slate-700 hover:underline" title="Only events from this MAC address">{{ $row->mac_address }}</a>
                                        <span class="whitespace-nowrap text-xs text-slate-500">last {{ Carbon::parse($row->last_seen)->diffForHumans() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </section>

            @include('claude-activity._events', [
                'action' => ClaudeActivityController::deviceUrl($noId ? null : $device),
                'device' => $device,
            ])
        </div>
    </div>
</x-app-layout>
