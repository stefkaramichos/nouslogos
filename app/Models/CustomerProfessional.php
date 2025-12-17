<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CustomerProfessional extends Pivot
{
    protected $table = 'customer_professional';

    protected $fillable = [
        'customer_id',
        'professional_id',
    ];
}
