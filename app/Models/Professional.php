<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;


class Professional extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'company_id',   // primary company
        'service_fee',
        'percentage_cut',
        'salary',
        'is_active',
        'password',
        'role',
        'profile_image', 
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // 👇 primary / legacy company (company_id)
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // 👇 many-to-many companies
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_professional');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function therapistAppointments()
    {
        return $this->hasMany(TherapistAppointment::class);
    }
}