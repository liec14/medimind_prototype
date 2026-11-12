@extends('layouts.app')
@section('title', 'History')
@section('content')
    <h1 class="text-2xl font-bold">My history</h1>
    <div class="mt-4">@include('partials.weekly-chart', ['weeks' => $weeks])</div>
    @include('partials.dose-history')
@endsection
