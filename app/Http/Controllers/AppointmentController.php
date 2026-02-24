<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Payment;
use App\Models\CustomerPrepayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
            if (!$appointment->payments()->exists()) {
                Payment::create([
                    'appointment_id' => $appointment->id,
                    'customer_id'    => $appointment->customer_id,
                    'amount'         => 0,
                    'is_full'        => 1,
                    'paid_at'        => now(),
                    'method'         => null,
                    'tax'            => 'N',
                    'bank'           => null,
                    'notes'          => '[AUTO_ZERO] Μηδενική χρέωση - αυτόματη εξόφληση.',
                    'created_by'     => Auth::id(),
                ]);
            }
        } else {
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
    // VIEW + PERIOD (day/week/month/all)
    // -----------------------------
    $view = $request->input('view', 'week'); // week | day | month | table
    if (!in_array($view, ['week','day','month','table'], true)) {
        $view = 'week';
    }

    $nav   = $request->input('nav');         // prev | next
    $day   = $request->input('day');         // Y-m-d (base date for day/week)
    $month = $request->input('month');       // Y-m

    // default base date
    $baseDate = $day ? Carbon::parse($day) : now();

    // If month view and month not provided -> current month
    if ($view === 'month' && !$month) {
        $month = now()->format('Y-m');
    }

    // -----------------------------
    // NAVIGATION (prev/next)
    // -----------------------------
    if ($nav === 'prev' || $nav === 'next') {
        if ($view === 'day') {
            $baseDate = $nav === 'prev' ? $baseDate->copy()->subDay() : $baseDate->copy()->addDay();
        } elseif ($view === 'week') {
            $baseDate = $nav === 'prev' ? $baseDate->copy()->subWeek() : $baseDate->copy()->addWeek();
        } elseif ($view === 'month') {
            $m = Carbon::createFromFormat('Y-m', $month ?: now()->format('Y-m'))->startOfMonth();
            $m = $nav === 'prev' ? $m->subMonth() : $m->addMonth();
            $month = $m->format('Y-m');
        }
    }

    // reflect back to strings
    $day = $baseDate->toDateString();

    // -----------------------------
    // Compute from/to depending on view
    // -----------------------------
    $from = null;
    $to   = null;

    $weekStart = null;
    $weekEnd   = null;
    $weekDays  = collect();

    if ($view === 'day') {
        $from = $baseDate->copy()->startOfDay();
        $to   = $baseDate->copy()->endOfDay();
    } elseif ($view === 'week') {
        $weekStart = $baseDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd   = $baseDate->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $from = $weekStart->copy();
        $to   = $weekEnd->copy();

        // build 7 days array for header
        $tmp = $weekStart->copy();
        for ($i = 0; $i < 7; $i++) {
            $weekDays->push($tmp->copy());
            $tmp->addDay();
        }
    } elseif ($view === 'month') {
        $m = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $from = $m->copy()->startOfMonth()->startOfDay();
        $to   = $m->copy()->endOfMonth()->endOfDay();
    } else {
        // table/all => no date restriction unless from/to given (optional)
        // Αν θες να έχει "all time" άστο έτσι.
        $from = null;
        $to   = null;
    }

    // -----------------------------
    // Other filters
    // -----------------------------
    $customerId     = $request->input('customer_id');
    $professionalId = $request->input('professional_id');
    $companyId      = $request->input('company_id');
    $status         = $request->input('status', 'all');
    $paymentStatus  = $request->input('payment_status', 'all');
    $paymentMethod  = $request->input('payment_method', 'all');

    // -----------------------------
    // Query appointments
    // -----------------------------
    $query = Appointment::query()
        ->with(['customer', 'professional', 'company', 'payments'])
        ->leftJoin('customers', 'customers.id', '=', 'appointments.customer_id')
        ->orderBy('appointments.start_time', 'asc')
        ->orderBy('customers.last_name', 'asc')
        ->select('appointments.*');

    if ($from) $query->where('appointments.start_time', '>=', $from);
    if ($to)   $query->where('appointments.start_time', '<=', $to);

    if ($customerId)     $query->where('appointments.customer_id', $customerId);
    if ($professionalId) $query->where('appointments.professional_id', $professionalId);
    if ($companyId)      $query->where('appointments.company_id', $companyId);

    // status filter (token μέσα σε comma-separated string)
    if ($status && $status !== 'all') {
        $query->where(function ($q) use ($status) {
            $q->where('status', $status)
              ->orWhere('status', 'like', $status . ',%')
              ->orWhere('status', 'like', '%,' . $status . ',%')
              ->orWhere('status', 'like', '%,' . $status);
        });
    }

    $appointments = $query->get();

    // payment_status filter
    if ($paymentStatus && $paymentStatus !== 'all') {
        $appointments = $appointments->filter(function ($a) use ($paymentStatus) {
            $total = (float)($a->total_price ?? 0);

            // total_price <= 0 => θεωρείται full
            if ($total <= 0) return $paymentStatus === 'full';

            $paid = (float)$a->payments->sum('amount');

            if ($paymentStatus === 'unpaid')  return $paid <= 0;
            if ($paymentStatus === 'partial') return $paid > 0 && $paid < $total;
            if ($paymentStatus === 'full')    return $paid >= $total;

            return true;
        })->values();
    }

    // payment_method filter
    if ($paymentMethod && $paymentMethod !== 'all') {
        $appointments = $appointments->filter(function ($a) use ($paymentMethod) {
            return $a->payments->where('method', $paymentMethod)->sum('amount') > 0;
        })->values();
    }

    // -----------------------------
    // Pagination: μόνο στο table view (για calendar view θέλεις ΟΛΑ)
    // -----------------------------
    if ($view === 'table') {
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
    } else {
        // calendar view: κράτα collection
        $appointments = $appointments->values();
    }

    // -----------------------------
    // Selected label
    // -----------------------------
    $selectedLabel = 'Όλα';
    if ($view === 'day') {
        $selectedLabel = $baseDate->copy()->locale('el')->translatedFormat('D d/m/Y');
    } elseif ($view === 'week') {
        $selectedLabel =
            $weekStart->copy()->locale('el')->translatedFormat('D d/m/Y') .
            ' - ' .
            $weekEnd->copy()->locale('el')->translatedFormat('D d/m/Y');
    } elseif ($view === 'month') {
        $selectedLabel = Carbon::createFromFormat('Y-m', $month)->locale('el')->translatedFormat('F Y');
    }

    // -----------------------------
    // Prev/Next URLs (κρατά όλα τα φίλτρα)
    // -----------------------------
    $baseQuery = $request->query();
    unset($baseQuery['nav']); // always rebuild

    // ensure we always pass current view
    $baseQuery['view'] = $view;

    // keep correct date param for each view
    if ($view === 'month') {
        $baseQuery['month'] = $month;
        unset($baseQuery['day']);
    } else {
        $baseQuery['day'] = $day; // base date for week/day
        unset($baseQuery['month']);
    }

    $prevUrl = $request->url() . '?' . http_build_query(array_merge($baseQuery, ['nav' => 'prev']));
    $nextUrl = $request->url() . '?' . http_build_query(array_merge($baseQuery, ['nav' => 'next']));

    $filters = [
        'view'            => $view,
        'day'             => $day,
        'month'           => $month,
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
        'selectedLabel',
        'weekStart',
        'weekEnd',
        'weekDays'
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
            ->orderBy('first_name')
            ->get();

        $companies = Company::orderBy('name')->get();

        return view('appointments.create', compact('customers', 'professionals', 'companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'customer_id' => 'required|exists:customers,id',
                'redirect_to' => 'nullable|string',

                'appointments' => 'required|array|min:1',

                'appointments.*.professional_id' => 'required|exists:professionals,id',
                'appointments.*.company_id'      => 'required|exists:companies,id',
                'appointments.*.start_time'      => 'required|date',

                'appointments.*.weeks' => 'nullable|integer|min:1|max:52',

                'appointments.*.status'   => 'nullable|array',
                'appointments.*.status.*' => 'in:logotherapia,psixotherapia,ergotherapia,omadiki,eidikos,aksiologisi',

                'appointments.*.total_price'         => 'nullable|numeric|min:0',
                'appointments.*.professional_amount' => 'nullable|numeric|min:0',
                'appointments.*.notes'               => 'nullable|string|max:5000',
            ],
            [
                'appointments.required' => 'Πρέπει να υπάρχει τουλάχιστον μία γραμμή ραντεβού.',
            ]
        );

        $customerId = (int)$data['customer_id'];
        $rows = $data['appointments'];

        $createdAppointments = [];

        foreach ($rows as $row) {
            $professional = Professional::findOrFail($row['professional_id']);

            $statusCsv = isset($row['status'])
                ? implode(',', array_values(array_filter($row['status'])))
                : null;

            $weeks = (int)($row['weeks'] ?? 1);

            $total = array_key_exists('total_price', $row) && $row['total_price'] !== null
                ? (float)$row['total_price']
                : (float)($professional->service_fee ?? 0);

            $professionalAmount = (float)($professional->percentage_cut ?? 0);
            if (array_key_exists('professional_amount', $row) && $row['professional_amount'] !== null && $row['professional_amount'] !== '') {
                $professionalAmount = (float)$row['professional_amount'];
            }

            $companyAmount = $total - $professionalAmount;

            $startTime = Carbon::parse($row['start_time']);

            for ($i = 0; $i < $weeks; $i++) {
                $appointment = Appointment::create([
                    'customer_id'         => $customerId,
                    'professional_id'     => (int)$row['professional_id'],
                    'company_id'          => (int)$row['company_id'],
                    'start_time'          => $startTime->copy()->addWeeks($i),
                    'end_time'            => null,
                    'status'              => $statusCsv,
                    'total_price'         => $total,
                    'professional_amount' => $professionalAmount,
                    'company_amount'      => $companyAmount,
                    'notes'               => $row['notes'] ?? null,
                    'created_by'          => Auth::id(),
                ]);

                $createdAppointments[] = $appointment;

                $this->ensureZeroPricePaid($appointment);
                $this->applyPrepaymentToAppointment($appointment);
            }
        }

        $message = count($createdAppointments) === 1
            ? 'Το ραντεβού δημιουργήθηκε επιτυχώς!'
            : 'Δημιουργήθηκαν ' . count($createdAppointments) . ' ραντεβού επιτυχώς!';

        if (!empty($data['redirect_to'])) {
            return redirect($data['redirect_to'])->with('success', $message);
        }

        return redirect()->route('appointments.index')->with('success', $message);
    }

    public function show(Appointment $appointment)
    {
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

                'status'                => 'nullable|array',
                'status.*'              => 'in:logotherapia,psixotherapia,ergotherapia,omadiki,eidikos,aksiologisi',

                'total_price'           => 'nullable|numeric|min:0',
                'notes'                 => 'nullable|string',
                'professional_amount'   => 'nullable|numeric|min:0',
            ]
        );

        $data['status'] = isset($data['status'])
            ? implode(',', array_values(array_filter($data['status'])))
            : null;

        $professional = Professional::findOrFail($data['professional_id']);

        $total = $data['total_price'] ?? $professional->service_fee;

        $professionalAmount = (float)($professional->percentage_cut ?? 0);
        if (array_key_exists('professional_amount', $data) && $data['professional_amount'] !== null) {
            $professionalAmount = (float)$data['professional_amount'];
        }

        $companyAmount = $total - $professionalAmount;

        $data['total_price']         = $total;
        $data['professional_amount'] = $professionalAmount;
        $data['company_amount']      = $companyAmount;

        $appointment->update($data);

        $this->ensureZeroPricePaid($appointment);

        $redirectTo = $request->input('redirect_to');

        if ($redirectTo) {
            return redirect($redirectTo)->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
        }

        return redirect()->route('appointments.index')->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
    }

    public function storeMultiple(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'rows' => 'required|array|min:1',

            'rows.*.professional_id' => 'required|exists:professionals,id',
            'rows.*.company_id'      => 'required|exists:companies,id',
            'rows.*.start_time'      => 'required|date',
            'rows.*.weeks'           => 'nullable|integer|min:1|max:52',

            'rows.*.status'   => 'nullable|array',
            'rows.*.status.*' => 'in:logotherapia,psixotherapia,ergotherapia,omadiki,eidikos,aksiologisi',

            'rows.*.total_price'         => 'nullable|numeric|min:0',
            'rows.*.professional_amount' => 'nullable|numeric|min:0',
            'rows.*.notes'               => 'nullable|string',
        ]);

        $customerId = (int)$data['customer_id'];
        $rows = $data['rows'];

        $created = 0;

        foreach ($rows as $row) {
            $professional = Professional::findOrFail($row['professional_id']);

            $weeks = (int)($row['weeks'] ?? 1);

            $statusCsv = isset($row['status'])
                ? implode(',', array_values(array_filter($row['status'])))
                : null;

            $total = $row['total_price'] ?? $professional->service_fee;

            $professionalAmount = (float)($professional->percentage_cut ?? 0);
            if (array_key_exists('professional_amount', $row) && $row['professional_amount'] !== null) {
                $professionalAmount = (float)$row['professional_amount'];
            }

            $companyAmount = $total - $professionalAmount;

            $startTime = Carbon::parse($row['start_time']);

            for ($i = 0; $i < $weeks; $i++) {
                $appointment = Appointment::create([
                    'customer_id'          => $customerId,
                    'professional_id'      => (int)$row['professional_id'],
                    'company_id'           => (int)$row['company_id'],
                    'start_time'           => $startTime->copy()->addWeeks($i),
                    'status'               => $statusCsv,
                    'total_price'          => (float)$total,
                    'professional_amount'  => (float)$professionalAmount,
                    'company_amount'       => (float)$companyAmount,
                    'notes'                => $row['notes'] ?? null,
                    'created_by'           => Auth::id(),
                ]);

                $this->ensureZeroPricePaid($appointment);
                $this->applyPrepaymentToAppointment($appointment); // ✅ ΕΔΩ ΕΛΕΙΠΕ
                $created++;
            }
        }

        $redirectTo = $request->input('redirect_to');
        if ($redirectTo && !str_starts_with($redirectTo, url('/'))) {
            $redirectTo = null;
        }

        return $redirectTo
            ? redirect()->to($redirectTo)->with('success', "Δημιουργήθηκαν {$created} ραντεβού επιτυχώς!")
            : redirect()->route('appointments.index')->with('success', "Δημιουργήθηκαν {$created} ραντεβού επιτυχώς!");
    }

    public function updatePrice(Request $request, Appointment $appointment)
    {
        $request->validate([
            'total_price' => 'required|numeric|min:0'
        ]);

        $appointment->update([
            'total_price' => $request->total_price
        ]);

        $this->ensureZeroPricePaid($appointment);

        return response()->json([
            'success' => true,
            'new_price' => number_format((float)$appointment->total_price, 2, ',', '.')
        ]);
    }

    /**
     * ✅ APPLY PREPAYMENT BALANCES TO THIS APPOINTMENT (oldest: cashY -> cashN -> card)
     * - δημιουργεί Payments
     * - μειώνει balances
     * - αν μηδενιστούν όλα -> σβήνει το prepayment record
     */
    private function applyPrepaymentToAppointment(Appointment $appointment): void
    {
        $total = (float)($appointment->total_price ?? 0);
        if ($total <= 0) return;

        DB::transaction(function () use ($appointment, $total) {

            $appointment->load('payments');

            $paid = (float)$appointment->payments->sum('amount');
            $due  = max(0, $total - $paid);
            if ($due <= 0.0001) return;

            $prepay = CustomerPrepayment::where('customer_id', $appointment->customer_id)
                ->lockForUpdate()
                ->first();

            if (!$prepay) return;

            $paidAt = $prepay->last_paid_at ?? now();

            // ✅ ΠΑΡΕ BALANCES ΣΕ LOCAL VARS (ΟΧΙ & σε model properties)
            $cashY = (float)($prepay->cash_y_balance ?? 0);
            $cashN = (float)($prepay->cash_n_balance ?? 0);
            $card  = (float)($prepay->card_balance ?? 0);

            $consume = function (float $available, float $need): float {
                if ($available <= 0 || $need <= 0) return 0.0;
                return min($available, $need);
            };

            // 1) cash Y
            if ($due > 0.0001 && $cashY > 0.0001) {
                $use = $consume($cashY, $due);
                if ($use > 0.0001) {
                    $payment = Payment::create([
                        'appointment_id' => $appointment->id,
                        'customer_id'    => $appointment->customer_id,
                        'amount'         => $use,
                        'is_full'        => 0,
                        'paid_at'        => $paidAt,
                        'method'         => 'cash',
                        'tax'            => 'Y',
                        'bank'           => null,
                        'notes'          => '[PREPAY] Αυτόματη χρέωση από προπληρωμή.',
                        'created_by'     => Auth::id(),
                    ]);
                    $appointment->payments->push($payment);

                    $cashY -= $use;
                    $due   -= $use;
                }
            }

            // 2) cash N
            if ($due > 0.0001 && $cashN > 0.0001) {
                $use = $consume($cashN, $due);
                if ($use > 0.0001) {
                    $payment = Payment::create([
                        'appointment_id' => $appointment->id,
                        'customer_id'    => $appointment->customer_id,
                        'amount'         => $use,
                        'is_full'        => 0,
                        'paid_at'        => $paidAt,
                        'method'         => 'cash',
                        'tax'            => 'N',
                        'bank'           => null,
                        'notes'          => '[PREPAY] Αυτόματη χρέωση από προπληρωμή.',
                        'created_by'     => Auth::id(),
                    ]);
                    $appointment->payments->push($payment);

                    $cashN -= $use;
                    $due   -= $use;
                }
            }

            // 3) card
            if ($due > 0.0001 && $card > 0.0001) {
                $use = $consume($card, $due);
                if ($use > 0.0001) {
                    $payment = Payment::create([
                        'appointment_id' => $appointment->id,
                        'customer_id'    => $appointment->customer_id,
                        'amount'         => $use,
                        'is_full'        => 0,
                        'paid_at'        => $paidAt,
                        'method'         => 'card',
                        'tax'            => 'Y',
                        'bank'           => $prepay->card_bank,
                        'notes'          => '[PREPAY] Αυτόματη χρέωση από προπληρωμή.',
                        'created_by'     => Auth::id(),
                    ]);
                    $appointment->payments->push($payment);

                    $card -= $use;
                    $due  -= $use;
                }
            }

            // ✅ cleanup tiny negatives
            if ($cashY < 0.0001) $cashY = 0;
            if ($cashN < 0.0001) $cashN = 0;
            if ($card  < 0.0001) $card  = 0;

            // ✅ γράψε πίσω στο model
            $prepay->cash_y_balance = $cashY;
            $prepay->cash_n_balance = $cashN;
            $prepay->card_balance   = $card;

            // ✅ αν όλα μηδέν -> delete record
            if ($cashY <= 0.0001 && $cashN <= 0.0001 && $card <= 0.0001) {
                $prepay->delete();
            } else {
                $prepay->save();
            }

            // ✅ recalc is_full
            $paidNow = (float)$appointment->payments->sum('amount');
            Payment::where('appointment_id', $appointment->id)->update(['is_full' => 0]);

            if ($total > 0 && $paidNow >= $total) {
                $last = Payment::where('appointment_id', $appointment->id)
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->first();

                if ($last) {
                    $last->is_full = 1;
                    $last->save();
                }
            }
        });
    }

    
