<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Services\DoseHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(Request $request, DoseHistoryService $history): View
    {
        $filters = $request->validate(DoseHistoryService::RULES);

        return view('patient.history', $history->build($request->user(), $filters));
    }
}
