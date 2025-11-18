<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create(['name' => 'Θέρμη', 'city' => 'Θεσσαλονίκη', 'is_active' => true]);
        Company::create(['name' => 'Θεσσαλονίκη', 'city' => 'Θεσσαλονίκη', 'is_active' => true]);
    }
}
