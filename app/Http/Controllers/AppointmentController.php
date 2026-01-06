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

        // -----------------------------
        // NEW: Period filter (range/day/month/all) + prev/next
        // -----------------------------
        $range = $request->input('range', 'month'); // day/month/all
        $nav   = $request->input('nav');
        $day   = $request->input('day');
        $month = $request->input('month');

        // Αν δεν έχει σταλεί τίποτα (ούτε φίλτρα), default = ΣΗΜΕΡΑ (day)
        if (!$request->hasAny([
            'range','day','month','nav',
            'customer_id','professional_id','company_id','status','payment_status','payment_method',
            'from','to' // για backward compatibility
        ])) {
            $range = 'month';
            $month = now()->format('Y-m');
            $month = null;
        }

        // Αν έρθουν τα παλιά from/to, τα μετατρέπουμε σε day range για συμβατότητα
        // (ώστε να μην χαλάσουν παλιά links / bookmarks)
        $legacyFrom = $request->input('from');
        $legacyTo   = $request->input('to');
        if ($legacyFrom && $legacyTo && !$request->hasAny(['range','day','month'])) {
            // αν είναι ίδια μέρα -> day, αλλιώς all (ή μπορούμε month αλλά είναι πιο tricky)
            if ($legacyFrom === $legacyTo) {
                $range = 'day';
                $day   = $legacyFrom;
            } else {
                // κρατάμε range=all και αφήνουμε from/to να δουλέψουν όπως πριν
                $range = 'all';
            }
        }

        // Normalization
        if ($range === 'day') {
            $day = $day ?: now()->toDateString();
            $month = null;
        } elseif ($range === 'month') {
            $month = $month ?: now()->format('Y-m');
            $day = null;
        } else {
            $day = null;
            $month = null;
        }

        // Prev/Next navigation
        if ($nav === 'prev' || $nav === 'next') {
            if ($range === 'day') {
                $base = Carbon::parse($day ?: now()->toDateString());
                $base = $nav === 'prev' ? $base->subDay() : $base->addDay();
                $day  = $base->toDateString();
            } elseif ($range === 'month') {
                $base = Carbon::createFromFormat('Y-m', $month ?: now()->format('Y-m'))->startOfMonth();
                $base = $nav === 'prev' ? $base->subMonth() : $base->addMonth();
                $month = $base->format('Y-m');
            }
        }

        // Υπολογίζουμε from/to από range
        $from = null;
        $to   = null;

        if ($range === 'day' && $day) {
            $from = Carbon::parse($day)->toDateString();
            $to   = Carbon::parse($day)->toDateString();
        } elseif ($range === 'month' && $month) {
            $m    = Carbon::createFromFormat('Y-m', $month);
            $from = $m->copy()->startOfMonth()->toDateString();
            $to   = $m->copy()->endOfMonth()->toDateString();
        } else {
            // range === 'all' -> αφήνουμε from/to null (εκτός αν ήρθαν legacy)
            if ($legacyFrom) $from = $legacyFrom;
            if ($legacyTo)   $to   = $legacyTo;
        }

        // Label
        $selectedLabel = 'Όλα';
        if ($range === 'day' && $day) {
            $selectedLabel = Carbon::parse($day)->locale('el')->translatedFormat('D d/m/Y');
        } elseif ($range === 'month' && $month) {
            $selectedLabel = Carbon::createFromFormat('Y-m', $month)->locale('el')->translatedFormat('F Y');
        }

        // -----------------------------
        // Other filters
        // -----------------------------
        $customerId     = $request->input('customer_id');
        $professionalId = $request->input('professional_id');
        $companyId      = $request->input('company_id');
        $status         = $request->input('status');
        $paymentStatus  = $request->input('payment_status');
        $paymentMethod  = $request->input('payment_method');

        // ✅ base query (payments όχι payment)
        $query = Appointment::with(['customer', 'professional', 'company', 'payments'])
            ->orderBy('start_time', 'desc');

        if ($from) $query->whereDate('start_time', '>=', $from);
        if ($to)   $query->whereDate('start_time', '<=', $to);

        if ($customerId)     $query->where('customer_id', $customerId);
        if ($professionalId) $query->where('professional_id', $professionalId);
        if ($companyId)      $query->where('company_id', $companyId);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $appointments = $query->get();

        // ✅ payment_status filter (με βάση paid_total)
        if ($paymentStatus && $paymentStatus !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentStatus) {
                $total = (float) ($a->total_price ?? 0);
                $paid  = (float) $a->payments->sum('amount');

                if ($paymentStatus === 'unpaid')  return $paid <= 0;
                if ($paymentStatus === 'partial') return $paid > 0 && $paid < $total;
                if ($paymentStatus === 'full')    return $total > 0 && $paid >= $total;

                return true;
            });
        }

        // ✅ payment_method filter (υπάρχει έστω μία πληρωμή με method)
        if ($paymentMethod && $paymentMethod !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentMethod) {
                return $a->payments->where('method', $paymentMethod)->sum('amount') > 0;
            });
        }

        // manual pagination (25 per page)
        $perPage = 25;
        $currentPage = Paginator::resolveCurrentPage() ?: 1;

        $currentItems = $appointments->values()->forPage($currentPage, $perPage);

        $appointments = new LengthAwarePaginator(
            $currentItems,
            $appointments->count(),
            $perPage,
            $currentPage,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        // Prev/Next URLs (κρατάμε όλα τα query params)
        $prevUrl = null;
        $nextUrl = null;

        if ($range !== 'all') {
            $baseQuery = $request->query();
            unset($baseQuery['nav']);
            unset($baseQuery['from'], $baseQuery['to']); // από εδώ και πέρα οδηγούμε με range/day/month

            if ($range === 'day') {
                $baseQuery['range'] = 'day';
                $baseQuery['day']   = $day ?: now()->toDateString();
                unset($baseQuery['month']);
            } elseif ($range === 'month') {
                $baseQuery['range'] = 'month';
                $baseQuery['month'] = $month ?: now()->format('Y-m');
                unset($baseQuery['day']);
            }

            $prevUrl = $request->url() . '?' . http_build_query(array_merge($baseQuery, ['nav' => 'prev']));
            $nextUrl = $request->url() . '?' . http_build_query(array_merge($baseQuery, ['nav' => 'next']));
        }

        $filters = [
            // NEW
            'range'          => $range,
            'day'            => $day,
            'month'          => $month,

            // OLD (still useful)
            'from'           => $from,
            'to'             => $to,

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
            'companies',
            'prevUrl',
            'nextUrl',
            'selectedLabel'
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
        $customers = Customer::where('is_active', 1)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

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

        $customers = Customer::where('is_active', 1)
            ->orWhere('id', $appointment->customer_id) // ✅ κρατάει selectable τον υπάρχοντα
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $professionals = Professional::whereIn('role', ['owner', 'therapist'])
            ->orderBy('last_name')
            ->get();

        $companies = Company::all();

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

    public function updatePrice(Request $request, Appointment $appointment)
    {
        $request->validate([
            'total_price' => 'required|numeric|min:0'
        ]);

        $appointment->update([
            'total_price' => $request->total_price
        ]);

        return response()->json([
            'success' => true,
            'new_price' => number_format($appointment->total_price, 2, ',', '.')
        ]);
    }

    public function destroy(Request $request, Appointment $appointment)
    {
        $appointment->delete(); // Soft delete

        $redirectTo = $request->input('redirect_to');

        // If we have a stored redirect URL → go back there
        if ($redirectTo) {
            return redirect($redirectTo)
                ->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
        }

        // Fallback
        return redirect()
            ->route('appointments.index')
            ->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
    }

}
