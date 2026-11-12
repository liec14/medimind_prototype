{{-- Expects $interactions: list of ['severity', 'note', 'medicines' => [a, b]] --}}
<ul class="space-y-2">
    @foreach($interactions as $i)
        @php $high = $i['severity'] === 'high'; @endphp
        <li class="rounded border p-3 {{ $high ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
            <div class="font-semibold">
                {{ implode(' + ', $i['medicines']) }}
                <span class="ml-1 rounded px-2 py-0.5 text-sm font-medium {{ $high ? 'bg-red-200' : 'bg-amber-200' }}">{{ $high ? 'Avoid taking together' : 'Use with care' }}</span>
            </div>
            <div class="text-base">{{ $i['note'] }}</div>
        </li>
    @endforeach
</ul>
<p class="mt-2 text-sm text-slate-500">Ask your doctor or pharmacist before taking these together. This check covers common pairs only, not every possible interaction.</p>
