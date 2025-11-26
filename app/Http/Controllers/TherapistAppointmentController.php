<?php

namespace App\Http\Controllers;

use App\Models\TherapistAppointment;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TherapistAppointmentController extends Controller
{
    // λίστα ραντεβών θεραπευτή
    public function index(Request $request)
    {
        $user = Auth::user();

        // Μόνο therapist μπαίνει εδώ
        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $from = $request->input('from');
        $to   = $request->input('to');

        $query = TherapistAppointment::with('customer')
            ->where('professional_id', $user->id)
            ->orderBy('start_time', 'asc');

        if ($from) {
            $query->whereDate('start_time', '>=', $from);
        }

        if ($to) {
            $query->whereDate('start_time', '<=', $to);
        }

        $appointments = $query->get();

        return view('therapist_appointments.index', compact(
            'appointments',
            'from',
            'to',
            'user',
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
