<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
        'eidikotita',
        'profile_image',
    ];

    protected $hidden = [
        'password',
        // 'remember_token', // προαιρετικά αφαιρείς αυτό, αφού δεν υπάρχει στήλη
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // 🔒 Απενεργοποίηση remember_token για αυτό το model
    public function getRememberTokenName()
    {
        return null; // Πες στο Laravel ότι δεν υπάρχει remember_token
    }

    public function setRememberToken($value)
    {
        // Μη κάνεις τίποτα – έτσι αποφεύγουμε απόπειρα αποθήκευσης σε ανύπαρκτη στήλη
    }

    public function getRememberToken()
    {
        return null;
    }

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
