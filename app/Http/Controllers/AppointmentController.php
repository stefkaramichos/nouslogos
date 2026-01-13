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
    /**
     * ✅ Αν total_price <= 0 (ή null), θεωρούμε το ραντεβού "paid"
     * και (προαιρετικά) δημιουργούμε Payment 0€ για να υπάρχει ίχνος.
     */
    private function ensureZeroPricePaid(Appointment $appointment): void
    {
        $total = (float) ($appointment->total_price ?? 0);

        if ($total <= 0) {
            // Αν δεν υπάρχει καμία πληρωμή, δημιουργούμε μία 0€
            if (!$appointment->payments()->exists()) {
                Payment::create([
                    'appointment_id' => $appointment->id,
                    'customer_id'    => $appointment->customer_id,
                    'amount'         => 0,
                    'is_full'        => 1,
                    'paid_at'        => now(),
                    'method'         => null,
                    'notes'          => '[AUTO_ZERO] Μηδενική χρέωση - αυτόματη εξόφληση.',
                ]);
            }
        } else {
            // Αν από 0 έγινε >0, σβήνουμε τυχόν auto-zero payment για να μην "μπερδεύει"
            $appointment->payments()
                ->where('amount', 0)
                ->where('notes', 'like', '[AUTO_ZERO]%')
                ->delete();
        }
    }

    public function index(Request $request)
    {
        // dropdown lists
        $customers     = Customer::orderBy('last_name')->get();
        $professionals = Professional::orderBy('last_name')->get();
        $companies     = Company::orderBy('name')->get();

        // -----------------------------
        // Period filter (range/day/month/all) + prev/next
        // -----------------------------
        $range = $request->input('range', 'month');
        $nav   = $request->input('nav');
        $day   = $request->input('day');
        $month = $request->input('month');

        if (!$request->hasAny([
            'range','day','month','nav',
            'customer_id','professional_id','company_id','status','payment_status','payment_method',
            'from','to'
        ])) {
            $range = 'month';
            $month = now()->format('Y-m');
            $month = null; // (κρατάω όπως το είχες)
        }

        $legacyFrom = $request->input('from');
        $legacyTo   = $request->input('to');
        if ($legacyFrom && $legacyTo && !$request->hasAny(['range','day','month'])) {
            if ($legacyFrom === $legacyTo) {
                $range = 'day';
                $day   = $legacyFrom;
            } else {
                $range = 'all';
            }
        }

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
            if ($legacyFrom) $from = $legacyFrom;
            if ($legacyTo)   $to   = $legacyTo;
        }

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

        $query = Appointment::query()
            ->with(['customer', 'professional', 'company', 'payments'])
            ->leftJoin('customers', 'customers.id', '=', 'appointments.customer_id')
            ->orderBy('appointments.start_time', 'desc')
            ->orderBy('customers.last_name', 'asc')
            ->select('appointments.*'); // important to avoid column conflicts

        if ($from) $query->whereDate('start_time', '>=', $from);
        if ($to)   $query->whereDate('start_time', '<=', $to);

        if ($customerId)     $query->where('appointments.customer_id', $customerId);
        if ($professionalId) $query->where('appointments.professional_id', $professionalId);
        if ($companyId)      $query->where('appointments.company_id', $companyId);


        // ✅ status filter (token μέσα σε comma-separated string)
        if ($status && $status !== 'all') {
            $query->where(function ($q) use ($status) {
                $q->where('status', $status)
                  ->orWhere('status', 'like', $status . ',%')
                  ->orWhere('status', 'like', '%,' . $status . ',%')
                  ->orWhere('status', 'like', '%,' . $status);
            });
        }

        $appointments = $query->get();

        // ✅ payment_status filter (με κανόνα: total_price <= 0 => FULL PAID)
        if ($paymentStatus && $paymentStatus !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentStatus) {
                $total = (float) ($a->total_price ?? 0);

                // ✅ Μηδενική/κενή χρέωση => θεωρείται εξοφλημένο
                if ($total <= 0) {
                    return $paymentStatus === 'full';
                }

                $paid  = (float) $a->payments->sum('amount');

                if ($paymentStatus === 'unpaid')  return $paid <= 0;
                if ($paymentStatus === 'partial') return $paid > 0 && $paid < $total;
                if ($paymentStatus === 'full')    return $paid >= $total;

                return true;
            });
        }

        // payment_method filter
        if ($paymentMethod && $paymentMethod !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentMethod) {
                return $a->payments->where('method', $paymentMethod)->sum('amount') > 0;
            });
        }

        // manual pagination
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

        // Prev/Next URLs
        $prevUrl = null;
        $nextUrl = null;

        if ($range !== 'all') {
            $baseQuery = $request->query();
            unset($baseQuery['nav']);
            unset($baseQuery['from'], $baseQuery['to']);

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
            'range'           => $range,
            'day'             => $day,
            'month'           => $month,
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
            'status'              => $appointment->status, // μπορεί να είναι "a,b"
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

        $companies = Company::all();

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

                // ✅ MULTI STATUS
                'status'                => 'nullable|array',
                'status.*'              => 'in:logotherapia,psixotherapia,ergotherapia,omadiki,eidikos,aksiologisi',

                'total_price'           => 'nullable|numeric|min:0',
                'notes'                 => 'nullable|string',
                'mark_as_paid'          => 'nullable|boolean',
                'payment_amount'        => 'nullable|numeric|min:0',
                'professional_amount'   => 'nullable|numeric|min:0',
                'weeks'                 => 'nullable|integer|min:1|max:52',
            ],
            [
                'customer_id.required'     => 'Ο πελάτης είναι υποχρεωτικός.',
                'professional_id.required' => 'Ο επαγγελματίας είναι υποχρεωτικός.',
                'company_id.required'      => 'Η εταιρεία είναι υποχρεωτική.',
                'start_time.required'      => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
            ]
        );

        // ✅ status[] -> "a,b,c"
        $data['status'] = isset($data['status'])
            ? implode(',', array_values(array_filter($data['status'])))
            : null;

        $weeks = (int)($data['weeks'] ?? 1);
        unset($data['weeks']);

        $professional = Professional::findOrFail($data['professional_id']);

        // Αν total_price δεν δοθεί, χρησιμοποίησε service_fee
        $total = $data['total_price'] ?? $professional->service_fee;

        $baseProfessionalAmount = $professional->percentage_cut;
        $professionalAmount = $baseProfessionalAmount;

        if (array_key_exists('professional_amount', $data) && $data['professional_amount'] !== null) {
            $professionalAmount = $data['professional_amount'];
        }

        // if ($professionalAmount > $total) {
        //     $professionalAmount = $total;
        // }

        $companyAmount = $total - $professionalAmount;

        $data['total_price']         = $total;
        $data['professional_amount'] = $professionalAmount;
        $data['company_amount']      = $companyAmount;
        $data['created_by']          = Auth::id();

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

            // ✅ Αν χρέωση 0/null => auto paid (Payment 0€)
            $this->ensureZeroPricePaid($appointment);

            // Μόνο στο πρώτο ραντεβού: αν ο χρήστης τσέκαρε mark_as_paid (και δεν είναι 0€)
            if ($i === 0 && $request->boolean('mark_as_paid')) {
                $appointment->load('payments');
                $apptTotal = (float)($appointment->total_price ?? 0);

                if ($apptTotal > 0) {
                    $paymentAmount = $data['payment_amount'] ?? $apptTotal;

                    if ($paymentAmount > 0) {
                        Payment::create([
                            'appointment_id' => $appointment->id,
                            'customer_id'    => $appointment->customer_id,
                            'amount'         => $paymentAmount,
                            'is_full'        => $paymentAmount >= $apptTotal,
                            'paid_at'        => now(),
                            'method'         => null,
                            'notes'          => 'Καταχώρηση από τη φόρμα δημιουργίας ραντεβού.',
                        ]);
                    }
                }
            }
        }

        $message = count($createdAppointments) === 1
            ? 'Το ραντεβού δημιουργήθηκε επιτυχώς!'
            : 'Δημιουργήθηκαν ' . count($createdAppointments) . ' εβδομαδιαία ραντεβού επιτυχώς!';

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('success', $message);
        }

        return redirect()->route('appointments.index')->with('success', $message);
    }

    public function show(Appointment $appointment)
    {
        // ✅ διόρθωση: payments (όχι payment)
        $appointment->load(['customer', 'professional', 'company', 'payments']);
        return view('appointments.show', compact('appointment'));
    }

    public function edit(Appointment $appointment)
    {
        $appointment->load(['customer', 'professional', 'company']);

        $customers = Customer::where('is_active', 1)
            ->orWhere('id', $appointment->customer_id)
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

                // ✅ MULTI STATUS
                'status'                => 'nullable|array',
                'status.*'              => 'in:logotherapia,psixotherapia,ergotherapia,omadiki,eidikos,aksiologisi',

                'total_price'           => 'nullable|numeric|min:0',
                'notes'                 => 'nullable|string',
                'professional_amount'   => 'nullable|numeric|min:0',
            ],
            [
                'customer_id.required'     => 'Ο πελάτης είναι υποχρεωτικός.',
                'professional_id.required' => 'Ο επαγγελματίας είναι υποχρεωτικός.',
                'company_id.required'      => 'Η εταιρεία είναι υποχρεωτική.',
                'start_time.required'      => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
            ]
        );

        // ✅ status[] -> "a,b,c"
        $data['status'] = isset($data['status'])
            ? implode(',', array_values(array_filter($data['status'])))
            : null;

        $professional = Professional::findOrFail($data['professional_id']);

        $total = $data['total_price'] ?? $professional->service_fee;

        $baseProfessionalAmount = $professional->percentage_cut;
        $professionalAmount = $baseProfessionalAmount;

        if (array_key_exists('professional_amount', $data) && $data['professional_amount'] !== null) {
            $professionalAmount = $data['professional_amount'];
        }

        // if ($professionalAmount > $total) {
        //     $professionalAmount = $total;
        // }

        $companyAmount = $total - $professionalAmount;

        $data['total_price']         = $total;
        $data['professional_amount'] = $professionalAmount;
        $data['company_amount']      = $companyAmount;

        $appointment->update($data);

        // ✅ Αν total_price <= 0 => auto paid (Payment 0€). Αν >0, καθάρισε auto-zero
        $this->ensureZeroPricePaid($appointment);

        $redirectTo = $request->input('redirect_to');

        if ($redirectTo) {
            return redirect($redirectTo)->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
        }

        return redirect()->route('appointments.index')->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
    }

    public function updatePrice(Request $request, Appointment $appointment)
    {
        $request->validate([
            'total_price' => 'required|numeric|min:0'
        ]);

        $appointment->update([
            'total_price' => $request->total_price
        ]);

        // ✅ Αν μηδενίστηκε -> auto paid, αν πήγε >0 -> καθάρισε auto-zero
        $this->ensureZeroPricePaid($appointment);

        return response()->json([
            'success' => true,
            'new_price' => number_format((float)$appointment->total_price, 2, ',', '.')
        ]);
    }

    public function destroy(Request $request, Appointment $appointment)
    {
        $appointment->delete();

        $redirectTo = $request->input('redirect_to');

        if ($redirectTo) {
            return redirect($redirectTo)->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
        }

        return redirect()->route('appointments.index')->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
    }
}
