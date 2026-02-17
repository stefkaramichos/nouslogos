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

        // ✅ Αν είναι therapist → επιτρέπουμε μόνο whitelist routes
        if ($user && $user->role === 'therapist') {

            $allowedRoutes = [
                'dashboard',

                // therapist appointments
                'therapist_appointments.index',
                'therapist_appointments.create',
                'therapist_appointments.store',
                'therapist_appointments.edit',
                'therapist_appointments.update',
                'therapist_appointments.destroy',

                // ✅ documents (για να βλέπουν/κατεβάζουν όσα επιτρέπονται από canBeViewedBy)
                'documents.index',
                'documents.view',
                'documents.download',

                // auth
                'logout',
            ];

            $currentRouteName = $request->route()?->getName();

            // ✅ Αν route δεν έχει όνομα, μην αφήνεις therapist να περάσει (ασφάλεια)
            if (!$currentRouteName || !in_array($currentRouteName, $allowedRoutes, true)) {
                abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
            }
        }

        return $next($request);
    }
}