public function updatePaidTotal(Request $request, Appointment $appointment)
{
    $data = $request->validate([
        'paid_total' => 'required|numeric|min:0',
        'method'     => 'required|in:cash,card',
        'tax'        => 'required|in:Y,N',
    ]);

    $newTotal   = (float)$data['paid_total'];
    $totalPrice = (float)($appointment->total_price ?? 0);

    if ($newTotal > $totalPrice + 0.0001) {
        return response()->json([
            'success' => false,
            'message' => 'Το ποσό πληρωμής δεν μπορεί να είναι μεγαλύτερο από το ποσό του ραντεβού.',
        ], 422);
    }

    // ✅ rule: κάρτα πάντα με απόδειξη
    if ($data['method'] === 'card') {
        $data['tax'] = 'Y';
    }

    $result = null;

    DB::transaction(function () use ($appointment, $newTotal, $data, &$result) {

        // lock όλα τα payments του appointment
        $payments = Payment::where('appointment_id', $appointment->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $currentTotal = (float)$payments->sum('amount');
        $delta = $newTotal - $currentTotal;

        // defaults για paid_at/bank από τελευταία πληρωμή
        $last = $payments->first();
        $defaultPaidAt = $last?->paid_at ?: now();
        $defaultBank   = $last?->bank ?? null;

        // =========================
        // A) ΜΕΙΩΣΗ
        // =========================
        if ($delta < -0.0001) {
            $toRemove = abs($delta);

            foreach ($payments as $p) {
                if ($toRemove <= 0) break;

                $amt = (float)$p->amount;

                if ($amt <= $toRemove + 0.0001) {
                    $toRemove -= $amt;
                    $p->delete();
                } else {
                    $p->amount = $amt - $toRemove;
                    $p->save();
                    $toRemove = 0;
                }
            }
        }

        // =========================
        // B) ΑΥΞΗΣΗ
        // =========================
        if ($delta > 0.0001) {
            Payment::create([
                'appointment_id' => $appointment->id,
                'customer_id'    => $appointment->customer_id,
                'amount'         => $delta,
                'is_full'        => 0,
                'paid_at'        => $defaultPaidAt,
                'method'         => $data['method'],
                'tax'            => $data['tax'],
                'bank'           => $data['method'] === 'card' ? $defaultBank : null,
                'notes'          => '[INLINE_EDIT] Update paid total + method/tax from appointments table.',
                'created_by'     => Auth::id(),
            ]);
        }

        // =========================
        // ✅ ΝΕΟ: ΕΦΑΡΜΟΓΗ METHOD/TAX ΑΚΟΜΑ ΚΑΙ ΑΝ ΔΕΝ ΑΛΛΑΞΕ ΤΟ ΠΟΣΟ (delta≈0)
        // ή μετά από μείωση/αύξηση για να μη μένουν "μείξεις"
        // =========================
        $bankToSet = ($data['method'] === 'card') ? $defaultBank : null;

        Payment::where('appointment_id', $appointment->id)->update([
            'method'     => $data['method'],
            'tax'        => $data['tax'],
            'bank'       => $bankToSet,
            'updated_at' => now(),
        ]);

        // recalc is_full
        $appointment->load('payments');

        $total = (float)($appointment->total_price ?? 0);
        $paid  = (float)$appointment->payments->sum('amount');

        Payment::where('appointment_id', $appointment->id)->update(['is_full' => 0]);

        if ($total > 0 && $paid >= $total) {
            $lastPay = Payment::where('appointment_id', $appointment->id)
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->first();

            if ($lastPay) {
                $lastPay->is_full = 1;
                $lastPay->save();
            }
        }

        $result = ['success' => true, 'paid_total' => $paid];
    });

    return response()->json([
        'success'    => true,
        'paid_total' => (float)($result['paid_total'] ?? 0),
        'formatted'  => number_format((float)($result['paid_total'] ?? 0), 2, ',', '.') . ' €',
    ]);
}


    
    public function destroy(Request $request, Appointment $appointment)
    {
        DB::transaction(function () use ($appointment) {
            // Φόρτωσε τις πληρωμές πριν διαγράψεις το ραντεβού
            $appointment->load('payments');

            $payments = $appointment->payments;

            if ($payments->count() > 0) {
                // Βρες ή δημιούργησε prepayment record
                $prepay = CustomerPrepayment::where('customer_id', $appointment->customer_id)
                    ->lockForUpdate()
                    ->first();

                if (!$prepay) {
                    $prepay = CustomerPrepayment::create([
                        'customer_id'      => $appointment->customer_id,
                        'cash_y_balance'   => 0,
                        'cash_n_balance'   => 0,
                        'card_balance'     => 0,
                        'last_paid_at'     => now(),
                    ]);
                }

                // Ομαδοποίησε πληρωμές ανά method/tax και πρόσθεσε στο prepayment
                foreach ($payments as $payment) {
                    $amount = (float)$payment->amount;

                    if ($payment->method === 'cash') {
                        if ($payment->tax === 'Y') {
                            $prepay->cash_y_balance += $amount;
                        } else {
                            $prepay->cash_n_balance += $amount;
                        }
                    } elseif ($payment->method === 'card') {
                        $prepay->card_balance += $amount;
                        if ($payment->bank) {
                            $prepay->card_bank = $payment->bank;
                        }
                    }
                }

                $prepay->last_paid_at = now();
                $prepay->save();

                // Διάγραψε όλες τις πληρωμές του ραντεβού
                Payment::where('appointment_id', $appointment->id)->delete();
            }

            // Διάγραψε το ραντεβού
            $appointment->delete();
        });

        $redirectTo = $request->input('redirect_to');

        if ($redirectTo) {
            return redirect($redirectTo)->with('success', 'Το ραντεβού διαγράφηκε και τα ποσά επιστράφηκαν στην προπληρωμή.');
        }

        return redirect()->route('appointments.index')->with('success', 'Το ραντεβού διαγράφηκε και τα ποσά επιστράφηκαν στην προπληρωμή.');
    }

   
}
