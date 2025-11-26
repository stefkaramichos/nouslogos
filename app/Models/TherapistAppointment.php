<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistAppointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'professional_id',
        'customer_id',
        'start_time',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
