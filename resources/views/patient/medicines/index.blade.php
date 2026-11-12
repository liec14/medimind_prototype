@extends('layouts.app')
@section('title', 'My medicines')
@section('content')
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">My medicines</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('patient.routine.edit') }}" class="text-base text-teal-700 underline">My daily routine</a>
            <a href="{{ route('patient.medicines.create') }}" class="rounded bg-teal-700 px-5 py-2.5 font-semibold text-white hover:bg-teal-800">Add medicine</a>
        </div>
    </div>

    @if($interactions->isNotEmpty())
        <div class="mt-6 rounded border border-amber-300 bg-white px-4 py-3" role="alert">
            <h2 class="mb-2 font-semibold text-amber-900">
                @if(session('new_interactions'))The medicine you just saved may not mix well with another one you take. @endif
                Possible interactions in your list
            </h2>
            @include('partials.interactions', ['interactions' => $interactions])
        </div>
    @endif

    @foreach(['Currently taking' => $active, 'No longer taking' => $inactive] as $heading => $list)
        @continue($list->isEmpty() && $heading !== 'Currently taking')
        <h2 class="mt-8 font-semibold">{{ $heading }}</h2>
        <ul class="mt-2 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
            @forelse($list as $medicine)
                <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div>
                        <div class="font-semibold">{{ $medicine->name }} <span class="font-normal text-slate-500">· {{ $medicine->dosage }}</span></div>
                        <div class="text-base text-slate-500">
                            <span class="rounded bg-teal-50 px-2 py-0.5 text-teal-800">{{ $medicine->category?->name ?? 'Uncategorized' }}</span>
                            @if($medicine->purpose) · {{ $medicine->purpose }}@endif
                        </div>
                        <div class="text-base {{ $medicine->schedule ? 'text-slate-700' : 'text-amber-700' }}">
                            {{ $medicine->schedule ? $medicine->schedule->summary() : 'No schedule yet' }}
                        </div>
                        @if($medicine->supply_until)<div class="text-sm text-slate-500">Supply until {{ $medicine->supply_until->format('M j, Y') }}</div>@endif
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('patient.schedule.edit', $medicine) }}" class="rounded bg-teal-700 px-3 py-1.5 text-base text-white hover:bg-teal-800">{{ $medicine->schedule ? 'Schedule' : 'Set schedule' }}</a>
                        <a href="{{ route('patient.medicines.edit', $medicine) }}" class="rounded border border-slate-300 px-3 py-1.5 text-base hover:bg-slate-100">Edit</a>
                        <form method="POST" action="{{ route('patient.medicines.destroy', $medicine) }}" onsubmit="return confirm('Remove {{ e($medicine->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="rounded border border-slate-300 px-3 py-1.5 text-base text-red-700 hover:bg-red-50">Remove</button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="p-4 text-slate-500">No medicines yet. Add the ones you take so MediMind can build your schedule.</li>
            @endforelse
        </ul>
    @endforeach
@endsection
