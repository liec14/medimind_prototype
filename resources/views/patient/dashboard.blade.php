@extends('layouts.app')
@section('title', 'Today')
@section('content')
    <h1 class="text-2xl font-bold">Hello, {{ auth()->user()->name }}</h1>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-3xl font-bold text-teal-700">{{ $counts['taken'] }}</div><div class="text-base text-slate-500">Taken today</div></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-3xl font-bold">{{ $counts['pending'] }}</div><div class="text-base text-slate-500">Still to take</div></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-3xl font-bold text-red-700">{{ $counts['missed'] }}</div><div class="text-base text-slate-500">Missed today</div></div>
        <a href="{{ route('patient.history') }}" class="rounded-lg bg-white p-4 shadow-sm hover:bg-teal-50">
            <div class="text-3xl font-bold">{{ $week['rate'] === null ? '—' : rtrim(rtrim(number_format($week['rate'], 1), '0'), '.').'%' }}</div>
            <div class="text-base text-slate-500">Taken on time, last 7 days</div>
        </a>
    </div>

    @foreach($suggestions as $s)
        <div class="mt-6 rounded-lg border border-teal-200 bg-teal-50 p-4">
            <div class="font-semibold">A tip for your {{ $s->slot }} {{ $s->medicine->name }}</div>
            <p class="mt-1">You often miss your {{ $s->slot }} doses of {{ $s->medicine->name }}. Would you like the reminder {{ $s->shift_minutes }} minutes earlier?</p>
            <div class="mt-3 flex gap-2">
                <form method="POST" action="{{ route('patient.suggestions.accept', $s) }}">@csrf
                    <button class="rounded bg-teal-700 px-4 py-2 font-semibold text-white hover:bg-teal-800">Yes, remind me earlier</button>
                </form>
                <form method="POST" action="{{ route('patient.suggestions.dismiss', $s) }}">@csrf
                    <button class="rounded border border-slate-300 bg-white px-4 py-2 hover:bg-slate-100">No thanks</button>
                </form>
            </div>
        </div>
    @endforeach

    <h2 class="mt-8 text-xl font-semibold">Today's doses <span class="text-base font-normal text-slate-500">· {{ now()->format('l, F j') }}</span></h2>
    <ul class="mt-3 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($today as $dose)
            @php
                $due = $dose->scheduled_at->lte(now());
                $canSnooze = $due && $dose->snoozed_count < config('medimind.dose.max_snoozes');
                $snoozing = $dose->snoozed_until && $dose->snoozed_until->gt(now());
            @endphp
            <li class="flex flex-wrap items-center justify-between gap-3 p-4 {{ $dose->isPending() && $due ? 'bg-amber-50' : '' }}">
                <div>
                    <span class="font-semibold">{{ $dose->scheduled_at->format('g:i A') }}</span>
                    · {{ $dose->medicine->name }} <span class="text-slate-500">({{ $dose->medicine->dosage }})</span>
                    @if($dose->isPending() && $due)
                        <div class="text-base font-medium text-amber-800">{{ $snoozing ? 'Reminding you again at '.$dose->snoozed_until->format('g:i A') : 'Due now' }}</div>
                    @elseif($dose->status === 'taken')
                        <div class="text-base text-slate-500">Taken at {{ $dose->confirmed_at->format('g:i A') }}</div>
                    @endif
                </div>
                @if($dose->isPending())
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('patient.doses.confirm', $dose) }}">@csrf
                            <button class="rounded bg-teal-700 px-4 py-2 font-semibold text-white hover:bg-teal-800">I took it</button>
                        </form>
                        @if($canSnooze)
                            <form method="POST" action="{{ route('patient.doses.snooze', $dose) }}">@csrf
                                <button class="rounded border border-slate-300 px-4 py-2 hover:bg-slate-100">Remind me in {{ config('medimind.dose.snooze_minutes') }} min</button>
                            </form>
                        @endif
                    </div>
                @else
                    <span class="rounded px-2 py-0.5 text-base {{ $dose->status === 'taken' ? 'bg-teal-100 text-teal-800' : 'bg-red-100 text-red-800' }}">{{ ucfirst($dose->status) }}</span>
                @endif
            </li>
        @empty
            <li class="p-4 text-slate-500">
                @if($hasMedicines)
                    No doses today. Check that each medicine has a <a href="{{ route('patient.medicines.index') }}" class="text-teal-700 underline">schedule</a>.
                @else
                    No medicines yet. <a href="{{ route('patient.medicines.create') }}" class="text-teal-700 underline">Add your first medicine</a> to get started.
                @endif
            </li>
        @endforelse
    </ul>

    <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('patient.medicines.index') }}" class="rounded-lg bg-white p-5 shadow-sm hover:bg-teal-50"><span class="font-semibold">My medicines</span><br><span class="text-base text-slate-500">Register medicines and set their schedules</span></a>
        <a href="{{ route('patient.history') }}" class="rounded-lg bg-white p-5 shadow-sm hover:bg-teal-50"><span class="font-semibold">History</span><br><span class="text-base text-slate-500">See which doses you took or missed</span></a>
        <a href="{{ route('patient.doctors') }}" class="rounded-lg bg-white p-5 shadow-sm hover:bg-teal-50"><span class="font-semibold">My doctors</span><br><span class="text-base text-slate-500">Invite your doctor to follow your progress</span></a>
        <a href="{{ route('patient.pharmacies') }}" class="rounded-lg bg-white p-5 shadow-sm hover:bg-teal-50"><span class="font-semibold">Pharmacies</span><br><span class="text-base text-slate-500">Choose which pharmacies may see your refill dates</span></a>
        <a href="{{ route('patient.ask') }}" class="rounded-lg bg-white p-5 shadow-sm hover:bg-teal-50"><span class="font-semibold">Ask a pharmacist</span><br><span class="text-base text-slate-500">Questions about taking medicines safely</span></a>
    </div>
@endsection
