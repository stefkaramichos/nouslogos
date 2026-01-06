<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'professional_id',
        'note',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
    ];

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function isPreviewable(): bool
    {
        $mime = strtolower((string) $this->mime_type);

        return str_starts_with($mime, 'image/')
            || in_array($mime, ['application/pdf', 'text/plain'], true);
    }
    
}
