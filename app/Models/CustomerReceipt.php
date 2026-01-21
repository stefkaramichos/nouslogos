<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReceipt extends Model
{
    protected $table = 'customer_receipts';

    protected $fillable = [
        'customer_id',
        'amount',
        'comment',
        'receipt_date',
        'is_issued',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'receipt_date' => 'date',
        'is_issued' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
