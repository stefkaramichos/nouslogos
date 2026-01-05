<?php

namespace App\Http\Controllers;

use App\Models\TherapistAppointment;
use App\Models\Customer;
use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TherapistAppointmentController extends Controller
{
    /**
     * Λίστα ραντεβού θεραπευτών
     */
    public function index(Request $request)
    {
        $user = Auth::user(); // Professional (role owner/therapist/grammatia)

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $from       = $request->input('from');
        $to         = $request->input('to');
        $customerId = $request->input('customer_id');
        $quick      = $request->input('quick'); // today|tomorrow|week|month

        // ---------------------------------------------------
        // Quick date filters override from/to if provided
        // ---------------------------------------------------
        if (!empty($quick)) {
            $now = Carbon::now();

            switch ($quick) {
                case 'today':
                    $from = $now->toDateString();
                    $to   = $now->toDateString();
                    break;

                case 'tomorrow':
                    $t = $now->copy()->addDay();
                    $from = $t->toDateString();
                    $to   = $t->toDateString();
                    break;

                case 'week':
                    // Ελλάδα: συνήθως Δευτέρα-Κυριακή
                    $from = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                    $to   = $now->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
                    break;

                case 'month':
                    $from = $now->copy()->startOfMonth()->toDateString();
                    $to   = $now->copy()->endOfMonth()->toDateString();
                    break;

                default:
                    // άγνωστο -> μην πειράξεις
                    break;
            }
        }

        // ---------------------------
        // professional_id με default τον owner
        // ---------------------------
        $professionalId = null;

        if ($user->role === 'owner') {
            if ($request->has('professional_id')) {
                // "" → όλοι οι επαγγελματίες
                // "14" → συγκεκριμένος
                $professionalId = $request->input('professional_id') ?: null;
            } else {
                // Πρώτη φόρτωση → default ο συνδεδεμένος owner
                $professionalId = $user->id;
            }
        }

        $query = TherapistAppointment::with(['customer', 'professional']);

        if ($user->role === 'therapist') {
            // Therapist βλέπει ΜΟΝΟ τα δικά του
            $query->where('professional_id', $user->id);
        }

        if ($user->role === 'owner') {
            // Owner βλέπει ΟΛΑ
            if (!empty($professionalId)) {
                $query->where('professional_id', $professionalId);
            }
        }

        $user_role = $user->role;

        if (!empty($from)) {
            $query->whereDate('start_time', '>=', $from);
        }

        if (!empty($to)) {
            $query->whereDate('start_time', '<=', $to);
        }

        if (!empty($customerId)) {
            $query->where('customer_id', $customerId);
        }

        // ✅ Order by date DESC + pagination
        $appointments = $query
            ->orderBy('start_time', 'desc')
            ->paginate(15)
            ->withQueryString();

        // ✅ Customers dropdown:
        if ($user->role === 'therapist') {
            $customers = Customer::where('is_active', 1)
                ->whereHas('professionals', function ($q) use ($user) {
                    $q->where('professionals.id', $user->id);
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        } else {
            $customers = Customer::where('is_active', 1)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

        }

        $professionals = [];

        if ($user->role === 'owner') {
            $professionals = Professional::where('role', '!=', 'grammatia')
                ->orderBy('last_name')
                ->get();
        }

        return view('therapist_appointments.index', compact(
            'appointments',
            'from',
            'to',
            'quick',
            'user',
            'customers',
            'customerId',
            'professionals',
            'professionalId',
            'user_role'
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

        if ($user->role === 'therapist') {
            $customers = Customer::where('is_active', 1)
                ->whereHas('professionals', function ($q) use ($user) {
                    $q->where('professionals.id', $user->id);
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

        } else {
            $customers = Customer::where('is_active', 1)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }

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
                'customer_id' => [
                    'required',
                    'exists:customers,id',
                    function ($attribute, $value, $fail) use ($user) {
                        if ($user->role === 'therapist') {
                            $allowed = Customer::where('id', $value)
                                ->whereHas('professionals', function ($q) use ($user) {
                                    $q->where('professionals.id', $user->id);
                                })
                                ->exists();

                            if (!$allowed) {
                                $fail('Ο πελάτης δεν ανήκει στον συγκεκριμένο θεραπευτή.');
                            }
                        }
                    },
                ],
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

    public function edit(TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        if ($user->role === 'therapist' &&
            $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        if ($user->role === 'therapist') {
            $customers = Customer::where(function ($q) use ($user, $therapistAppointment) {
                    $q->where('is_active', 1)
                    ->whereHas('professionals', function ($qq) use ($user) {
                        $qq->where('professionals.id', $user->id);
                    });
                })
                ->orWhere('id', $therapistAppointment->customer_id)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

        } else {
            $customers = Customer::where('is_active', 1)
                ->orWhere('id', $therapistAppointment->customer_id)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }

        return view('therapist_appointments.edit', [
            'appointment' => $therapistAppointment,
            'customers'   => $customers,
            'user'        => $user,
        ]);
    }

    public function update(Request $request, TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        if ($user->role === 'therapist' &&
            $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        $data = $request->validate(
            [
                'customer_id' => [
                    'required',
                    'exists:customers,id',
                    function ($attribute, $value, $fail) use ($user) {
                        if ($user->role === 'therapist') {
                            $allowed = Customer::where('id', $value)
                                ->whereHas('professionals', function ($q) use ($user) {
                                    $q->where('professionals.id', $user->id);
                                })
                                ->exists();

                            if (!$allowed) {
                                $fail('Ο πελάτης δεν ανήκει στον συγκεκριμένο θεραπευτή.');
                            }
                        }
                    },
                ],
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

    public function destroy(TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        if ($user->role === 'therapist' &&
            $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        $therapistAppointment->delete();

        return redirect()
            ->route('therapist_appointments.index')
            ->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
    }
}
