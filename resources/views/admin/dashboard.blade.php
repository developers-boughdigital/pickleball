<x-admin.layout title="Overview" :breadcrumb="['Dashboard', 'Overview']">
    <x-admin.page-header :title="$greeting.', '.$firstName" subtitle="Here’s what’s happening across clubs, sessions and players today.">
        <button type="button" class="btn-outline">
            <x-icon name="calendar" class="text-slate-600" />
            {{ now()->format('D, j M Y') }}
        </button>
        <button type="button" class="btn-gold">
            <x-icon name="download" />
            Export report
        </button>
    </x-admin.page-header>

    {{-- KPI cards --}}
    <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            @php
                [$noteClass, $iconClass, $stroke] = match ($stat['tone']) {
                    'green' => ['text-emerald-600', 'text-brand-500', '#eeb008'],
                    'gold' => ['text-brand-500', 'text-brand-500', '#eeb008'],
                    'red' => ['text-red-600', 'text-red-600', '#dc2626'],
                };
            @endphp
            <div class="card p-6">
                <div class="flex items-center justify-between">
                    <p class="text-[15px] text-slate-500">{{ $stat['label'] }}</p>
                    <x-icon :name="$stat['icon']" :class="$iconClass" />
                </div>
                <p class="mt-4 text-3xl font-bold tracking-tight 2xl:text-4xl">{{ $stat['value'] }}</p>
                <div class="mt-4 flex items-end justify-between gap-4">
                    <p class="text-sm font-medium {{ $noteClass }}">{{ $stat['note'] }}</p>
                    <svg viewBox="0 0 100 26" class="h-7 w-24 shrink-0" fill="none" aria-hidden="true">
                        <polyline points="{{ $stat['spark'] }}" stroke="{{ $stroke }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
        {{-- Bookings --}}
        <section class="card min-w-0 p-6 sm:p-10">
            <h2 class="text-xl font-semibold">Bookings and check-ins</h2>

            <div x-data="{ range: '30 days' }" role="tablist" class="mt-4 inline-flex rounded-lg bg-slate-100 p-1">
                @foreach (['7 days', '30 days', '90 days'] as $range)
                    <button
                        type="button"
                        role="tab"
                        @click="range = @js($range)"
                        :aria-selected="(range === @js($range)).toString()"
                        class="rounded-md px-3.5 py-1.5 text-[15px] font-medium text-slate-700 transition aria-selected:bg-white aria-selected:text-ink-950 aria-selected:shadow-sm"
                    >
                        {{ $range }}
                    </button>
                @endforeach
            </div>

            <div class="mt-8 flex gap-3">
                <div class="flex h-72 flex-col justify-between pb-1 text-xs text-slate-500 sm:text-sm">
                    @foreach (['250', '200', '150', '100', '50', '0'] as $tick)
                        <span class="-translate-y-1/2 first:translate-y-0 last:translate-y-0">{{ $tick }}</span>
                    @endforeach
                </div>

                <div class="min-w-0 flex-1">
                    <div class="relative h-72">
                        <svg viewBox="0 0 600 300" preserveAspectRatio="none" class="absolute inset-0 size-full" aria-label="Daily bookings and check-ins over the last 30 days">
                            @foreach ([0, 60, 120, 180, 240] as $y)
                                <line x1="0" x2="600" y1="{{ $y }}" y2="{{ $y }}" stroke="#e2e8f0" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
                            @endforeach
                            <line x1="0" x2="600" y1="300" y2="300" stroke="#e2e8f0" vector-effect="non-scaling-stroke" />

                            @php($sales = 'M0,204 C40,170 70,150 100,151 C140,152 170,172 200,168 C240,163 260,110 300,96 C330,86 350,80 380,72 C420,60 440,45 470,36 C520,24 560,20 600,4')
                            <path d="{{ $sales }} L600,300 L0,300 Z" fill="#fef8e8" />
                            <path d="{{ $sales }}" fill="none" stroke="#eeb008" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                            <path d="M0,252 C40,245 70,241 100,242 C140,243 170,250 200,245 C240,240 260,232 300,228 C340,222 370,218 400,214 C440,209 470,207 500,204 C540,199 570,193 600,187"
                                  fill="none" stroke="#d97706" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                        </svg>

                        <div class="absolute top-2 right-4 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm whitespace-nowrap shadow-sm">
                            {{ now()->format('j M') }} · 248 bookings
                        </div>
                    </div>

                    <div class="mt-3 flex justify-between text-xs text-slate-500 sm:text-sm">
                        @foreach ($chartLabels as $label)
                            <span @class(['hidden sm:inline' => in_array($loop->index, [1, 3])])>{{ $label }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 flex gap-8 text-[15px] text-slate-600">
                <span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-brand-500"></span>Bookings</span>
                <span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-amber-600"></span>Check-ins</span>
            </div>
        </section>

        {{-- Coming up --}}
        <section class="card min-w-0 p-6 sm:p-10">
            <h2 class="text-xl font-semibold">Coming up this week</h2>
            <a href="#" class="mt-1 inline-block text-[15px] font-medium text-brand-500 hover:underline">Open calendar</a>

            <ul class="mt-6 divide-y divide-slate-100">
                @foreach ($upcomingSessions as $session)
                    <li class="flex items-start justify-between gap-4 py-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-0.5 grid size-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                                <x-icon name="calendar" />
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium">{{ $session['title'] }} · {{ $session['club'] }}</p>
                                <p class="text-sm text-slate-500">{{ $session['when'] }} · {{ $session['venue'] }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $session['level'] }}</p>
                            </div>
                        </div>
                        <span @class([
                            'shrink-0 text-sm font-semibold',
                            'text-red-600' => $session['spots'] === 'Waitlist',
                        ])>{{ $session['spots'] }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Listing claims --}}
        <section class="card min-w-0 p-6 sm:p-10">
            <h2 class="text-xl font-semibold">Listing claims queue</h2>

            <div class="mt-6 flex items-center gap-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-[15px] text-brand-700">
                <x-icon name="triangle-alert" />
                2 claims have been waiting more than 48 hours
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-[15px]">
                    <thead class="text-slate-500">
                        <tr class="border-b border-slate-200">
                            <th class="py-3 pr-4 font-medium">Listing</th>
                            <th class="py-3 pr-4 font-medium">Type</th>
                            <th class="py-3 pr-4 font-medium">Claimant</th>
                            <th class="py-3 pr-4 font-medium">Status</th>
                            <th class="py-3 text-right font-medium">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($claims as $claim)
                            <tr>
                                <td class="py-5 pr-4 font-medium">{{ $claim['listing'] }}</td>
                                <td class="py-5 pr-4 text-slate-500">{{ $claim['type'] }}</td>
                                <td class="py-5 pr-4 text-slate-500">{{ $claim['claimant'] }}</td>
                                <td class="py-5 pr-4">
                                    <span class="{{ $claim['status'] === 'Pending review' ? 'badge-gold' : 'badge-muted' }}">{{ $claim['status'] }}</span>
                                </td>
                                <td class="py-5 text-right text-slate-500">{{ $claim['submitted'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Recent activity --}}
        <section class="card min-w-0 p-6 sm:p-10">
            <h2 class="text-xl font-semibold">Recent activity</h2>
            <a href="{{ route('admin.activity') }}" class="mt-1 inline-block text-[15px] font-medium text-brand-500 hover:underline">View audit log</a>

            <ul class="mt-6 space-y-6">
                @foreach ($activity as $item)
                    <li class="flex items-start gap-4">
                        @if ($item['initials'])
                            <x-admin.avatar :initials="$item['initials']" :tone="$item['tone']" class="mt-1" />
                        @else
                            <span class="mt-1 grid size-11 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-600">
                                <x-icon name="bot" />
                            </span>
                        @endif
                        <div>
                            <p class="text-base leading-snug sm:text-[17px]">{{ $item['text'] }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $item['time'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-admin.layout>
