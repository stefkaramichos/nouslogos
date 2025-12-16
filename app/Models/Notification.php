<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'professional_id',
        'note',
        'notify_at',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'notify_at' => 'datetime',
        'is_read'   => 'boolean',
        'read_at'   => 'datetime',
    ];

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }
}
