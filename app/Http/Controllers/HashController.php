<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class HashController extends Controller
{
    public function hashPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        // Δημιουργεί καθαρό bcrypt hash
        $hashedPassword = Hash::make($request->password);

        // Εμφανίζει ΜΟΝΟ το hash, χωρίς JSON escaping
        return response($hashedPassword);
    }
}
