<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Jesus Guerra',
            'email' => 'jehg13072002@gmail.com',
            'password' => Hash::make('13072002'),
            'rol' => 'admin',
        ]);
    }
}