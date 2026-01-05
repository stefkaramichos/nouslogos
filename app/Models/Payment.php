<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'customer_id',
        'amount',
        'is_full',
        'paid_at',
        'method',
        'tax',
        'bank',
        'notes',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'is_full' => 'boolean',
        'amount'  => 'decimal:2',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
