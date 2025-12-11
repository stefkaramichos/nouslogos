<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        // dropdown lists
        $customers     = Customer::orderBy('last_name')->get();
        $professionals = Professional::orderBy('last_name')->get();
        $companies     = Company::orderBy('name')->get();

        // filters
        $from            = $request->input('from');
        $to              = $request->input('to');
        if (!$request->hasAny(['from','to','customer_id','professional_id','company_id','status','payment_status','payment_method'])) {
            $from = now()->toDateString();
            $to   = now()->toDateString();
        }
        $customerId      = $request->input('customer_id');
        $professionalId  = $request->input('professional_id');
        $companyId       = $request->input('company_id');
        $status          = $request->input('status');
        $paymentStatus   = $request->input('payment_status');
        $paymentMethod   = $request->input('payment_method');

        // base query
        $query = Appointment::with(['customer', 'professional', 'company', 'payment'])
            ->orderBy('start_time', 'desc');

        if ($from) {
            $query->whereDate('start_time', '>=', $from);
        }

        if ($to) {
            $query->whereDate('start_time', '<=', $to);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($professionalId) {
            $query->where('professional_id', $professionalId);
        }

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // get results
        $appointments = $query->get();

        // collection filters for payments
        if ($paymentStatus && $paymentStatus !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentStatus) {
                $total = $a->total_price ?? 0;
                $paid  = $a->payment->amount ?? 0;

                if ($paymentStatus === 'unpaid') {
                    return $paid <= 0;
                }

                if ($paymentStatus === 'partial') {
                    return $paid > 0 && $paid < $total;
                }

                if ($paymentStatus === 'full') {
                    return $total > 0 && $paid >= $total;
                }

                return true;
            });
        }

        if ($paymentMethod && $paymentMethod !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentMethod) {
                if (!$a->payment) {
                    return false;
                }
                return $a->payment->method === $paymentMethod;
            });
        }

        // ✅ manual pagination (25 per page)
        $perPage = 25;
        $currentPage = Paginator::resolveCurrentPage() ?: 1;

        $currentItems = $appointments
            ->values() // reset keys
            ->forPage($currentPage, $perPage);

        $appointments = new LengthAwarePaginator(
            $currentItems,
            $appointments->count(),
            $perPage,
            $currentPage,
            [
                'path'  => $request->url(),
                'query' => $request->query(), // keep filters in pagination links
            ]
        );

        // filters for view
        $filters = [
            'from'            => $from,
            'to'              => $to,
            'customer_id'     => $customerId,
            'professional_id' => $professionalId,
            'company_id'      => $companyId,
            'status'          => $status ?? 'all',
            'payment_status'  => $paymentStatus ?? 'all',
            'payment_method'  => $paymentMethod ?? 'all',
        ];

        return view('appointments.index', compact(
            'appointments',
            'filters',
            'customers',
            'professionals',
            'companies'
        ));
    }

    public function getLastForCustomer(Request $request)
    {
        $customerId = $request->query('customer_id');

        if (!$customerId) {
            return response()->json(['found' => false]);
        }

        $appointment = Appointment::where('customer_id', $customerId)
            ->with(['professional', 'company'])
            ->orderByDesc('start_time')
            ->first();

        if (!$appointment) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'               => true,
            'professional_id'     => $appointment->professional_id,
            'company_id'          => $appointment->company_id,
            'status'              => $appointment->status,
            'total_price'         => $appointment->total_price,
            'professional_amount' => $appointment->professional_amount,
            'notes'               => $appointment->notes,
        ]);
    }

    public function create()
    {
        $customers     = Customer::orderBy('last_name')->get();
        $professionals = Professional::whereIn('role', ['owner', 'therapist'])
            ->orderBy('last_name')
            ->get();
        $companies     = Company::all();

        return view('appointments.create', compact('customers', 'professionals', 'companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'customer_id'           => 'required|exists:customers,id',
                'professional_id'       => 'required|exists:professionals,id',
                'company_id'            => 'required|exists:companies,id',
                'start_time'            => 'required|date',
                'end_time'              => 'nullable|date|after_or_equal:start_time',
                'status'                => 'nullable|string',
                'total_price'           => 'nullable|numeric|min:0',
                'notes'                 => 'nullable|string',
                'mark_as_paid'          => 'nullable|boolean',
                'payment_amount'        => 'nullable|numeric|min:0',
                // ΝΕΟ πεδίο: ειδικό ποσό επαγγελματία για αυτό το ραντεβού (override)
                'professional_amount'   => 'nullable|numeric|min:0',
                // ΝΕΟ πεδίο: πόσες εβδομάδες να δημιουργηθεί (1–52)
                'weeks'                 => 'nullable|integer|min:1|max:52',
            ],
            [
                'customer_id.required'     => 'Ο πελάτης είναι υποχρεωτικός.',
                'professional_id.required' => 'Ο επαγγελματίας είναι υποχρεωτικός.',
                'company_id.required'      => 'Η εταιρεία είναι υποχρεωτική.',
                'start_time.required'      => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
            ]
        );

        // Πόσες εβδομάδες επανάληψη; default 1
        $weeks = (int)($data['weeks'] ?? 1);
        unset($data['weeks']); // δεν υπάρχει στο table

        $professional = Professional::findOrFail($data['professional_id']);

        // Αν δεν δώσεις total_price, παίρνουμε τη χρέωση του επαγγελματία
        $total = $data['total_price'] ?? $professional->service_fee;

        // Βάση: ποσό ανά ραντεβού από το profile του επαγγελματία
        $baseProfessionalAmount = $professional->percentage_cut; // ΠΟΣΟ, όχι ποσοστό %

        // Default ποσό επαγγελματία = αυτό
        $professionalAmount = $baseProfessionalAmount;

        // Αν συμπλήρωσες ειδικό ποσό στο ραντεβού, κάνουμε override
        if (array_key_exists('professional_amount', $data) && $data['professional_amount'] !== null) {
            $professionalAmount = $data['professional_amount'];
        }

        // Δεν αφήνουμε ποτέ ο επαγγελματίας να πάρει παραπάνω από total
        if ($professionalAmount > $total) {
            $professionalAmount = $total;
        }

        $companyAmount = $total - $professionalAmount;

        $data['total_price']         = $total;
        $data['professional_amount'] = $professionalAmount;
        $data['company_amount']      = $companyAmount;
        $data['created_by']          = Auth::id();

        // Διαχείριση ημερομηνιών για επαναλαμβανόμενα ραντεβού
        $startTime = Carbon::parse($data['start_time']);
        $endTime   = isset($data['end_time']) ? Carbon::parse($data['end_time']) : null;

        $createdAppointments = [];

        for ($i = 0; $i < $weeks; $i++) {
            $currentData = $data;

            $currentData['start_time'] = $startTime->copy()->addWeeks($i);

            if ($endTime) {
                $currentData['end_time'] = $endTime->copy()->addWeeks($i);
            }

            $appointment = Appointment::create($currentData);
            $createdAppointments[] = $appointment;

            // Πληρωμή μόνο για το πρώτο ραντεβού
            if ($i === 0 && $request->boolean('mark_as_paid')) {
                $paymentAmount = $data['payment_amount'] ?? $total;

                if ($paymentAmount > 0) {
                    Payment::create([
                        'appointment_id' => $appointment->id,
                        'customer_id'    => $appointment->customer_id,
                        'amount'         => $paymentAmount,
                        'is_full'        => $paymentAmount >= $total,
                        'paid_at'        => now(),
                        'method'         => null,
                        'notes'          => 'Καταχώρηση από τη φόρμα δημιουργίας ραντεβού.',
                    ]);
                }
            }
        }

        $message = count($createdAppointments) === 1
            ? 'Το ραντεβού δημιουργήθηκε επιτυχώς!'
            : 'Δημιουργήθηκαν ' . count($createdAppointments) . ' εβδομαδιαία ραντεβού επιτυχώς!';

        // ---- Χειρισμός redirect ----
        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))
                ->with('success', $message);
        }

        return redirect()->route('appointments.index')
            ->with('success', $message);
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['customer', 'professional', 'company', 'payment']);

        return view('appointments.show', compact('appointment'));
    }

    public function edit(Appointment $appointment)
    {
        $appointment->load(['customer', 'professional', 'company']);

        $customers     = Customer::orderBy('last_name')->get();
        $professionals = Professional::whereIn('role', ['owner', 'therapist'])
            ->orderBy('last_name')
            ->get();
        $companies     = Company::all();

        return view('appointments.edit', compact('appointment', 'customers', 'professionals', 'companies'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $request->validate(
            [
                'customer_id'           => 'required|exists:customers,id',
                'professional_id'       => 'required|exists:professionals,id',
                'company_id'            => 'required|exists:companies,id',
                'start_time'            => 'required|date',
                'end_time'              => 'nullable|date|after_or_equal:start_time',
                'status'                => 'nullable|string',
                'total_price'           => 'nullable|numeric|min:0',
                'notes'                 => 'nullable|string',
                // το ίδιο override πεδίο και εδώ
                'professional_amount'   => 'nullable|numeric|min:0',
            ],
            [
                'customer_id.required'     => 'Ο πελάτης είναι υποχρεωτικός.',
                'professional_id.required' => 'Ο επαγγελματίας είναι υποχρεωτικός.',
                'company_id.required'      => 'Η εταιρεία είναι υποχρεωτική.',
                'start_time.required'      => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
            ]
        );

        // Ξαναυπολογίζουμε οικονομικά με βάση τον επαγγελματία
        $professional = Professional::findOrFail($data['professional_id']);

        $total = $data['total_price'] ?? $professional->service_fee;

        // Βάση = ποσό ανά ραντεβού από τον επαγγελματία
        $baseProfessionalAmount = $professional->percentage_cut;

        $professionalAmount = $baseProfessionalAmount;

        if (array_key_exists('professional_amount', $data) && $data['professional_amount'] !== null) {
            $professionalAmount = $data['professional_amount'];
        }

        if ($professionalAmount > $total) {
            $professionalAmount = $total;
        }

        $companyAmount = $total - $professionalAmount;

        $data['total_price']         = $total;
        $data['professional_amount'] = $professionalAmount;
        $data['company_amount']      = $companyAmount;

        $appointment->update($data);

        $redirectTo = $request->input('redirect_to');

        if ($redirectTo) {
            return redirect($redirectTo)
                ->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
        }

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
    }

    public function destroy(Request $request, Appointment $appointment)
    {
        $appointment->payments()->delete();
        $appointment->delete();

        $redirectTo = $request->input('redirect_to');

        if ($redirectTo) {
            return redirect($redirectTo)
                ->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
        }

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
    }
}
