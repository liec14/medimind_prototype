@extends('layouts.app')
@section('title', 'Review medicine')
@section('content')
    <a href="{{ route('doctor.patients.show', $patient) }}" class="text-base text-teal-700 underline">← {{ $patient->name }}</a>
    <h1 class="mb-1 mt-2 text-2xl font-bold">Review {{ $medicine->name }}</h1>
    <p class="mb-4 text-slate-600">Correct entries that are wrong or out of date. Your name is recorded on every change.</p>
    @include('medicines._form', ['action' => route('doctor.medicines.update', [$patient, $medicine]), 'cancel' => route('doctor.patients.show', $patient)])
@endsection
