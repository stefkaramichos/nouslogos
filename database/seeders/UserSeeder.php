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
            'role'       => 'owner',      // 👈
            'company_id' => 1,
            'password'   => bcrypt('password123'),
        ]);

        User::create([
            'first_name' => 'Maria',
            'last_name'  => 'Grammateia',
            'phone'      => '6900000001',
            'email'      => 'grammatia@example.com',
            'role'       => 'grammatia',  // 👈
            'company_id' => 1,
            'password'   => bcrypt('password123'),
        ]);

    }
}
