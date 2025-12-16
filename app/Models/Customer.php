<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Payment; 
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'company_id',
        'tax_office', 
        'vat_number', 
        'informations', 
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function files()
    {
        return $this->hasMany(\App\Models\CustomerFile::class);
    }

}
