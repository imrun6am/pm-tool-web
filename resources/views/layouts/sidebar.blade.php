@php use App\Support\Navigation; use App\Support\Palette; @endphp
{{-- App-shell sidebar. One markup for both breakpoints: fixed rail on lg+
     (collapsible), off-canvas drawer below lg. Menu data comes from
     App\Support\Navigation via a view composer ($navGroups, $createActions);
     every icon wears its area's hue from App\Support\Palette — a soft tile at
     rest, a solid one when active. Lives inside the `appShell` Alpine scope
     (see layouts/app). --}}
<aside id="app-sidebar" class="sidebar" :class="{ 'is-open': mobileOpen }" aria-label="Sidebar">

    {{-- Workspace header --}}
    <div class="flex h-16 flex-none items-center gap-2 px-4 lg:sidebar-collapsed:justify-center lg:sidebar-collapsed:px-0">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 rounded-lg">
            <span class="brand-mark">PM</span>
            <span class="nav-label leading-tight">
                <span class="block text-[15px] font-bold tracking-tight text-slate-900">PM Tool</span>
                <span class="block text-[11px] font-medium text-slate-500">Plan · Track · Ship</span>
            </span>
        </a>
        <button type="button" x-ref="sidebarClose" @click="closeMobile()"
                class="btn-ghost btn-sm btn-icon ml-auto lg:hidden" aria-label="Close navigation">
            <x-icon name="x-mark" variant="outline" class="h-5 w-5" />
        </button>
    </div>

    {{-- Create menu --}}
    <div class="flex-none px-3 pb-4">
        <x-dropdown align="left" width="w-60" contentClasses="py-1.5 bg-white">
            <x-slot name="trigger">
                <button type="button" class="btn-primary sidebar-create w-full lg:sidebar-collapsed:px-0" aria-haspopup="menu"
                        @mouseenter="showTip($el)" @mouseleave="hideTip()" @focus="showTip($el)" @blur="hideTip()">
                    <x-icon name="plus" variant="outline" class="h-[18px] w-[18px]" />
                    <span class="nav-label">Create</span>
                </button>
            </x-slot>
            <x-slot name="content">
                <p class="px-3 pb-1.5 pt-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Create</p>
                @foreach ($createActions as $action)
                    <a href="{{ route($action['route']) }}"
                       class="mx-1.5 flex items-center gap-3 rounded-md px-2 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900">
                        <span @class(['nav-icon', Palette::soft($action['tone'])])>
                            <x-icon :name="$action['icon']" variant="outline" class="h-4 w-4" />
                        </span>
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </x-slot>
        </x-dropdown>
    </div>

    {{-- Primary navigation (the only scrolling region) --}}
    <nav aria-label="Main" class="scroll-area flex-1 overflow-y-auto px-3 pb-4" @scroll.passive="hideTip()">
        @foreach ($navGroups as $group)
            @php $headed = $group['key'] !== 'overview'; @endphp
            <div class="nav-group">
                @if ($headed)
                    <p id="nav-group-{{ $group['key'] }}" class="nav-group-label"><span>{{ $group['label'] }}</span></p>
                @endif
                <ul class="space-y-0.5" @if ($headed) aria-labelledby="nav-group-{{ $group['key'] }}" @endif>
                    @foreach ($group['items'] as $item)
                        @php $active = Navigation::isActive($item); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}"
                               @class(['nav-item group/nav', 'nav-item-active' => $active])
                               @if ($active) aria-current="page" @endif
                               @click="closeMobile()"
                               @mouseenter="showTip($el)" @mouseleave="hideTip()" @focus="showTip($el)" @blur="hideTip()">
                                <span @class([
                                    'nav-icon',
                                    Palette::solid($item['tone']).' shadow-sm' => $active,
                                    Palette::soft($item['tone']) => ! $active,
                                ])>
                                    <x-icon :name="$item['icon']" variant="outline" class="h-[18px] w-[18px]" />
                                </span>
                                <span class="nav-label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{-- Footer: shortcut hint + collapse toggle (desktop only) --}}
    <div class="hidden flex-none border-t border-sidebar-border p-3 lg:block">
        <p class="nav-label mb-2 flex items-center gap-1.5 px-2 text-[11px] text-slate-500" aria-hidden="true">
            <kbd class="kbd" x-text="modKey">⌘</kbd><kbd class="kbd">K</kbd> search
            <span class="mx-1 text-slate-300">·</span><kbd class="kbd">C</kbd> new task
            <span class="mx-1 text-slate-300">·</span><kbd class="kbd">?</kbd>
        </p>
        <button type="button" @click="toggleCollapse()" class="nav-item"
                aria-controls="app-sidebar" :aria-pressed="collapsed.toString()" aria-label="Collapse sidebar"
                @mouseenter="showTip($el)" @mouseleave="hideTip()" @focus="showTip($el)" @blur="hideTip()">
            <span class="nav-icon bg-slate-100 text-slate-500">
                <x-icon name="chevron-double-left" variant="outline"
                        class="h-4 w-4 transition-transform duration-200 motion-reduce:transition-none lg:sidebar-collapsed:rotate-180" />
            </span>
            <span class="nav-label" x-text="collapsed ? 'Expand' : 'Collapse'">Collapse</span>
        </button>
    </div>
</aside>

{{-- Shared tooltip for the collapsed rail. Fixed-positioned so the nav's
     scroll container cannot clip it; purely visual (labels stay in the DOM
     as each control's accessible name). --}}
<div x-show="tip.show" x-cloak aria-hidden="true"
     class="pointer-events-none fixed left-[4.9rem] z-[60] -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg"
     :style="{ top: tip.top + 'px' }" x-text="tip.text"></div>
