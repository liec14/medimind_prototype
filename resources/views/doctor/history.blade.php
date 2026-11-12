@extends('layouts.app')
@section('title', 'Intake history')
@section('content')
    <a href="{{ route('doctor.patients.show', $patient) }}" class="text-base text-teal-700 underline">← {{ $patient->name }}</a>
    <h1 class="mt-2 text-2xl font-bold">Intake history: {{ $patient->name }}</h1>
    <p class="mt-1 text-slate-600">Recorded from the patient's confirmations. Read only.</p>
    <div class="mt-4">@include('partials.weekly-chart', ['weeks' => $weeks])</div>
    @include('partials.dose-history')
@endsection
