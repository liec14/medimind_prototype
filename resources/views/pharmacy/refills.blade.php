@extends('layouts.app')
@section('title', 'Refills due')
@section('content')
    <h1 class="text-2xl font-bold">Refills due</h1>
    <p class="mt-2 text-slate-600">
        Patients who agreed to share with {{ $pharmacy->name }}, whose supply runs out within {{ config('medimind.refill.due_days') }} days or already has.
        Messages are sent through MediMind, so you never see the patient's email or phone number.
    </p>

    <ul class="mt-6 space-y-3">
        @forelse($rows as $row)
            @php
                $badge = ['stopped' => ['May have stopped refilling', 'bg-red-100 text-red-800'], 'overdue' => ['Overdue', 'bg-amber-100 text-amber-800'], 'due_soon' => ['Due soon', 'bg-teal-100 text-teal-800']][$row['status']];
                $when = $row['days_left'] < 0 ? abs($row['days_left']).' day'.(abs($row['days_left']) === 1 ? '' : 's').' ago' : ($row['days_left'] === 0 ? 'today' : 'in '.$row['days_left'].' day'.($row['days_left'] === 1 ? '' : 's'));
            @endphp
            <li class="rounded-lg bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="text-lg font-semibold">{{ $row['patient']->name }}</span>
                        <span class="text-slate-500">· {{ $row['medicine']->name }}</span>
                    </div>
                    <span class="rounded px-2 py-0.5 text-sm font-medium {{ $badge[1] }}">{{ $badge[0] }}</span>
                </div>
                <div class="text-base text-slate-500">
                    Supply runs out {{ $row['due_on']->format('M j, Y') }} ({{ $when }})
                    @if($row['last_followup']) · last messaged {{ $row['last_followup']->diffForHumans() }}@endif
                </div>

                <form method="POST" action="{{ route('pharmacy.refills.followup', $row['medicine']) }}" class="mt-3 space-y-2">
                    @csrf
                    <label class="block text-base">Message to patient
                        <textarea name="message" rows="3" maxlength="500" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">{{ old('message', $row['default_message']) }}</textarea>
                    </label>
                    <button class="rounded bg-teal-700 px-5 py-2 font-semibold text-white hover:bg-teal-800">Send follow-up</button>
                </form>
            </li>
        @empty
            <li class="rounded-lg bg-white p-5 text-slate-500 shadow-sm">No refills due right now. Patients appear here once they share with your pharmacy and a supply date is set.</li>
        @endforelse
    </ul>
@endsection
