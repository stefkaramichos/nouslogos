<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'role',
        'company_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function appointmentsCreated()
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isSecretary(): bool
    {
        return $this->role === 'secretary';
    }
}
