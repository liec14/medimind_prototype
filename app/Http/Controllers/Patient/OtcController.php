<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Services\OtcRecommendationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OtcController extends Controller
{
    public function __invoke(Request $request, OtcRecommendationService $otc): View
    {
        $input = $request->validate([
            'symptom' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $searched = filled($input['symptom'] ?? null) || filled($input['q'] ?? null);

        return view('patient.otc', [
            'symptoms' => $otc->symptoms(),
            'input' => ['symptom' => $input['symptom'] ?? '', 'q' => $input['q'] ?? ''],
            'searched' => $searched,
            'result' => $searched ? $otc->recommend($request->user(), $input['symptom'] ?? null, $input['q'] ?? null) : null,
        ]);
    }
}
