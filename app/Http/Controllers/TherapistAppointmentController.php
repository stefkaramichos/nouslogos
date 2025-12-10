<?php

namespace App\Http\Controllers;

use App\Models\TherapistAppointment;
use App\Models\Customer;
use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TherapistAppointmentController extends Controller
{
    /**
     * Λίστα ραντεβού θεραπευτών
     */
    public function index(Request $request)
    {
        $user = Auth::user(); // εδώ είναι Professional (με role owner/therapist/grammatia)

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $from       = $request->input('from');
        $to         = $request->input('to');
        $customerId = $request->input('customer_id');

        // ---------------------------
        // professional_id με default τον owner
        // ---------------------------
        $professionalId = null;

        if ($user->role === 'owner') {
            if ($request->has('professional_id')) {
                // Αν υπάρχει στο query:
                //  - "" → όλοι οι επαγγελματίες
                //  - "14" → συγκεκριμένος επαγγελματίας
                $professionalId = $request->input('professional_id') ?: null;
            } else {
                // Πρώτη φόρτωση → default ο συνδεδεμένος owner
                // π.χ. owner με id=1 → βλέπει ραντεβού του professional_id = 1
                $professionalId = $user->id;
            }
        }

        $query = TherapistAppointment::with(['customer', 'professional']);

        if ($user->role === 'therapist') {
            // Therapist βλέπει ΜΟΝΟ τα δικά του
            $query->where('professional_id', $user->id);
        }

        if ($user->role === 'owner') {
            // Owner βλέπει ΟΛΑ τα ραντεβού (χωρίς company restriction)
            // Αν έχει οριστεί professionalId (είτε default owner, είτε επιλεγμένος από φίλτρο)
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

        $customers     = Customer::orderBy('last_name')->get();
        $professionals = [];

        if ($user->role === 'owner') {
            // Όλοι οι επαγγελματίες εκτός από role=grammatia
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

    /**
     * Φόρμα δημιουργίας ραντεβού
     */
    public function create()
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $customers = Customer::orderBy('last_name')->get();

        return view('therapist_appointments.create', compact('customers', 'user'));
    }

    /**
     * Αποθήκευση νέου ραντεβού
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
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

        TherapistAppointment::create([
            'professional_id' => $user->id, // ο συνδεδεμένος επαγγελματίας (owner ή therapist)
            'customer_id'     => $data['customer_id'],
            'start_time'      => $data['start_time'],
            'notes'           => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('therapist_appointments.index')
            ->with('success', 'Το ραντεβού καταχωρήθηκε επιτυχώς.');
    }

    /**
     * ✏️ Φόρμα επεξεργασίας ραντεβού
     */
    public function edit(TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Therapist: μόνο τα δικά του
        if ($user->role === 'therapist' &&
            $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Owner: μπορεί να επεξεργαστεί οποιοδήποτε ραντεβού

        $customers = Customer::orderBy('last_name')->get();

        return view('therapist_appointments.edit', [
            'appointment' => $therapistAppointment,
            'customers'   => $customers,
            'user'        => $user,
        ]);
    }

    /**
     * 💾 Αποθήκευση αλλαγών ραντεβού
     */
    public function update(Request $request, TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Therapist: μόνο τα δικά του
        if ($user->role === 'therapist' &&
            $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Owner: μπορεί να ενημερώσει οποιοδήποτε ραντεβού

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

    /**
     * 🗑 Διαγραφή ραντεβού
     */
    public function destroy(TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Therapist: μόνο τα δικά του
        if ($user->role === 'therapist' &&
            $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Owner: μπορεί να διαγράψει οποιοδήποτε ραντεβού

        $therapistAppointment->delete();

        return redirect()
            ->route('therapist_appointments.index')
            ->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
    }
}
