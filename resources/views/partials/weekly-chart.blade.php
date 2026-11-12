{{-- Expects $weeks from AdherenceService::weekly() --}}
<section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="weekly-heading">
    <h2 id="weekly-heading" class="font-semibold">Doses taken, by week</h2>
    <div class="mt-4 flex h-40 items-end gap-2" role="img" aria-label="Weekly percentage of doses taken">
        @foreach($weeks as $week)
            <div class="flex h-full flex-1 flex-col items-center justify-end" title="{{ $week['label'] }}: {{ $week['taken'] }} taken, {{ $week['missed'] }} missed">
                <div class="mb-1 text-sm text-slate-600">{{ $week['rate'] === null ? '—' : round($week['rate']).'%' }}</div>
                <div class="w-full rounded-t {{ $week['rate'] === null ? 'bg-slate-200' : ($week['rate'] >= 80 ? 'bg-teal-600' : ($week['rate'] >= 50 ? 'bg-amber-500' : 'bg-red-600')) }}"
                     style="height: {{ $week['rate'] === null ? 4 : max(4, $week['rate']) }}%"></div>
            </div>
        @endforeach
    </div>
    <div class="mt-1 flex gap-2 text-center text-xs text-slate-500">
        @foreach($weeks as $week)<div class="flex-1">{{ \Illuminate\Support\Str::before($week['label'], ' –') }}</div>@endforeach
    </div>
    <p class="mt-3 text-sm text-slate-500">Week starting on the date shown. Green is 80% or more, amber 50–79%, red below 50%.</p>
</section>
