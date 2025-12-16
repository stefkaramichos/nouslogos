<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerFile extends Model
{
    protected $fillable = [
        'customer_id',
        'uploaded_by',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'notes',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploader()
    {
        return $this->belongsTo(Professional::class, 'uploaded_by');
    }
}
