<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first(); // π.χ. Θέρμη

        User::create([
            'first_name' => 'Admin',
            'last_name'  => 'Owner',
            'phone'      => '6900000000',
            'email'      => 'owner@example.com',
            'role'       => 'owner',
            'company_id' => $company?->id,
            'password'   => Hash::make('password'),
        ]);
    }
}
