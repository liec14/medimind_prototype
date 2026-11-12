@extends('layouts.app')
@section('title', 'Daily routine')
@section('content')
    <h1 class="text-2xl font-bold">My daily routine</h1>
    <p class="mt-2 text-slate-600">MediMind uses these times to suggest when to take each medicine. Existing schedules are not changed.</p>

    <form method="POST" action="{{ route('patient.routine.update') }}" class="mt-6 max-w-md space-y-4 rounded-lg bg-white p-6 shadow-sm">
        @csrf @method('PUT')
        @foreach(\App\Models\PatientRoutine::LABELS as $field => $label)
            <label class="block">{{ $label }}
                <input type="time" name="{{ $field }}" value="{{ old($field, $routine->{$field}) }}" required
                       class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </label>
        @endforeach
        <div class="flex gap-3">
            <button class="rounded bg-teal-700 px-6 py-3 font-semibold text-white hover:bg-teal-800">Save routine</button>
            <a href="{{ route('patient.medicines.index') }}" class="rounded border border-slate-300 px-6 py-3 hover:bg-slate-100">Cancel</a>
        </div>
    </form>
@endsection
