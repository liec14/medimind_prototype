@extends('layouts.app')
@section('title', 'Welcome')
@section('content')
    <section class="py-10 text-center">
        <h1 class="text-4xl font-bold text-teal-800">Take the right medicine, on time.</h1>
        <p class="mx-auto mt-4 max-w-2xl text-slate-600">
            MediMind helps patients keep track of their medicines, and helps doctors and pharmacies support them between visits.
        </p>
        <div class="mt-8 flex justify-center gap-3">
            <a href="{{ route('register') }}" class="rounded bg-teal-700 px-6 py-3 font-semibold text-white hover:bg-teal-800">Create an account</a>
            <a href="{{ route('login') }}" class="rounded border border-slate-300 bg-white px-6 py-3 font-semibold hover:bg-slate-100">Log in</a>
        </div>
    </section>
@endsection
