<?php

namespace App\Models;

use Database\Factories\GeneralContractorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralContractor extends Model
{
    /** @use HasFactory<GeneralContractorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];
}
