@extends('layouts.app')
@section('title', $medicine->generic_name)
@section('content')
    <a href="{{ route('patient.info.index') }}" class="text-base text-teal-700 underline">← All medicines</a>
    <h1 class="mt-2 text-2xl font-bold">{{ $medicine->generic_name }}
        @if($medicine->is_otc)<span class="ml-1 rounded bg-teal-100 px-2 py-0.5 align-middle text-sm font-medium text-teal-800">Over the counter</span>@endif
    </h1>
    <p class="text-slate-500">{{ $medicine->brand_names }} · {{ $medicine->strength }} {{ $medicine->form }} · {{ $medicine->category?->name }}</p>

    @if($alreadyTaking)
        <p class="mt-4 rounded border border-teal-200 bg-teal-50 px-4 py-3 text-teal-900">You already have this medicine in your list.</p>
    @endif

    @if($interactions->isNotEmpty())
        <div class="mt-4 rounded border border-amber-300 bg-white px-4 py-3" role="alert">
            <h2 class="mb-2 font-semibold text-amber-900">Possible interactions with what you take</h2>
            @include('partials.interactions', ['interactions' => $interactions])
        </div>
    @endif

    <dl class="mt-6 space-y-5 rounded-lg bg-white p-6 shadow-sm">
        <div><dt class="font-semibold">What it is for</dt><dd class="mt-1">{{ $medicine->purpose }}</dd></div>
        <div><dt class="font-semibold">How it is usually taken</dt><dd class="mt-1">{{ $medicine->how_to_take }}</dd></div>
        <div><dt class="font-semibold text-red-800">What to watch out for</dt><dd class="mt-1">{{ $medicine->warnings }}</dd></div>
    </dl>

    <div class="mt-6 flex flex-wrap gap-3">
        @unless($alreadyTaking)
            <a href="{{ route('patient.medicines.create', ['name' => $medicine->generic_name]) }}" class="rounded bg-teal-700 px-5 py-2.5 font-semibold text-white hover:bg-teal-800">Add to my medicines</a>
        @endunless
        <a href="{{ route('patient.compare', ['medicine_id' => $medicine->id]) }}" class="rounded border border-slate-300 bg-white px-5 py-2.5 hover:bg-slate-100">Compare pharmacy prices</a>
    </div>
    <p class="mt-6 text-sm text-slate-500">General information only. Follow your doctor's or pharmacist's instructions for you.</p>
@endsection
