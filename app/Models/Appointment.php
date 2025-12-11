<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Payment;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'professional_id',
        'company_id',
        'start_time',
        'end_time',
        'status',
        'total_price',
        'professional_amount',
        'company_amount',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(Professional::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function getPaidTotalAttribute()
    {
        return $this->payments->sum('amount');
    }

}
