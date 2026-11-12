@extends('layouts.app')
@section('title', $patient->name)
@section('content')
    <a href="{{ route('doctor.dashboard') }}" class="text-base text-teal-700 underline">← My patients</a>
    <h1 class="mt-2 text-2xl font-bold">{{ $patient->name }}</h1>
    <dl class="mt-4 grid max-w-md grid-cols-2 gap-2 rounded-lg bg-white p-4 shadow-sm">
        <dt class="text-slate-500">Age</dt><dd>{{ $patient->birthdate?->age ?? '—' }}</dd>
        <dt class="text-slate-500">Mobile</dt><dd>{{ $patient->phone ?? '—' }}</dd>
    </dl>

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach([['Last 14 days', $rate14], ['Last 30 days', $rate30]] as [$label, $s])
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <div class="text-3xl font-bold">{{ $s['rate'] === null ? '—' : rtrim(rtrim(number_format($s['rate'], 1), '0'), '.').'%' }}</div>
                <div class="text-base text-slate-500">Taken, {{ strtolower($label) }}</div>
            </div>
        @endforeach
        <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-3xl font-bold text-red-700">{{ $rate14['missed'] }}</div><div class="text-base text-slate-500">Missed, last 14 days</div></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-3xl font-bold">{{ $rate14['taken'] }}</div><div class="text-base text-slate-500">Taken, last 14 days</div></div>
    </div>

    @if($alerts->isNotEmpty())
        <h2 class="mt-8 text-xl font-semibold">Alerts</h2>
        <ul class="mt-3 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
            @foreach($alerts as $alert)
                <li class="flex flex-wrap items-center justify-between gap-3 p-4 {{ $alert->isOpen() ? 'bg-red-50' : '' }}">
                    <div>
                        <div class="font-semibold">
                            <span class="mr-1 rounded px-2 py-0.5 text-sm {{ $alert->type === 'emergency' ? 'bg-red-600 text-white' : 'bg-amber-200 text-amber-900' }}">{{ ucfirst($alert->type) }}</span>
                            {{ $alert->message }}
                        </div>
                        <div class="text-base text-slate-500">
                            {{ $alert->created_at->format('M j, g:i A') }}
                            @if($alert->acknowledged_at) · seen by {{ $alert->acknowledgedBy?->name ?? 'a doctor' }}@endif
                            @unless($alert->isOpen()) · closed ({{ ['resumed' => 'patient took a dose', 'improved' => 'risk improved', 'doctor' => 'resolved by doctor'][$alert->resolution] ?? 'closed' }})@endunless
                        </div>
                    </div>
                    @if($alert->isOpen())
                        <div class="flex gap-2">
                            @unless($alert->acknowledged_at)
                                <form method="POST" action="{{ route('doctor.alerts.acknowledge', $alert) }}">@csrf @method('PATCH')
                                    <button class="rounded border border-slate-300 px-3 py-1.5 text-base hover:bg-slate-100">Acknowledge</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('doctor.alerts.resolve', $alert) }}">@csrf @method('PATCH')
                                <button class="rounded bg-teal-700 px-3 py-1.5 text-base text-white hover:bg-teal-800">Mark resolved</button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mt-8 text-xl font-semibold">Adherence risk</h2>
    <div class="mt-3 rounded-lg bg-white p-5 shadow-sm">
        @if($risk && $risk->hasEnoughData())
            <div class="flex flex-wrap items-center gap-3">
                @include('partials.risk-badge', ['risk' => $risk])
                <span class="text-2xl font-bold">{{ rtrim(rtrim(number_format($risk->score, 1), '0'), '.') }}<span class="text-base font-normal text-slate-500"> / 100</span></span>
                <span class="text-base text-slate-500">as of {{ $risk->computed_at->format('M j, g:i A') }}</span>
            </div>
            <ul class="mt-4 space-y-2">
                @foreach($risk->factors['factors'] as $factor)
                    <li>
                        <div class="flex justify-between text-base"><span>{{ $factor['label'] }}</span><span class="text-slate-500">{{ $factor['points'] }} / {{ $factor['max'] }}</span></div>
                        <div class="mt-1 h-2 rounded bg-slate-100"><div class="h-2 rounded {{ $factor['points'] > 0 ? 'bg-amber-500' : 'bg-slate-200' }}" style="width: {{ $factor['max'] ? min(100, $factor['points'] / $factor['max'] * 100) : 0 }}%"></div></div>
                        <div class="text-sm text-slate-500">{{ $factor['detail'] }}</div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-slate-600">Not enough intake data yet to estimate risk. A score appears once the patient has at least {{ config('medimind.risk.min_doses') }} recorded doses in the last 14 days.</p>
        @endif
        <p class="mt-4 text-sm text-slate-500">A simple estimate from the patient's recorded doses, to help you decide who may need follow-up. It is not a diagnosis, and it can be wrong, especially with little data.</p>
    </div>

    @if($interactions->isNotEmpty())
        <div class="mt-6 rounded border border-amber-300 bg-white px-4 py-3" role="alert">
            <h2 class="mb-2 font-semibold text-amber-900">Possible interactions in this patient's list</h2>
            @include('partials.interactions', ['interactions' => $interactions])
        </div>
    @endif

    <h2 class="mt-8 text-xl font-semibold">Registered medicines</h2>
    <p class="text-base text-slate-500">You can verify and correct entries. MediMind does not prescribe.</p>
    <ul class="mt-3 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($medicines as $medicine)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div>
                    <div class="font-semibold">{{ $medicine->name }} <span class="font-normal text-slate-500">· {{ $medicine->dosage }}</span>
                        @unless($medicine->active)<span class="ml-1 rounded bg-slate-200 px-2 py-0.5 text-sm">Inactive</span>@endunless
                    </div>
                    <div class="text-base text-slate-500">
                        {{ $medicine->category?->name ?? 'Uncategorized' }} · from {{ $medicine->start_date->format('M j, Y') }}@if($medicine->end_date) to {{ $medicine->end_date->format('M j, Y') }}@endif
                        @if($medicine->updatedBy) · last updated by {{ $medicine->updatedBy->name }}@endif
                    </div>
                    <div class="text-base text-slate-700">{{ $medicine->schedule ? $medicine->schedule->summary() : 'No schedule set' }}</div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('doctor.schedule.edit', [$patient, $medicine]) }}" class="rounded border border-slate-300 px-3 py-1.5 text-base hover:bg-slate-100">Schedule</a>
                    <a href="{{ route('doctor.medicines.edit', [$patient, $medicine]) }}" class="rounded border border-slate-300 px-3 py-1.5 text-base hover:bg-slate-100">Review / edit</a>
                </div>
            </li>
        @empty
            <li class="p-4 text-slate-500">This patient has not registered any medicines yet.</li>
        @endforelse
    </ul>
    <h2 class="mt-8 text-xl font-semibold">Adherence over time</h2>
    <div class="mt-3">@include('partials.weekly-chart', ['weeks' => $weeks])</div>

    <div class="mt-8 flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">Recent doses</h2>
        <a href="{{ route('doctor.patients.history', $patient) }}" class="text-base text-teal-700 underline">Full intake history</a>
    </div>
    <ul class="mt-3 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($recentDoses as $dose)
            <li class="flex items-center justify-between gap-3 p-4">
                <div>
                    <span class="font-semibold">{{ $dose->medicine->name }}</span>
                    <span class="text-base text-slate-500">· {{ $dose->scheduled_at->format('D, M j · g:i A') }}@if($dose->confirmed_at) · taken at {{ $dose->confirmed_at->format('g:i A') }}@endif</span>
                </div>
                <span class="rounded px-2 py-0.5 text-base {{ match($dose->status) { 'taken' => 'bg-teal-100 text-teal-800', 'missed' => 'bg-red-100 text-red-800', default => 'bg-slate-100 text-slate-700' } }}">{{ ucfirst($dose->status) }}</span>
            </li>
        @empty
            <li class="p-4 text-slate-500">No doses recorded yet.</li>
        @endforelse
    </ul>
@endsection
