@extends('layouts.app')
@section('title', 'Review schedule')
@section('content')
    <a href="{{ route('doctor.patients.show', $patient) }}" class="text-base text-teal-700 underline">← {{ $patient->name }}</a>
    <h1 class="mb-4 mt-2 text-2xl font-bold">Schedule for {{ $medicine->name }}</h1>
    @include('schedules._form', [
        'action' => route('doctor.schedule.update', [$patient, $medicine]),
        'frequencyUrl' => route('doctor.schedule.edit', [$patient, $medicine]),
        'cancel' => route('doctor.patients.show', $patient),
    ])
@endsection
