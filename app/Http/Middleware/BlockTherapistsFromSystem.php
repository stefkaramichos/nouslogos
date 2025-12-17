<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockTherapistsFromSystem
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Αν είναι therapist → επιτρέπουμε ΜΟΝΟ συγκεκριμένα routes
        if ($user && $user->role === 'therapist') {

            $allowedRoutes = [
                'dashboard',
                'therapist_appointments.index',
                'therapist_appointments.create',
                'therapist_appointments.store',
                'therapist_appointments.edit',
                'therapist_appointments.update',
                'therapist_appointments.destroy',
                'logout',
            ];

            $currentRouteName = $request->route()?->getName();

            if (!in_array($currentRouteName, $allowedRoutes, true)) {
                abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
            }
        }

        return $next($request);
    }
}
