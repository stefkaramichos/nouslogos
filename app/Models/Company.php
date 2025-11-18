<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'city', 'is_active'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function professionals()
    {
        return $this->hasMany(Professional::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
