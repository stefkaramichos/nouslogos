<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'professional_id',          // uploader
        'visible_professional_id',  // σε ποιον είναι ορατό (nullable)
        'note',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
    ];

    public function professional()
    {
        return $this->belongsTo(Professional::class); // uploader
    }

    public function visibleProfessional()
    {
        return $this->belongsTo(Professional::class, 'visible_professional_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function isPreviewable(): bool
    {
        $mime = strtolower((string) $this->mime_type);

        return str_starts_with($mime, 'image/')
            || in_array($mime, ['application/pdf', 'text/plain'], true);
    }

    // ✅ ΚΕΝΤΡΙΚΟΣ ΕΛΕΓΧΟΣ ΠΡΟΣΒΑΣΗΣ
    public function canBeViewedBy(?Professional $user): bool
    {
        if (!$user) return false;

        // owner βλέπει ΟΛΑ
        if ($user->role === 'owner') return true;

        // (αν θες και η γραμματεία να βλέπει όλα)
        if ($user->role === 'grammatia') return true;

        // therapist: βλέπει
        // 1) ό,τι ανέβασε ο ίδιος (professional_id)
        // 2) ό,τι του έχει γίνει visible (visible_professional_id)
        $uid = (int)$user->id;

        return ((int)($this->professional_id ?? 0) === $uid)
            || ((int)($this->visible_professional_id ?? 0) === $uid);
    }
}
