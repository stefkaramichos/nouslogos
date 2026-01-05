<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

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
        'start_time'   => 'datetime',
        'end_time'     => 'datetime',
        'total_price'  => 'decimal:2',
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

    // ✅ όλες οι πληρωμές (split)
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // ✅ τελευταία πληρωμή (μόνο για προβολή)
    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany('paid_at');
    }

    // ✅ computed σύνολο πληρωμένων
    public function getPaidTotalAttribute()
    {
        // Αν δεν έχει γίνει eager load, θα κάνει query per row.
        // Για list/table ΠΑΝΤΑ φόρτωσε appointments.payments στο controller.
        return $this->payments->sum('amount');
    }

    public function getOutstandingAttribute()
    {
        $total = (float)($this->total_price ?? 0);
        $paid  = (float)($this->paid_total ?? 0);
        return max(0, $total - $paid);
    }
}
