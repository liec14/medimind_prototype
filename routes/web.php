<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Doctor\AlertController as DoctorAlertController;
use App\Http\Controllers\Doctor\HistoryController as DoctorHistoryController;
use App\Http\Controllers\Doctor\MedicineController as DoctorMedicineController;
use App\Http\Controllers\Doctor\PatientController as DoctorPatientController;
use App\Http\Controllers\Doctor\ScheduleController as DoctorScheduleController;
use App\Http\Controllers\Patient\DashboardController as PatientDashboardController;
use App\Http\Controllers\Patient\DoctorLinkController;
use App\Http\Controllers\Patient\DoseController;
use App\Http\Controllers\Patient\HistoryController as PatientHistoryController;
use App\Http\Controllers\Patient\MedicineController;
use App\Http\Controllers\Patient\MedicineInfoController;
use App\Http\Controllers\Patient\NotificationController;
use App\Http\Controllers\Patient\OtcController;
use App\Http\Controllers\Patient\PharmacyComparisonController;
use App\Http\Controllers\Patient\PharmacyConsentController;
use App\Http\Controllers\Patient\PharmacyQuestionController;
use App\Http\Controllers\Patient\ReminderSuggestionController;
use App\Http\Controllers\Patient\RoutineController;
use App\Http\Controllers\Patient\ScheduleController;
use App\Http\Controllers\Patient\TrustedContactController;
use App\Http\Controllers\Pharmacy\DashboardController as PharmacyDashboardController;
use App\Http\Controllers\Pharmacy\QuestionController as PharmacyQuestionAnswerController;
use App\Http\Controllers\Pharmacy\RefillController;
use App\Http\Controllers\Pharmacy\StockController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('/pending-approval', 'auth.pending')->name('pending');

    Route::middleware(['approved', 'role:patient'])->prefix('patient')->name('patient.')->group(function () {
        Route::get('/', PatientDashboardController::class)->name('dashboard');
        Route::resource('medicines', MedicineController::class)->except('show');
        Route::get('/medicines/{medicine}/schedule', [ScheduleController::class, 'edit'])->name('schedule.edit');
        Route::put('/medicines/{medicine}/schedule', [ScheduleController::class, 'update'])->name('schedule.update');
        Route::delete('/medicines/{medicine}/schedule', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
        Route::post('/doses/{dose}/confirm', [DoseController::class, 'confirm'])->name('doses.confirm');
        Route::post('/doses/{dose}/snooze', [DoseController::class, 'snooze'])->name('doses.snooze');
        Route::get('/history', PatientHistoryController::class)->name('history');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read');
        Route::get('/medicine-info', [MedicineInfoController::class, 'index'])->name('info.index');
        Route::get('/medicine-info/{catalogMedicine}', [MedicineInfoController::class, 'show'])->name('info.show');
        Route::get('/otc', OtcController::class)->name('otc');
        Route::get('/compare', PharmacyComparisonController::class)->name('compare');
        Route::get('/ask', [PharmacyQuestionController::class, 'index'])->name('ask');
        Route::post('/ask', [PharmacyQuestionController::class, 'store'])->name('ask.store')->middleware('throttle:10,1');
        Route::get('/contacts', [TrustedContactController::class, 'index'])->name('contacts');
        Route::post('/contacts', [TrustedContactController::class, 'store'])->name('contacts.store');
        Route::put('/contacts/{contact}', [TrustedContactController::class, 'update'])->name('contacts.update');
        Route::delete('/contacts/{contact}', [TrustedContactController::class, 'destroy'])->name('contacts.destroy');
        Route::post('/suggestions/{suggestion}/accept', [ReminderSuggestionController::class, 'accept'])->name('suggestions.accept');
        Route::post('/suggestions/{suggestion}/dismiss', [ReminderSuggestionController::class, 'dismiss'])->name('suggestions.dismiss');
        Route::get('/routine', [RoutineController::class, 'edit'])->name('routine.edit');
        Route::put('/routine', [RoutineController::class, 'update'])->name('routine.update');
        Route::get('/doctors', [DoctorLinkController::class, 'index'])->name('doctors');
        Route::post('/doctors', [DoctorLinkController::class, 'store'])->name('doctors.store');
        Route::delete('/doctors/{doctor}', [DoctorLinkController::class, 'destroy'])->name('doctors.destroy');
        Route::get('/pharmacies', [PharmacyConsentController::class, 'index'])->name('pharmacies');
        Route::post('/pharmacies/{pharmacy}/consent', [PharmacyConsentController::class, 'store'])->name('pharmacies.consent');
        Route::delete('/pharmacies/{pharmacy}/consent', [PharmacyConsentController::class, 'destroy'])->name('pharmacies.revoke');
    });

    Route::middleware(['approved', 'role:doctor'])->prefix('doctor')->name('doctor.')->group(function () {
        Route::get('/', [DoctorPatientController::class, 'dashboard'])->name('dashboard');
        Route::get('/alerts', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/alerts/read-all', [NotificationController::class, 'readAll'])->name('notifications.read');
        Route::patch('/alerts/{alert}/acknowledge', [DoctorAlertController::class, 'acknowledge'])->name('alerts.acknowledge');
        Route::patch('/alerts/{alert}/resolve', [DoctorAlertController::class, 'resolve'])->name('alerts.resolve');
        Route::get('/patients/{patient}', [DoctorPatientController::class, 'show'])->name('patients.show');
        Route::get('/patients/{patient}/history', DoctorHistoryController::class)->name('patients.history');
        Route::patch('/patients/{patient}/link', [DoctorPatientController::class, 'respond'])->name('patients.respond');
        Route::get('/patients/{patient}/medicines/{medicine}/edit', [DoctorMedicineController::class, 'edit'])->name('medicines.edit')->scopeBindings();
        Route::put('/patients/{patient}/medicines/{medicine}', [DoctorMedicineController::class, 'update'])->name('medicines.update')->scopeBindings();
        Route::get('/patients/{patient}/medicines/{medicine}/schedule', [DoctorScheduleController::class, 'edit'])->name('schedule.edit')->scopeBindings();
        Route::put('/patients/{patient}/medicines/{medicine}/schedule', [DoctorScheduleController::class, 'update'])->name('schedule.update')->scopeBindings();
    });

    Route::middleware(['approved', 'role:pharmacy'])->prefix('pharmacy')->name('pharmacy.')->group(function () {
        Route::get('/', PharmacyDashboardController::class)->name('dashboard');
        Route::get('/refills', [RefillController::class, 'index'])->name('refills');
        Route::post('/refills/{medicine}/follow-up', [RefillController::class, 'followUp'])->name('refills.followup');
        Route::get('/questions', [PharmacyQuestionAnswerController::class, 'index'])->name('questions');
        Route::post('/questions/{question}/answer', [PharmacyQuestionAnswerController::class, 'answer'])->name('questions.answer');
        Route::get('/prices', [StockController::class, 'index'])->name('prices');
        Route::post('/stock', [StockController::class, 'store'])->name('stock.store');
        Route::put('/stock/{stock}', [StockController::class, 'update'])->name('stock.update');
        Route::delete('/stock/{stock}', [StockController::class, 'destroy'])->name('stock.destroy');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');
        Route::patch('/approvals/{user}', [ApprovalController::class, 'approve'])->name('approvals.approve');
    });
});
