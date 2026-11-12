<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'birthdate', 'license_no'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'birthdate' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function pharmacy(): HasOne
    {
        return $this->hasOne(Pharmacy::class);
    }

    /** For a patient: every scheduled dose, taken, missed or pending. */
    public function doseLogs(): HasMany
    {
        return $this->hasMany(DoseLog::class, 'patient_id');
    }

    /** For a patient: safe-use questions they sent to pharmacies. */
    public function pharmacyQuestions(): HasMany
    {
        return $this->hasMany(PharmacyQuestion::class, 'patient_id');
    }

    public function routine(): HasOne
    {
        return $this->hasOne(PatientRoutine::class, 'patient_id');
    }

    /** The patient's saved routine, or default times if none is saved yet. */
    public function routineOrDefault(): PatientRoutine
    {
        return $this->routine ?? PatientRoutine::defaults();
    }

    /** For a patient: registered medicines. */
    public function medicines(): HasMany
    {
        return $this->hasMany(PatientMedicine::class, 'patient_id');
    }

    /** For a patient: doctors linked in any status (check pivot status). */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'doctor_patient', 'patient_id', 'doctor_id')
            ->withPivot('status')->withTimestamps();
    }

    /** For a doctor: patients linked in any status (check pivot status). */
    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'doctor_patient', 'doctor_id', 'patient_id')
            ->withPivot('status')->withTimestamps();
    }

    /** For a patient: pharmacies they consented to share info with. */
    public function consentedPharmacies(): BelongsToMany
    {
        return $this->belongsToMany(Pharmacy::class, 'pharmacy_patient_consents', 'patient_id', 'pharmacy_id')
            ->withPivot('granted_at');
    }

    /** For a patient: doctors who accepted the link. */
    public function activeDoctors(): Collection
    {
        return $this->doctors()->wherePivot('status', 'active')->get();
    }

    public function trustedContacts(): HasMany
    {
        return $this->hasMany(TrustedContact::class, 'patient_id');
    }

    public function riskAssessments(): HasMany
    {
        return $this->hasMany(RiskAssessment::class, 'patient_id');
    }

    /** The most recent risk estimate. */
    public function latestRisk(): HasOne
    {
        return $this->hasOne(RiskAssessment::class, 'patient_id')->latestOfMany('assessed_on');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'patient_id');
    }

    public function hasActiveDoctor(User $doctor): bool
    {
        return $this->doctors()->where('users.id', $doctor->id)->wherePivot('status', 'active')->exists();
    }
}
