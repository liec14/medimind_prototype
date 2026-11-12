{{-- Expects $risk: a RiskAssessment or null --}}
@php
    $level = $risk?->hasEnoughData() ? $risk->level : null;
    $styles = ['high' => 'bg-red-100 text-red-800', 'medium' => 'bg-amber-100 text-amber-800', 'low' => 'bg-teal-100 text-teal-800'];
@endphp
@if($level)
    <span class="rounded px-2 py-0.5 text-sm font-medium {{ $styles[$level] }}">{{ ucfirst($level) }} risk</span>
@else
    <span class="rounded bg-slate-100 px-2 py-0.5 text-sm text-slate-600">Not enough data yet</span>
@endif
