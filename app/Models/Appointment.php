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

    // ✅ Split payments (πολλά payments ανά ραντεβού)
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // ✅ Helpers για να μη σκάει τίποτα στα blades/controllers
    public function getPaidTotalAttribute(): float
    {
        // αν έχει γίνει eager-load, δεν χτυπάει DB
        return (float) $this->payments->sum('amount');
    }

    public function getIsFullyPaidAttribute(): bool
    {
        $total = (float) ($this->total_price ?? 0);
        if ($total <= 0) return true;

        return $this->paid_total >= $total;
    }

    public function getOutstandingAttribute(): float
    {
        $total = (float) ($this->total_price ?? 0);
        return max(0, $total - $this->paid_total);
    }
}
