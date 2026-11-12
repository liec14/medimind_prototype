@extends('layouts.app')
@section('title', 'Patient questions')
@section('content')
    <h1 class="text-2xl font-bold">Patient questions</h1>
    <p class="mt-2 text-slate-600">Safe-use questions sent to your pharmacy. You see the patient's name and the one medicine they mentioned. Please answer in plain language, and advise a doctor visit or emergency care when needed.</p>

    <h2 class="mt-6 font-semibold">Waiting for an answer ({{ $open->count() }})</h2>
    <ul class="mt-2 space-y-3">
        @forelse($open as $q)
            <li class="rounded-lg bg-white p-5 shadow-sm">
                <div class="font-semibold">{{ $q->patient->name }}
                    @if($q->medicine)<span class="font-normal text-slate-500">· about {{ $q->medicine->name }}</span>@endif
                </div>
                <div class="text-sm text-slate-500">Asked {{ $q->created_at->diffForHumans() }}</div>
                <p class="mt-2 whitespace-pre-line">{{ $q->question }}</p>
                <form method="POST" action="{{ route('pharmacy.questions.answer', $q) }}" class="mt-3 space-y-2">
                    @csrf
                    <label class="block text-base">Your answer
                        <textarea name="answer" rows="3" maxlength="2000" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
                    </label>
                    <button class="rounded bg-teal-700 px-5 py-2 font-semibold text-white hover:bg-teal-800">Send answer</button>
                </form>
            </li>
        @empty
            <li class="rounded-lg bg-white p-5 text-slate-500 shadow-sm">No unanswered questions.</li>
        @endforelse
    </ul>

    @if($answered->isNotEmpty())
        <h2 class="mt-8 font-semibold">Answered</h2>
        <ul class="mt-2 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
            @foreach($answered as $q)
                <li class="p-4">
                    <div class="font-semibold">{{ $q->patient->name }}@if($q->medicine) <span class="font-normal text-slate-500">· about {{ $q->medicine->name }}</span>@endif</div>
                    <p class="mt-1 whitespace-pre-line text-slate-700">{{ $q->question }}</p>
                    <p class="mt-2 whitespace-pre-line rounded bg-teal-50 p-3">{{ $q->answer }}</p>
                    <div class="mt-1 text-sm text-slate-500">Answered {{ $q->answered_at->diffForHumans() }}</div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
