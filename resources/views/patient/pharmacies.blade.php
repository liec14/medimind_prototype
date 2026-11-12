@extends('layouts.app')
@section('title', 'Pharmacies')
@section('content')
    <h1 class="text-2xl font-bold">Pharmacies</h1>
    <p class="mt-2 text-slate-600">If you agree, a pharmacy can see limited information, such as when a medicine is due for refill, so it can follow up with you. You can stop sharing at any time. The pharmacy sees the medicine name and the date your supply runs out. It never sees your doses, history or contact details. <a href="{{ route('patient.ask') }}" class="text-teal-700 underline">Ask a pharmacist a question</a>.</p>

    <ul class="mt-6 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($pharmacies as $pharmacy)
            @php $on = in_array($pharmacy->id, $consented, true); @endphp
            <li class="flex items-center justify-between gap-3 p-4">
                <div>
                    <div class="font-semibold">{{ $pharmacy->name }}</div>
                    <div class="text-base text-slate-500">{{ $pharmacy->address }}</div>
                </div>
                <form method="POST" action="{{ route($on ? 'patient.pharmacies.revoke' : 'patient.pharmacies.consent', $pharmacy) }}">
                    @csrf @if($on) @method('DELETE') @endif
                    <button class="rounded px-3 py-1.5 text-base {{ $on ? 'border border-slate-300 hover:bg-slate-100' : 'bg-teal-700 text-white hover:bg-teal-800' }}">
                        {{ $on ? 'Stop sharing' : 'Share with this pharmacy' }}
                    </button>
                </form>
            </li>
        @empty
            <li class="p-4 text-slate-500">No pharmacies have joined yet.</li>
        @endforelse
    </ul>
@endsection
