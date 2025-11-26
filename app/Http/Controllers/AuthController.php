<?php

namespace App\Http\Controllers;

use App\Models\Professional;
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

        // Βρίσκουμε τον επαγγελματία
        $professional = Professional::where('email', $credentials['email'])->first();

        if (!$professional) {
            return back()
                ->withErrors(['email' => 'Λάθος email ή κωδικός.'])
                ->withInput();
        }

        // Έλεγχος ρόλου
        if (!in_array($professional->role, ['owner', 'grammatia', 'therapist'])) {
            return back()
                ->withErrors(['email' => 'Δεν έχετε δικαίωμα πρόσβασης στο σύστημα.'])
                ->withInput();
        }

        // Προσπάθεια login
        if (Auth::attempt($credentials, $request->boolean('remember'))) {

            $request->session()->regenerate();

            // 👇 REDIRECT BASED ON ROLE
            if ($professional->role === 'therapist') {
                return redirect()->route('therapist_appointments.index');
            }

            // Για owner & γραμματεία
            return redirect()->intended(route('customers.index'));
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
