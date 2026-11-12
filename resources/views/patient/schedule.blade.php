@extends('layouts.app')
@section('title', 'Set schedule')
@section('content')
    <h1 class="mb-4 text-2xl font-bold">Schedule for {{ $medicine->name }}</h1>
    @include('schedules._form', [
        'action' => route('patient.schedule.update', $medicine),
        'frequencyUrl' => route('patient.schedule.edit', $medicine),
        'cancel' => route('patient.medicines.index'),
    ])
    @if($schedule)
        <form method="POST" action="{{ route('patient.schedule.destroy', $medicine) }}" class="mt-4" onsubmit="return confirm('Remove this schedule? Upcoming doses will be deleted.')">
            @csrf @method('DELETE')
            <button class="text-base text-red-700 underline">Remove schedule</button>
        </form>
    @endif
@endsection
