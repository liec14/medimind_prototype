@extends('layouts.app')
@section('title', 'Ask a pharmacist')
@section('content')
    @php $input = 'mt-1 w-full rounded border border-slate-300 bg-white px-3 py-2'; @endphp
    <h1 class="text-2xl font-bold">Ask a pharmacist</h1>
    <p class="mt-2 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-900">
        This is not for emergencies. If you took too much, or have trouble breathing, swelling, or a severe reaction, call emergency services or go to the nearest hospital now.
    </p>
    <p class="mt-3 text-slate-600">The pharmacy sees your name, your question, and the one medicine you choose to mention. Nothing else from your record is shared.</p>

    <form method="POST" action="{{ route('patient.ask.store') }}" class="mt-6 max-w-xl space-y-4 rounded-lg bg-white p-6 shadow-sm">
        @csrf
        <label class="block">Which pharmacy?
            <select name="pharmacy_id" required class="{{ $input }}">
                <option value="">Choose a pharmacy</option>
                @foreach($pharmacies as $p)<option value="{{ $p->id }}" @selected((string) old('pharmacy_id') === (string) $p->id)>{{ $p->name }}@if($p->address) · {{ $p->address }}@endif</option>@endforeach
            </select>
        </label>
        <label class="block">About one of your medicines <span class="text-base text-slate-500">(optional)</span>
            <select name="patient_medicine_id" class="{{ $input }}">
                <option value="">No specific medicine</option>
                @foreach($medicines as $m)<option value="{{ $m->id }}" @selected((string) old('patient_medicine_id') === (string) $m->id)>{{ $m->name }}</option>@endforeach
            </select>
        </label>
        <label class="block">Your question
            <textarea name="question" rows="4" maxlength="1000" required class="{{ $input }}" placeholder="e.g. Can I take this with my cough medicine?">{{ old('question') }}</textarea>
        </label>
        <button class="rounded bg-teal-700 px-6 py-3 font-semibold text-white hover:bg-teal-800">Send question</button>
    </form>

    <h2 class="mt-8 text-xl font-semibold">My questions</h2>
    <ul class="mt-3 space-y-3">
        @forelse($questions as $q)
            <li class="rounded-lg bg-white p-5 shadow-sm">
                <div class="font-semibold">{{ $q->pharmacy->name }}@if($q->medicine) <span class="font-normal text-slate-500">· about {{ $q->medicine->name }}</span>@endif</div>
                <p class="mt-1 whitespace-pre-line">{{ $q->question }}</p>
                @if($q->isAnswered())
                    <p class="mt-3 whitespace-pre-line rounded bg-teal-50 p-3">{{ $q->answer }}</p>
                    <div class="mt-1 text-sm text-slate-500">Answered {{ $q->answered_at->diffForHumans() }}. General guidance only; follow your doctor's instructions.</div>
                @else
                    <div class="mt-2 text-base text-amber-700">Waiting for a reply</div>
                @endif
            </li>
        @empty
            <li class="rounded-lg bg-white p-5 text-slate-500 shadow-sm">You have not asked anything yet.</li>
        @endforelse
    </ul>
@endsection
