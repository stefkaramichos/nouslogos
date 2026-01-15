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
     * Index
     * - Therapist: βλέπει μόνο τα δικά του (professional_id = user id) και φίλτρο πελάτη
     * - Owner: βλέπει όλα, και φίλτρο "Με" (customer/professional)
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        $from       = $request->input('from');
        $to         = $request->input('to');
        $quick      = $request->input('quick');

        // Για therapist (παλιά συμπεριφορά)
        $customerId = $request->input('customer_id');

        // Για owner (νέο unified φίλτρο)
        $partyType = $request->input('party_type'); // customer|professional
        $partyId   = $request->input('party_id');   // id

        // Quick date filters
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
                    $from = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                    $to   = $now->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
                    break;
                case 'month':
                    $from = $now->copy()->startOfMonth()->toDateString();
                    $to   = $now->copy()->endOfMonth()->toDateString();
                    break;
            }
        }

        // professional_id filter (μόνο owner)
        $professionalId = null;
        if ($user->role === 'owner') {
            if ($request->has('professional_id')) {
                $professionalId = $request->input('professional_id') ?: null;
            } else {
                $professionalId = $user->id;
            }
        }

        $query = TherapistAppointment::with(['customer', 'professional', 'withProfessional']);

        if ($user->role === 'therapist') {
            $query->where('professional_id', $user->id);
        } else {
            // owner
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

        // Filters:
        if ($user->role === 'owner') {
            // ΝΕΟ: party filter
            if (!empty($partyType) && !empty($partyId)) {
                if ($partyType === 'customer') {
                    $query->where('customer_id', $partyId);
                } elseif ($partyType === 'professional') {
                    $query->where('with_professional_id', $partyId);
                }
            }
        } else {
            // therapist: παλιό φίλτρο customer
            if (!empty($customerId)) {
                $query->where('customer_id', $customerId);
            }
        }

        $appointments = $query
            ->orderBy('start_time', 'desc')
            ->paginate(15)
            ->withQueryString();

        $user_role = $user->role;

        // Customers list
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

        // Owner list of professionals for professional_id filter
        $professionals = [];
        if ($user->role === 'owner') {
            $professionals = Professional::where('role', '!=', 'grammatia')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }

        // Owner list of professionals to appear inside the "Με" party dropdown
        $allProfessionalsForParties = collect();
        if ($user->role === 'owner') {
            $allProfessionalsForParties = Professional::where('role', '!=', 'grammatia')
                ->orderBy('last_name')
                ->orderBy('first_name')
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
            'user_role',
            'partyType',
            'partyId',
            'allProfessionalsForParties'
        ));
    }

    /**
     * Create
     * - Therapist: μόνο customers
     * - Owner: customers + professionals
     */
    public function create()
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        // Customers
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

        // Professionals for "appointment with professional" (ONLY OWNER)
        $withProfessionals = collect();
        if ($user->role === 'owner') {
            $withProfessionals = Professional::where('role', '!=', 'grammatia')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }

        return view('therapist_appointments.create', compact('customers', 'withProfessionals', 'user'));
    }

    /**
     * Store
     * - Therapist: customer_id required, with_professional_id must be null
     * - Owner: exactly one of customer_id / with_professional_id
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        if ($user->role === 'therapist') {
            $data = $request->validate(
                [
                    'customer_id' => [
                        'required',
                        'exists:customers,id',
                        function ($attribute, $value, $fail) use ($user) {
                            $allowed = Customer::where('id', $value)
                                ->whereHas('professionals', function ($q) use ($user) {
                                    $q->where('professionals.id', $user->id);
                                })
                                ->exists();
                            if (!$allowed) {
                                $fail('Ο πελάτης δεν ανήκει στον συγκεκριμένο θεραπευτή.');
                            }
                        },
                    ],
                    'with_professional_id' => 'nullable', // ignored
                    'start_time' => 'required|date',
                    'notes'      => 'nullable|string',
                ],
                [
                    'customer_id.required' => 'Ο πελάτης είναι υποχρεωτικός.',
                    'start_time.required'  => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
                ]
            );

            TherapistAppointment::create([
                'professional_id'      => $user->id,
                'customer_id'          => $data['customer_id'],
                'with_professional_id' => null,
                'start_time'           => $data['start_time'],
                'notes'                => $data['notes'] ?? null,
            ]);

            return redirect()->route('therapist_appointments.index')->with('success', 'Το ραντεβού καταχωρήθηκε επιτυχώς.');
        }

        // OWNER
        $data = $request->validate(
            [
                'customer_id' => 'nullable|exists:customers,id',
                'with_professional_id' => [
                    'nullable',
                    'exists:professionals,id',
                ],
                'start_time' => 'required|date',
                'notes'      => 'nullable|string',
            ],
            [
                'start_time.required'  => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
            ]
        );

        // enforce exactly one
        $hasCustomer = !empty($data['customer_id']);
        $hasProf     = !empty($data['with_professional_id']);

        if ((!$hasCustomer && !$hasProf) || ($hasCustomer && $hasProf)) {
            return back()
                ->withErrors(['customer_id' => 'Επιλέξτε ΜΟΝΟ Πελάτη ή ΜΟΝΟ Επαγγελματία.'])
                ->withInput();
        }

        TherapistAppointment::create([
            'professional_id'      => $user->id,
            'customer_id'          => $data['customer_id'] ?? null,
            'with_professional_id' => $data['with_professional_id'] ?? null,
            'start_time'           => $data['start_time'],
            'notes'                => $data['notes'] ?? null,
        ]);

        return redirect()->route('therapist_appointments.index')->with('success', 'Το ραντεβού καταχωρήθηκε επιτυχώς.');
    }

    /**
     * Edit
     */
    public function edit(TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        if ($user->role === 'therapist' && $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        // Customers
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

        // Professionals (ONLY OWNER)
        $withProfessionals = collect();
        if ($user->role === 'owner') {
            $withProfessionals = Professional::where('role', '!=', 'grammatia')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }

        return view('therapist_appointments.edit', [
            'appointment'       => $therapistAppointment,
            'customers'         => $customers,
            'withProfessionals' => $withProfessionals,
            'user'              => $user,
        ]);
    }

    /**
     * Update
     */
    public function update(Request $request, TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        if ($user->role === 'therapist' && $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        if ($user->role === 'therapist') {
            $data = $request->validate(
                [
                    'customer_id' => [
                        'required',
                        'exists:customers,id',
                        function ($attribute, $value, $fail) use ($user) {
                            $allowed = Customer::where('id', $value)
                                ->whereHas('professionals', function ($q) use ($user) {
                                    $q->where('professionals.id', $user->id);
                                })
                                ->exists();
                            if (!$allowed) {
                                $fail('Ο πελάτης δεν ανήκει στον συγκεκριμένο θεραπευτή.');
                            }
                        },
                    ],
                    'with_professional_id' => 'nullable', // ignored
                    'start_time' => 'required|date',
                    'notes'      => 'nullable|string',
                ]
            );

            $therapistAppointment->update([
                'customer_id'          => $data['customer_id'],
                'with_professional_id' => null,
                'start_time'           => $data['start_time'],
                'notes'                => $data['notes'] ?? null,
            ]);

            return redirect()->route('therapist_appointments.index')->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
        }

        // OWNER
        $data = $request->validate(
            [
                'customer_id' => 'nullable|exists:customers,id',
                'with_professional_id' => 'nullable|exists:professionals,id',
                'start_time' => 'required|date',
                'notes'      => 'nullable|string',
            ]
        );

        $hasCustomer = !empty($data['customer_id']);
        $hasProf     = !empty($data['with_professional_id']);

        if ((!$hasCustomer && !$hasProf) || ($hasCustomer && $hasProf)) {
            return back()
                ->withErrors(['customer_id' => 'Επιλέξτε ΜΟΝΟ Πελάτη ή ΜΟΝΟ Επαγγελματία.'])
                ->withInput();
        }

        $therapistAppointment->update([
            'customer_id'          => $data['customer_id'] ?? null,
            'with_professional_id' => $data['with_professional_id'] ?? null,
            'start_time'           => $data['start_time'],
            'notes'                => $data['notes'] ?? null,
        ]);

        return redirect()->route('therapist_appointments.index')->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
    }

    /**
     * Destroy
     */
    public function destroy(TherapistAppointment $therapistAppointment)
    {
        $user = Auth::user();

        if (!$user || ($user->role !== 'therapist' && $user->role !== 'owner')) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        if ($user->role === 'therapist' && $therapistAppointment->professional_id !== $user->id) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το ραντεβού.');
        }

        $therapistAppointment->delete();

        return redirect()->route('therapist_appointments.index')->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
    }
}
