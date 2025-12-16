<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected $fillable = [
        'company_id',
        'month',
        'total_amount',
        'cash_to_bank',
        'partner1_total',
        'partner2_total',
        'created_by',
    ];

    protected $casts = [
        'month' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(Professional::class, 'created_by');
    }
}
