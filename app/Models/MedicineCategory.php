<?php

namespace App\Models;

use Database\Factories\MedicineCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class MedicineCategory extends Model
{
    /** @use HasFactory<MedicineCategoryFactory> */
    use HasFactory;
}
