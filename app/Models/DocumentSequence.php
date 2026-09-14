<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = ['series', 'last_number', 'financial_year'];
}
