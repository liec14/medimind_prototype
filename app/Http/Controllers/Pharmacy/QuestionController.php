<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\PharmacyQuestion;
use App\Notifications\CareAlertNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Safe-use questions patients send to this pharmacy. The pharmacist sees the patient's name and the one medicine they chose to mention, nothing else. */
class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $pharmacy = $request->user()->pharmacy;
        abort_unless($pharmacy, 403);

        $questions = $pharmacy->questions()->with(['patient:id,name', 'medicine:id,name'])->oldest()->get();

        return view('pharmacy.questions', [
            'open' => $questions->whereNull('answered_at')->values(),
            'answered' => $questions->whereNotNull('answered_at')->sortByDesc('answered_at')->values(),
        ]);
    }

    public function answer(Request $request, PharmacyQuestion $question): RedirectResponse
    {
        Gate::authorize('answer', $question);
        abort_if($question->isAnswered(), 404);

        $data = $request->validate(['answer' => ['required', 'string', 'max:2000']]);

        $question->forceFill(['answer' => $data['answer'], 'answered_at' => now()])->save();

        $question->patient->notify(new CareAlertNotification(
            $question->pharmacy->name.' answered your question',
            $question->pharmacy->name.' replied to your question. Open "Ask a pharmacist" to read it.',
            route('patient.ask'),
        ));

        return back()->with('status', 'Answer sent.');
    }
}
