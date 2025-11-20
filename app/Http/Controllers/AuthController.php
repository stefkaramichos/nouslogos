<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(
            [
                'email'    => 'required|email',
                'password' => 'required',
            ],
            [
                'email.required'    => 'Το email είναι υποχρεωτικό.',
                'email.email'       => 'Το email δεν είναι έγκυρο.',
                'password.required' => 'Ο κωδικός είναι υποχρεωτικός.',
            ]
        );

        // Βρίσκουμε τον χρήστη
        $user = User::where('email', $credentials['email'])->first();

        // Αν δεν υπάρχει ή δεν έχει σωστό ρόλο → μπλοκάρισμα
        if (!$user || !in_array($user->role, ['owner', 'grammatia'])) {
            return back()
                ->withErrors(['email' => 'Δεν έχετε δικαίωμα πρόσβασης στο σύστημα.'])
                ->withInput();
        }

        // Προσπάθεια login
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Πάμε στο dashboard ή όπου θέλεις
            return redirect()->intended(route('appointments.index'));
        }

        return back()
            ->withErrors(['email' => 'Λάθος email ή κωδικός.'])
            ->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
