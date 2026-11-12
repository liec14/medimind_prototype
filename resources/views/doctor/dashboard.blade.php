@extends('layouts.app')
@section('title', 'My patients')
@section('content')
    <h1 class="text-2xl font-bold">My patients</h1>

    <div class="mt-4 grid grid-cols-3 gap-3">
        <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-3xl font-bold">{{ $totals['patients'] }}</div><div class="text-base text-slate-500">Linked patients</div></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-3xl font-bold {{ $totals['high'] ? 'text-red-700' : '' }}">{{ $totals['high'] }}</div><div class="text-base text-slate-500">At high risk</div></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-3xl font-bold {{ $totals['alerts'] ? 'text-red-700' : '' }}">{{ $totals['alerts'] }}</div><div class="text-base text-slate-500">Open alerts</div></div>
    </div>

    @if($pending->isNotEmpty())
        <h2 class="mt-6 font-semibold">Link requests</h2>
        <ul class="mt-2 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
            @foreach($pending as $patient)
                <li class="flex items-center justify-between gap-3 p-4">
                    <span class="font-semibold">{{ $patient->name }}</span>
                    <form method="POST" action="{{ route('doctor.patients.respond', $patient) }}" class="flex gap-2">
                        @csrf @method('PATCH')
                        <button name="decision" value="accept" class="rounded bg-teal-700 px-3 py-1.5 text-base text-white hover:bg-teal-800">Accept</button>
                        <button name="decision" value="decline" class="rounded border border-slate-300 px-3 py-1.5 text-base hover:bg-slate-100">Decline</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mt-6 font-semibold">Linked patients</h2>
    <ul class="mt-2 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($active as $patient)
            <li>
                <a href="{{ route('doctor.patients.show', $patient) }}" class="flex flex-wrap items-center justify-between gap-2 p-4 hover:bg-teal-50">
                    <span class="font-semibold">{{ $patient->name }}</span>
                    <span class="flex flex-wrap items-center gap-2">
                        @php $r = $rates[$patient->id]['rate'] ?? null; @endphp
                        <span class="text-base text-slate-600">{{ $r === null ? 'No doses yet' : rtrim(rtrim(number_format($r, 1), '0'), '.').'% taken (14 days)' }}</span>
                        @if($patient->open_alerts)<span class="rounded bg-red-600 px-2 py-0.5 text-sm text-white">{{ $patient->open_alerts }} open alert{{ $patient->open_alerts > 1 ? 's' : '' }}</span>@endif
                        @include('partials.risk-badge', ['risk' => $patient->latestRisk])
                    </span>
                </a>
            </li>
        @empty
            <li class="p-4 text-slate-500">No linked patients yet.</li>
        @endforelse
    </ul>
@endsection
