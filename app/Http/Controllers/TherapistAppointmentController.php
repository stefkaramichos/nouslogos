<?php

namespace App\Http\Controllers;

use App\Models\TherapistAppointment;
use App\Models\Customer;
use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TherapistAppointmentController extends Controller
{
    // λίστα ραντεβών θεραπευτή
   public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $from       = $request->input('from');
        $to         = $request->input('to');
        $customerId = $request->input('customer_id');

        // --- εδώ κάνουμε default τον logged-in professional για owner ---
        $professionalId = null;

        if ($user->role === 'owner') {
            if ($request->has('professional_id')) {
                // Αν υπάρχει στο query string:
                //  - αν είναι κενό => "Όλοι οι επαγγελματίες" (χωρίς φίλτρο)
                //  - αν έχει τιμή => συγκεκριμένος επαγγελματίας
                $professionalId = $request->input('professional_id') ?: null;
            } else {
                // Πρώτη φόρτωση σελίδας → default ο συνδεδεμένος owner
                $professionalId = $user->id;
            }
        }
        // ---------------------------------------------------------------

        $query = TherapistAppointment::with(['customer', 'professional']);

        if ($user->role === 'therapist') {
            // Therapist βλέπει μόνο τα δικά του
            $query->where('professional_id', $user->id);
        }

        if ($user->role === 'owner') {
            // Owner βλέπει επαγγελματίες της εταιρείας του
            $query->whereHas('professional', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });

            // Αν υπάρχει professionalId (είτε default, είτε επιλεγμένος)
            if ($professionalId) {
                $query->where('professional_id', $professionalId);
            }
        }

        if ($from) {
            $query->whereDate('start_time', '>=', $from);
        }

        if ($to) {
            $query->whereDate('start_time', '<=', $to);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $appointments = $query->orderBy('start_time', 'asc')->get();

        $customers = Customer::orderBy('last_name')->get();
        $professionals = [];

        if ($user->role === 'owner') {
            $professionals = Professional::where('company_id', $user->company_id)
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


    // φόρμα δημιουργίας
    public function create()
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $customers = Customer::orderBy('last_name')->get();

        return view('therapist_appointments.create', compact('customers', 'user'));
    }

    // αποθήκευση νέου ραντεβού
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

        if (
            !$user ||
            ($user->role !== 'therapist' && $user->role !== 'owner') ||
            $therapistAppointment->professional_id !== $user->id
        ) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

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

        if (
            !$user ||
            ($user->role !== 'therapist' && $user->role !== 'owner')||
            $therapistAppointment->professional_id !== $user->id
        ) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
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

        $therapistAppointment->update([
            // professional_id δεν αλλάζει, είναι ο τρέχων therapist
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

        if (
            !$user ||
            ($user->role !== 'therapist' && $user->role !== 'owner') ||
            $therapistAppointment->professional_id !== $user->id
        ) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        $therapistAppointment->delete();

        return redirect()
            ->route('therapist_appointments.index')
            ->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
    }
}
