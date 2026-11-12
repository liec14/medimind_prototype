@extends('layouts.app')
@section('title', 'Pharmacy overview')
@section('content')
    <h1 class="text-2xl font-bold">{{ $pharmacy->name }}</h1>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <a href="{{ route('pharmacy.refills') }}" class="rounded-lg bg-white p-4 shadow-sm hover:bg-teal-50">
            <div class="text-3xl font-bold {{ $due->isNotEmpty() ? 'text-amber-700' : '' }}">{{ $due->count() }}</div>
            <div class="text-base text-slate-500">Refills due</div>
        </a>
        <a href="{{ route('pharmacy.refills') }}" class="rounded-lg bg-white p-4 shadow-sm hover:bg-teal-50">
            <div class="text-3xl font-bold {{ $stopped ? 'text-red-700' : '' }}">{{ $stopped }}</div>
            <div class="text-base text-slate-500">May have stopped refilling</div>
        </a>
        <a href="{{ route('pharmacy.questions') }}" class="rounded-lg bg-white p-4 shadow-sm hover:bg-teal-50">
            <div class="text-3xl font-bold {{ $openQuestions ? 'text-red-700' : '' }}">{{ $openQuestions }}</div>
            <div class="text-base text-slate-500">Unanswered questions</div>
        </a>
        <a href="{{ route('pharmacy.prices') }}" class="rounded-lg bg-white p-4 shadow-sm hover:bg-teal-50">
            <div class="text-3xl font-bold">{{ $listed }}</div>
            <div class="text-base text-slate-500">Medicines on your price list</div>
        </a>
    </div>

    <p class="mt-6 text-slate-600">
        {{ $patients }} patient{{ $patients === 1 ? ' has' : 's have' }} chosen to share refill dates with you.
        You see only the medicine name and the date the supply runs out, never doses, history or contact details.
    </p>
@endsection
