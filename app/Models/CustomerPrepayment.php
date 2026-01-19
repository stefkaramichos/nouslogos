<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPrepayment extends Model
{
    protected $table = 'customer_prepayments';

    protected $fillable = [
        'customer_id',
        'cash_y_balance',
        'cash_n_balance',
        'card_balance',
        'card_bank',
        'last_paid_at',
        'created_by',
        'notes',
    ];
}
