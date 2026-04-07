<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GeneralContractorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
])]
class GeneralContractor extends Model
{
    /** @use HasFactory<GeneralContractorFactory> */
    use HasFactory;
}
