@extends('layouts.app')
@section('title', 'Edit medicine')
@section('content')
    <h1 class="mb-4 text-2xl font-bold">Edit {{ $medicine->name }}</h1>
    @include('medicines._form', ['action' => route('patient.medicines.update', $medicine), 'cancel' => route('patient.medicines.index')])
@endsection
