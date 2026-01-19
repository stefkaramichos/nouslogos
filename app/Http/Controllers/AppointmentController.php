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
                    'tax'            => null,
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
        $customers     = Customer::orderBy('last_name')->get();
        $professionals = Professional::orderBy('last_name')->get();
        $companies     = Company::orderBy('name')->get();

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
            $month = null; // όπως το είχες
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
            ->select('appointments.*');

        if ($from) $query->whereDate('start_time', '>=', $from);
        if ($to)   $query->whereDate('start_time', '<=', $to);

        if ($customerId)     $query->where('appointments.customer_id', $customerId);
        if ($professionalId) $query->where('appointments.professional_id', $professionalId);
        if ($companyId)      $query->where('appointments.company_id', $companyId);

        if ($status && $status !== 'all') {
            $query->where(function ($q) use ($status) {
                $q->where('status', $status)
                  ->orWhere('status', 'like', $status . ',%')
                  ->orWhere('status', 'like', '%,' . $status . ',%')
                  ->orWhere('status', 'like', '%,' . $status);
            });
        }

        $appointments = $query->get();

        if ($paymentStatus && $paymentStatus !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentStatus) {
                $total = (float) ($a->total_price ?? 0);

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

        if ($paymentMethod && $paymentMethod !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentMethod) {
                return $a->payments->where('method', $paymentMethod)->sum('amount') > 0;
            });
        }

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
