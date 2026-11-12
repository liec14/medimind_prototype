@extends('layouts.app')
@section('title', 'OTC help')
@section('content')
    <h1 class="text-2xl font-bold">Over-the-counter help</h1>
    <p class="mt-2 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
        These are suggestions only. They are not a diagnosis and do not replace a doctor or pharmacist. If you are unsure, pregnant, or treating a child, ask a pharmacist first.
    </p>

    <form method="GET" class="mt-6 max-w-xl space-y-4 rounded-lg bg-white p-5 shadow-sm">
        <label class="block">What do you need help with?
            <select name="symptom" class="mt-1 w-full rounded border border-slate-300 bg-white px-3 py-2">
                <option value="">Choose from the list</option>
                @foreach($symptoms as $s)<option value="{{ $s }}" @selected($input['symptom'] === $s)>{{ $s }}</option>@endforeach
            </select>
        </label>
        <label class="block">Or describe it in your own words
            <input name="q" value="{{ $input['q'] }}" placeholder="e.g. my head hurts, sipon, kabag" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </label>
        <button class="rounded bg-teal-700 px-6 py-3 font-semibold text-white hover:bg-teal-800">Find suggestions</button>
    </form>

    @if($searched)
        @if(empty($result['matched']))
            <p class="mt-6 rounded-lg bg-white p-5 shadow-sm">We could not match that to a common complaint. Please ask a pharmacist, or <a href="{{ route('patient.info.index') }}" class="text-teal-700 underline">browse medicine information</a>.</p>
        @else
            <h2 class="mt-8 text-xl font-semibold">Suggestions for {{ implode(', ', $result['matched']) }}</h2>

            <ul class="mt-3 space-y-3">
                @forelse($result['suggestions'] as $s)
                    <li class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('patient.info.show', $s['medicine']) }}" class="text-lg font-semibold text-teal-800 underline">{{ $s['medicine']->generic_name }}</a>
                            <span class="text-slate-500">{{ $s['medicine']->strength }} {{ $s['medicine']->form }}</span>
                            @if($s['priority'] === 1)<span class="rounded bg-teal-100 px-2 py-0.5 text-sm text-teal-800">First choice</span>@endif
                            @if($s['already_taking'])<span class="rounded bg-slate-200 px-2 py-0.5 text-sm">You already take this</span>@endif
                        </div>
                        <div class="text-base text-slate-500">{{ $s['medicine']->brand_names }}</div>
                        @if($s['notes'])<p class="mt-2">{{ $s['notes'] }}</p>@endif
                        <p class="mt-2 text-base text-red-800"><strong>Watch out:</strong> {{ $s['medicine']->warnings }}</p>
                        @if($s['cautions']->isNotEmpty())
                            <div class="mt-3">@include('partials.interactions', ['interactions' => $s['cautions']])</div>
                        @endif
                        <a href="{{ route('patient.compare', ['medicine_id' => $s['medicine']->id]) }}" class="mt-3 inline-block text-base text-teal-700 underline">Compare pharmacy prices</a>
                    </li>
                @empty
                    <li class="rounded-lg bg-white p-5 shadow-sm">None of the usual options is safe to suggest with your current medicines. Please ask a pharmacist or your doctor.</li>
                @endforelse
            </ul>

            @if($result['excluded']->isNotEmpty())
                <h2 class="mt-8 text-xl font-semibold text-red-900">Not suggested for you</h2>
                <ul class="mt-3 space-y-3">
                    @foreach($result['excluded'] as $e)
                        <li class="rounded-lg border border-red-200 bg-red-50 p-5">
                            <div class="font-semibold">{{ $e['medicine']->generic_name }}</div>
                            <p class="mt-1 text-base">It may not mix well with a medicine you already take:</p>
                            <div class="mt-2">@include('partials.interactions', ['interactions' => $e['reasons']])</div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if(! empty($result['see_doctor_if']))
                <div class="mt-8 rounded-lg border border-red-200 bg-white p-5">
                    <h2 class="font-semibold text-red-900">See a doctor if</h2>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach($result['see_doctor_if'] as $symptom => $text)<li><strong>{{ $symptom }}:</strong> {{ $text }}</li>@endforeach
                    </ul>
                </div>
            @endif
        @endif
    @endif
@endsection
