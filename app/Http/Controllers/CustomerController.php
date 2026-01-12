<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\CustomerFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /* =========================================================
     |  FILES
     ========================================================= */

    public function view(Customer $customer, CustomerFile $file)
    {
        if ((int)$file->customer_id !== (int)$customer->id) {
            abort(403);
        }

        $disk = $file->disk ?? 'local';

        if (!Storage::disk($disk)->exists($file->path)) {
            abort(404, 'Το αρχείο δεν βρέθηκε.');
        }

        return Storage::disk($disk)->response(
            $file->path,
            $file->original_name,
            ['Content-Disposition' => 'inline']
        );
    }

    public function uploadFile(Request $request, Customer $customer)
    {
        $request->validate([
            'file'  => 'required|file|max:10240', // 10MB
            'notes' => 'nullable|string|max:1000',
        ], [
            'file.required' => 'Πρέπει να επιλέξετε αρχείο.',
            'file.file'     => 'Μη έγκυρο αρχείο.',
            'file.max'      => 'Το αρχείο δεν μπορεί να ξεπερνά τα 10MB.',
        ]);

        $uploaded = $request->file('file');

        $originalName = $uploaded->getClientOriginalName();
        $mime         = $uploaded->getClientMimeType();
        $size         = $uploaded->getSize();

        $disk = 'local';

        $storedName = Str::random(12) . '_' . time() . '_' . preg_replace('/\s+/', '_', $originalName);
        $dir        = "customer-files/{$customer->id}";
        $path       = $uploaded->storeAs($dir, $storedName, $disk);

        CustomerFile::create([
            'customer_id'   => $customer->id,
            'uploaded_by'   => Auth::user()?->id,
            'original_name' => $originalName,
            'stored_name'   => $storedName,
            'path'          => $path,
            'disk'          => $disk,
            'mime_type'     => $mime,
            'size'          => $size,
            'notes'         => $request->input('notes'),
        ]);

        return back()->with('success', 'Το αρχείο ανέβηκε επιτυχώς.');
    }

    public function downloadFile(Customer $customer, CustomerFile $file)
    {
        if ((int)$file->customer_id !== (int)$customer->id) {
            abort(404);
        }

        $disk = $file->disk ?? 'local';

        if (!Storage::disk($disk)->exists($file->path)) {
            return back()->with('error', 'Το αρχείο δεν βρέθηκε στο storage.');
        }

        return Storage::disk($disk)->download($file->path, $file->original_name);
    }

    public function deleteFile(Request $request, Customer $customer, CustomerFile $file)
    {
        if ((int)$file->customer_id !== (int)$customer->id) {
            abort(404);
        }

        $disk = $file->disk ?? 'local';

        if ($file->path && Storage::disk($disk)->exists($file->path)) {
            Storage::disk($disk)->delete($file->path);
        }

        $file->delete();

        return back()->with('success', 'Το αρχείο διαγράφηκε επιτυχώς.');
    }

    /* =========================================================
     |  INDEX / CRUD CUSTOMER
     ========================================================= */

    public function index(Request $request)
    {
        $search = $request->input('search');

        // ✅ If user clicked "Όλοι"
        if ($request->boolean('clear_company')) {
            $request->session()->forget('customers_company_id');
        }

        // ✅ remember chosen company
        if (!$request->boolean('clear_company') && $request->has('company_id')) {
            $request->session()->put('customers_company_id', $request->input('company_id'));
        }

        $companyId = $request->has('company_id')
            ? $request->input('company_id')
            : $request->session()->get('customers_company_id');

        if ($companyId === '' || $companyId === null) {
            $companyId = null;
        }

        $customers = Customer::query()
            ->with(['company', 'professionals'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('company', fn($qc) => $qc->where('name', 'like', "%{$search}%"));
                });
            })

            // ✅ ACTIVE ΠΑΝΩ, DISABLED ΚΑΤΩ
            ->orderByDesc('is_active')

            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();


        $companies = Company::where('is_active', 1)->orderBy('id')->get();

        return view('customers.index', [
            'customers' => $customers,
            'companies' => $companies,
            'search'    => $search,
            'companyId' => $companyId,
        ]);
    }

    public function create()
    {
        $companies = Company::all();

        $professionals = Professional::where('is_active', 1)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('customers.create', compact('companies', 'professionals'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'phone'         => 'nullable|string|max:100',
            'email'         => 'nullable|email|max:150',
            'company_id'    => 'nullable|exists:companies,id',
            'tax_office'    => 'nullable|string|max:100',
            'vat_number'    => 'nullable|string|max:20',
            'informations'  => 'nullable|string',

            'professionals'   => 'nullable|array',
            'professionals.*' => 'exists:professionals,id',
        ]);

        $professionalIds = $data['professionals'] ?? [];
        unset($data['professionals']);

        $customer = Customer::create($data);
        $customer->professionals()->sync($professionalIds);

        return redirect()->route('customers.index')->with('success', 'Ο πελάτης δημιουργήθηκε επιτυχώς.');
    }

    public function edit(Request $request, Customer $customer)
    {
        $companies = Company::all();

        $professionals = Professional::where('is_active', 1)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $redirect = $request->input('redirect');

        $customer->load('professionals');

        return view('customers.edit', compact('customer', 'companies', 'professionals', 'redirect'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'phone'         => 'nullable|string|max:100',
            'email'         => 'nullable|email|max:150',
            'company_id'    => 'nullable|exists:companies,id',
            'tax_office'    => 'nullable|string|max:100',
            'vat_number'    => 'nullable|string|max:20',
            'informations'  => 'nullable|string',

            'professionals'   => 'nullable|array',
            'professionals.*' => 'exists:professionals,id',
        ]);

        $professionalIds = $data['professionals'] ?? [];
        unset($data['professionals']);

        $customer->update($data);
        $customer->professionals()->sync($professionalIds);

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('success', 'Ο πελάτης ενημερώθηκε επιτυχώς.');
        }

        return redirect()->route('customers.index')->with('success', 'Ο πελάτης ενημερώθηκε επιτυχώς.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Ο πελάτης διαγράφηκε επιτυχώς.');
    }

    /* =========================================================
     |  SHOW CUSTOMER + APPOINTMENTS + PAYMENTS (SPLIT)
     ========================================================= */

    public function show(Request $request, Customer $customer)
    {
        /**
         * ✅ CRITICAL:
         * - appointments.payments (hasMany) για split
         * - ΟΧΙ appointments.payment
         */
        $customer->load([
            'company',
            'professionals',
            'appointments.professional',
            'appointments.company',
            'appointments.payments',
            'appointments.creator',
            'files.uploader',
        ]);

        /**
         * 🔹 Ιστορικό πληρωμών (ομαδοποίηση ανά ημερομηνία paid_at)
         */
        $payments = Payment::where('customer_id', $customer->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $paymentsByDate = $payments->groupBy(function ($payment) {
            if (!$payment->paid_at) return 'Χωρίς ημερομηνία';
            return Carbon::parse($payment->paid_at)->toDateString(); // Y-m-d
        });

        /**
         * 🔹 Date filter για ραντεβού λίστας (μένει όπως το είχες)
         */
        $range = $request->input('range', 'month'); // month/day/all
        $nav   = $request->input('nav');

        $day   = $request->input('day');   // Y-m-d
        $month = $request->input('month'); // Y-m

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
        }

        $paymentStatus = $request->input('payment_status'); // unpaid/partial/full/all
        $paymentMethod = $request->input('payment_method'); // cash/card/all

        /**
         * 🔹 Collection appointments (όχι DB query)
         */
        $appointmentsCollection = $customer->appointments
            ->sortByDesc('start_time')
            ->values();

        $filteredAppointments = $appointmentsCollection;

        // Date range for list
        if ($from && $to) {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($from, $to) {
                if (!$a->start_time) return false;
                $d = $a->start_time->toDateString();
                return $d >= $from && $d <= $to;
            });
        }

        // Payment status based on payments sum
        if ($paymentStatus && $paymentStatus !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($paymentStatus) {
                $total = (float)($a->total_price ?? 0);
                $paid  = (float)$a->payments->sum('amount');

                return match ($paymentStatus) {
                    'unpaid'  => $paid <= 0,
                    'partial' => $paid > 0 && $paid < $total,
                    'full'    => $total > 0 && $paid >= $total,
                    default   => true,
                };
            });
        }

        // method filter (cash/card): true αν υπάρχει έστω μία πληρωμή με method
        if ($paymentMethod && $paymentMethod !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($paymentMethod) {
                return $a->payments->contains(fn($p) => $p->method === $paymentMethod);
            });
        }

        $filteredAppointments = $filteredAppointments->values();

        /**
         * 🔹 GLOBAL totals (χωρίς φίλτρα)
         */
        $allAppointments = $appointmentsCollection;

        $globalAppointmentsCount = $allAppointments->count();
        $globalTotalAmount = $allAppointments->sum(fn($a) => (float)($a->total_price ?? 0));
        $globalPaidTotal   = $allAppointments->sum(fn($a) => (float)$a->payments->sum('amount'));
        $globalOutstandingTotal = max($globalTotalAmount - $globalPaidTotal, 0);

        /**
         * 🔹 Totals filtered (αν θες)
         */
        $appointmentsCount = $filteredAppointments->count();
        $cashTotal = $filteredAppointments->sum(fn($a) => (float)$a->payments->where('method', 'cash')->sum('amount'));
        $cardTotal = $filteredAppointments->sum(fn($a) => (float)$a->payments->where('method', 'card')->sum('amount'));

        /**
         * ✅ OUTSTANDING PREVIEW (ΟΛΑ τα χρωστούμενα, χωρίς ημερομηνίες)
         */
        [$outstandingCount, $outstandingAmount] = $this->calcOutstandingForCustomer($customer->id);

        /**
         * 🔹 Prev/Next URLs
         */
        $filters = [
            'range'          => $range,
            'day'            => $day,
            'month'          => $month,
            'payment_status' => $paymentStatus ?? 'all',
            'payment_method' => $paymentMethod ?? 'all',
        ];

        $prevUrl = null;
        $nextUrl = null;

        if ($range !== 'all') {
            $baseQuery = $request->query();
            unset($baseQuery['nav']);

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

        $selectedLabel = 'Όλα';
        if ($range === 'day' && $day) {
            $selectedLabel = Carbon::parse($day)->locale('el')->translatedFormat('D d/m/Y');
        } elseif ($range === 'month' && $month) {
            $selectedLabel = Carbon::createFromFormat('Y-m', $month)->locale('el')->translatedFormat('F Y');
        }

        // pass to view
        $appointments = $filteredAppointments;

        return view('customers.show', compact(
            'customer',
            'appointments',
            'appointmentsCount',

            'globalAppointmentsCount',
            'globalTotalAmount',
            'globalPaidTotal',
            'globalOutstandingTotal',

            'cashTotal',
            'cardTotal',

            'filters',
            'paymentsByDate',

            'prevUrl',
            'nextUrl',
            'selectedLabel',

            'outstandingCount',
            'outstandingAmount'
        ));
    }

    /**
     * ✅ helper: outstanding για ΟΛΑ τα ραντεβού (total - sum(payments))
     */
    private function calcOutstandingForCustomer(int $customerId): array
    {
        $appointments = Appointment::where('customer_id', $customerId)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->with('payments')
            ->get();

        $count = 0;
        $dueTotal = 0.0;

        foreach ($appointments as $a) {
            $total = (float)($a->total_price ?? 0);
            $paid  = (float)$a->payments->sum('amount');
            $due   = max(0, $total - $paid);

            if ($due > 0.0001) {
                $count++;
                $dueTotal += $due;
            }
        }

        return [$count, round($dueTotal, 2)];
    }

    /**
     * ✅ (optional) ajax preview endpoint
     */
    public function paymentPreviewOutstanding(Request $request, Customer $customer)
    {
        [$count, $due] = $this->calcOutstandingForCustomer($customer->id);

        return response()->json([
            'count'     => $count,
            'amount'    => $due,
            'formatted' => number_format($due, 2, ',', '.') . ' €',
        ]);
    }

    /**
     * ✅ ΠΛΗΡΩΝΕΙ ΟΛΑ ΤΑ ΧΡΩΣΤΟΥΜΕΝΑ (ΧΩΡΙΣ ΗΜΕΡΟΜΗΝΙΕΣ)
     * ✅ split μετρητών: cash Y + cash N + card
     */
    public function payOutstandingSplit(Request $request, Customer $customer)
    {
        $data = $request->validate([
            // ✅ ο χρήστης διαλέγει πότε έγινε/καταχωρήθηκε η πληρωμή
            // στείλτο από input datetime-local
            'paid_at'       => 'required|date',

            'cash_y_amount' => 'nullable|numeric|min:0',
            'cash_n_amount' => 'nullable|numeric|min:0',

            'card_amount'   => 'nullable|numeric|min:0',
            'card_bank'     => 'nullable|string|max:255',

            'notes'         => 'nullable|string|max:1000',
        ], [
            'paid_at.required' => 'Πρέπει να επιλέξετε ημερομηνία/ώρα πληρωμής.',
        ]); 

        $cashY = (float)($data['cash_y_amount'] ?? 0);
        $cashN = (float)($data['cash_n_amount'] ?? 0);
        $card  = (float)($data['card_amount'] ?? 0);

        if ($cashY <= 0 && $cashN <= 0 && $card <= 0) {
            return back()->with('error', 'Βάλτε ποσό σε τουλάχιστον ένα πεδίο (Μετρητά με/χωρίς απόδειξη ή Κάρτα).');
        }

        // ✅ paid_at από user
        $paidAt = Carbon::parse($data['paid_at']);

        // όλα τα ραντεβού του πελάτη (με ποσό)
        $appointments = Appointment::where('customer_id', $customer->id)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->with('payments')
            ->orderBy('start_time')
            ->get();

        // συνολικό due
        $dueTotal = 0.0;
        foreach ($appointments as $a) {
            $total = (float)$a->total_price;
            $paid  = (float)$a->payments->sum('amount');
            $dueTotal += max(0, $total - $paid);
        }

        if ($dueTotal <= 0.0001) {
            return back()->with('error', 'Δεν υπάρχουν χρωστούμενα ραντεβού για αυτόν τον πελάτη.');
        }

        $incoming = $cashY + $cashN + $card;

        if ($incoming > $dueTotal + 0.0001) {
            return back()->with('error', 'Το ποσό που δώσατε είναι μεγαλύτερο από το συνολικό υπόλοιπο.');
        }

        DB::transaction(function () use ($appointments, $customer, $cashY, $cashN, $card, $data, $paidAt) {

            $allocate = function (float $amount, string $method, string $tax, ?string $bank = null)
                use (&$appointments, $customer, $data, $paidAt) {

                $remaining = $amount;

                foreach ($appointments as $a) {
                    if ($remaining <= 0) break;

                    $total = (float)$a->total_price;
                    $paid  = (float)$a->payments->sum('amount');
                    $due   = max(0, $total - $paid);

                    if ($due <= 0) continue;

                    $payNow = min($due, $remaining);

                    $payment = Payment::create([
                        'appointment_id' => $a->id,
                        'customer_id'    => $customer->id,
                        'amount'         => $payNow,
                        'is_full'        => false,
                        'paid_at'        => $paidAt,              // ✅ ΟΧΙ now()
                        'method'         => $method,
                        'tax'            => $tax,
                        'bank'           => $bank,
                        'notes'          => $data['notes'] ?? 'Πληρωμή χρωστούμενων (split).',
                        'created_by'     => Auth::id(),           // ✅ ποιος την πέρασε
                    ]);

                    // update in-memory
                    $a->payments->push($payment);

                    $remaining -= $payNow;
                }

                return $remaining;
            };

            // σειρά:
            if ($cashY > 0) $allocate($cashY, 'cash', 'Y', null);
            if ($cashN > 0) $allocate($cashN, 'cash', 'N', null);

            if ($card > 0) {
                $bank = $data['card_bank'] ?? null;
                $allocate($card, 'card', 'Y', $bank);
            }

            // is_full στο τελευταίο payment κάθε appointment αν καλύφθηκε
            foreach ($appointments as $a) {
                $total = (float)$a->total_price;
                $paid  = (float)$a->payments->sum('amount');

                if ($total > 0 && $paid >= $total) {
                    $last = Payment::where('appointment_id', $a->id)
                        ->orderByDesc('paid_at')
                        ->orderByDesc('id')
                        ->first();

                    if ($last) {
                        $last->is_full = true;
                        $last->save();
                    }
                }
            }
        });

        return back()->with('success', 'Η πληρωμή καταχωρήθηκε επιτυχώς.');
    }

    /**
     * ✅ Διαγραφή πληρωμών grouped ανά ημέρα (paid_at)
     */
    public function destroyPaymentsByDay(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'day_key' => 'required|string',
        ]);

        $dayKey = $data['day_key'];

        // χωρίς ημερομηνία
        if ($dayKey === 'no-date') {
            $deleted = Payment::where('customer_id', $customer->id)
                ->whereNull('paid_at')
                ->delete();

            return back()->with('success', "Διαγράφηκαν {$deleted} πληρωμές (χωρίς ημερομηνία).");
        }

        // Y-m-d
        try {
            $start = Carbon::createFromFormat('Y-m-d', $dayKey)->startOfDay();
            $end   = Carbon::createFromFormat('Y-m-d', $dayKey)->endOfDay();
        } catch (\Exception $e) {
            return back()->with('error', 'Μη έγκυρη ημερομηνία.');
        }

        $deleted = Payment::where('customer_id', $customer->id)
            ->whereBetween('paid_at', [$start, $end])
            ->delete();

        return back()->with('success', "Διαγράφηκαν {$deleted} πληρωμές για {$dayKey}.");
    }

    /**
     * ✅ Delete appointments (soft delete) selected
     */
    public function deleteAppointments(Request $request, Customer $customer)
    {
        $appointmentIds = $request->input('appointments', []);

        if (empty($appointmentIds)) {
            return back()->with('error', 'Δεν επιλέχθηκαν ραντεβού για διαγραφή.');
        }

        $appointments = Appointment::whereIn('id', $appointmentIds)
            ->where('customer_id', $customer->id)
            ->get();

        if ($appointments->isEmpty()) {
            return back()->with('error', 'Δεν βρέθηκαν έγκυρα ραντεβού για διαγραφή.');
        }

        foreach ($appointments as $appointment) {
            $appointment->delete(); // soft delete
        }

        return back()->with('success', 'Τα επιλεγμένα ραντεβού διαγράφηκαν επιτυχώς.');
    }

    public function toggleActive(Request $request, Customer $customer)
    {
        // Αν θέλεις να επιτρέπεται μόνο σε owner:
        // abort_unless(Auth::user()?->role === 'owner', 403);

        $data = $request->validate([
            'is_active' => 'required|in:0,1',
        ]);

        $customer->is_active = (int)$data['is_active'];
        $customer->save();

        return back()->with(
            'success',
            $customer->is_active ? 'Ο πελάτης ενεργοποιήθηκε.' : 'Ο πελάτης απενεργοποιήθηκε.'
        );
    }

    public function taxFixOldestCashNoReceipt(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'fix_amount' => ['required','integer','min:5', function ($attr, $value, $fail) {
                if ($value % 5 !== 0) $fail('Το ποσό πρέπει να είναι πολλαπλάσιο του 5 (5,10,15...).');
            }],
        ]);

        $x = (int) ($data['fix_amount'] / 5);
        if ($x <= 0) {
            return back()->with('error', 'Μη έγκυρη τιμή.');
        }

        // 🔎 Πρώτος έλεγχος: υπάρχει ΤΟΥΛΑΧΙΣΤΟΝ 1 payment που να πληροί τα criteria;
        $baseQuery = \App\Models\Payment::where('customer_id', $customer->id)
            ->where('method', 'cash')
            ->where('tax', 'N');

        if (! $baseQuery->exists()) {
            return back()->with('error', 'Δεν βρέθηκε κανένα ραντεβού με πληρωμή μετρητών χωρίς απόδειξη.');
        }

        $changedPayments = 0;
        $changedAppointments = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $x, &$changedPayments, &$changedAppointments) {

            // X πιο παλιές πληρωμές
            $payments = \App\Models\Payment::where('customer_id', $customer->id)
                ->where('method', 'cash')
                ->where('tax', 'N')
                ->orderByRaw('paid_at IS NULL DESC')
                ->orderBy('paid_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($x)
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                return;
            }

            $paymentIds = $payments->pluck('id')->all();
            $appointmentIds = $payments
                ->pluck('appointment_id')
                ->filter()                 // πετάμε NULL
                ->unique()
                ->values()
                ->all();

            // 1) Update payments
            $changedPayments = \App\Models\Payment::whereIn('id', $paymentIds)->update([
                'amount' => 35.00,
                'tax' => 'Y',
                'is_tax_fixed' => 1,
                'tax_fixed_at' => now(),
                'updated_at' => now(),
            ]);

            // 2) Update appointments ΜΟΝΟ αν υπάρχουν
            if (!empty($appointmentIds)) {
                $changedAppointments = \App\Models\Appointment::whereIn('id', $appointmentIds)->update([
                    'total_price' => 35.00,
                    'updated_at' => now(),
                ]);
            }
        });

        // 🧾 Τελικά μηνύματα
        if ($changedPayments === 0) {
            return back()->with('error', 'Δεν βρέθηκαν πληρωμές για διόρθωση.');
        }

        if ($changedAppointments === 0) {
            return back()->with('warning', 'Οι πληρωμές διορθώθηκαν, αλλά δεν βρέθηκε κανένα συνδεδεμένο ραντεβού για ενημέρωση ποσού.');
        }

        return back()->with(
            'success',
            "Ολοκληρώθηκε: διορθώθηκαν {$changedPayments} πληρωμές και ενημερώθηκαν {$changedAppointments} ραντεβού (ποσό 35€)."
        );
    }




}
