@extends('layouts.app')
@section('title', 'Add medicine')
@section('content')
    <h1 class="mb-4 text-2xl font-bold">Add a medicine</h1>
    @include('medicines._form', ['action' => route('patient.medicines.store'), 'cancel' => route('patient.medicines.index')])
@endsection
