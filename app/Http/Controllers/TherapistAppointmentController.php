<?php

namespace App\Http\Controllers;

use App\Models\TherapistAppointment;
use App\Models\Customer;
use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TherapistAppointmentController extends Controller
{
    // Λίστα ραντεβού θεραπευτών
    public function index(Request $request)
    {
        $user = Auth::user();

        // Μόνο therapist ή owner (όπως τους χειρίζεσαι μέσω guards)
        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner' && $user->role !== 'grammatia')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $from           = $request->input('from');
        $to             = $request->input('to');
        $customerId     = $request->input('customer_id');
        $professionalId = $request->input('professional_id'); // από τα φίλτρα, nullable

        $query = TherapistAppointment::with(['customer', 'professional']);

        if ($user->role === 'therapist') {
            // Αν συνδέεσαι ως therapist (μέσω Professional guard)
            $query->where('professional_id', $user->id);
        } elseif ($user->role === 'owner' || $user->role === 'grammatia') {
            // Owner / γραμματεία → βλέπουν όλα τα ραντεβού, ανεξαρτήτως company

            if (!empty($professionalId)) {
                $query->where('professional_id', $professionalId);
            }
        }

        if (!empty($from)) {
            $query->whereDate('start_time', '>=', $from);
        }

        if (!empty($to)) {
            $query->whereDate('start_time', '<=', $to);
        }

        if (!empty($customerId)) {
            $query->where('customer_id', $customerId);
        }

        $appointments = $query->orderBy('start_time', 'asc')->get();

        $customers = Customer::orderBy('last_name')->get();
        $professionals = [];

        // Owner: λίστα με ΟΛΟΥΣ τους επαγγελματίες εκτός από όσους έχουν ρόλο "grammatia"
        if ($user->role === 'owner' || $user->role === 'grammatia') {
            $professionals = Professional::where('role', '!=', 'grammatia')
                ->orderBy('last_name')
                ->get();
        }

        return view('therapist_appointments.index', compact(
            'appointments',
            'from',
            'to',
            'user',
            'customers',
            'customerId',
            'professionals',
            'professionalId',
        ));
    }

    // Φόρμα δημιουργίας
    public function create()
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner' && $user->role !== 'grammatia')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $customers = Customer::orderBy('last_name')->get();

        return view('therapist_appointments.create', compact('customers', 'user'));
    }

    // Αποθήκευση νέου ραντεβού
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner' && $user->role !== 'grammatia')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $data = $request->validate(
            [
                'customer_id' => 'required|exists:customers,id',
                'start_time'  => 'required|date',
                'notes'       => 'nullable|string',
            ],
            [
                'customer_id.required' => 'Ο πελάτης είναι υποχρεωτικός.',
                'start_time.required'  => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
            ]
        );

        // Αν οι therapists κάνουν login από τον πίνακα professionals,
        // ίσως εδώ να θες `professional_id` από άλλο guard.
        TherapistAppointment::create([
            'professional_id' => $user->id,
            'customer_id'     => $data['customer_id'],
            'start_time'      => $data['start_time'],
            'notes'           => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('therapist_appointments.index')
            ->with('success', 'Το ραντεβού καταχωρήθηκε επιτυχώς.');
    }

    // ✏️ Φόρμα επεξεργασίας ραντεβού
    public function edit(TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner' && $user->role !== 'grammatia')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Therapist: μόνο τα δικά του
        if ($user->role === 'therapist' &&
            $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Owner / γραμματεία: μπορούν να επεξεργαστούν όλα τα ραντεβού

        $customers = Customer::orderBy('last_name')->get();

        return view('therapist_appointments.edit', [
            'appointment' => $therapistAppointment,
            'customers'   => $customers,
            'user'        => $user,
        ]);
    }

    // 💾 Αποθήκευση αλλαγών ραντεβού
    public function update(Request $request, TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner' && $user->role !== 'grammatia')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Therapist: μόνο τα δικά του
        if ($user->role === 'therapist' &&
            $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Owner / γραμματεία: μπορούν να ενημερώσουν όλα τα ραντεβού

        $data = $request->validate(
            [
                'customer_id' => 'required|exists:customers,id',
                'start_time'  => 'required|date',
                'notes'       => 'nullable|string',
            ],
            [
                'customer_id.required' => 'Ο πελάτης είναι υποχρεωτικός.',
                'start_time.required'  => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
            ]
        );

        $therapistAppointment->update([
            'customer_id' => $data['customer_id'],
            'start_time'  => $data['start_time'],
            'notes'       => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('therapist_appointments.index')
            ->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
    }

    // 🗑 Διαγραφή ραντεβού
    public function destroy(TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner' && $user->role !== 'grammatia')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Therapist: μόνο τα δικά του
        if ($user->role === 'therapist' &&
            $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Owner / γραμματεία: μπορούν να διαγράψουν όλα τα ραντεβού

        $therapistAppointment->delete();

        return redirect()
            ->route('therapist_appointments.index')
            ->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
    }
}
