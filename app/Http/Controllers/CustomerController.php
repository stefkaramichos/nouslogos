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
use App\Models\CustomerPrepayment;
use App\Models\CustomerReceipt;

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

        // ✅ NEW: active filter
        $active = $request->input('active', '1'); // all | 1 | 0
        if (!in_array((string)$active, ['all', '1', '0'], true)) {
            $active = 'all';
        }

        $customers = Customer::query()
            ->with(['company', 'professionals'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when($active !== 'all', fn($q) => $q->where('is_active', (int)$active))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('company', fn($qc) => $qc->where('name', 'like', "%{$search}%"));
                });
            })

            // ✅ ACTIVE ΠΑΝΩ, DISABLED ΚΑΤΩ (αν active=all)
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
            'active'    => $active, // ✅ pass to blade
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

        return redirect()->route('customers.index')->with('success', 'Το περιστατικό δημιουργήθηκε επιτυχώς.');
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

        $redirect = $request->input('redirect') ?? $request->input('redirect_to');

        if ($redirect) {
            // optional safety
            if (!str_starts_with($redirect, url('/'))) {
                $redirect = null;
            }
        }

        if ($redirect) {
            return redirect()->to($redirect)->with('success', 'Το περιστατικό ενημερώθηκε επιτυχώς.');
        }


        return redirect()->route('customers.index')->with('success', 'Το περιστατικό ενημερώθηκε επιτυχώς.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Το περιστατικό διαγράφηκε επιτυχώς.');
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
            'receipts',
        ]);

        $receipts = $customer->receipts()
            ->orderByDesc('id')
            ->get();

        $taxFixLogs = DB::table('customer_tax_fix_logs')
            ->where('customer_id', $customer->id)
            ->orderByDesc('run_at')
            ->orderByDesc('id')
            ->get();


        /**
         * 🔹 Ιστορικό πληρωμών (ομαδοποίηση ανά paid_at)
         * (μένει όπως ήταν: αφορά ΟΛΕΣ τις πληρωμές του πελάτη)
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
         * 🔹 Date filter για ραντεβού λίστας
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
                $day = $base->toDateString();
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
            $m = Carbon::createFromFormat('Y-m', $month);
            $from = $m->copy()->startOfMonth()->toDateString();
            $to   = $m->copy()->endOfMonth()->toDateString();
        }

        // ✅ Existing filters
        $paymentStatus = $request->input('payment_status'); // unpaid/partial/full/all
        $paymentMethod = $request->input('payment_method'); // cash/card/all

        // ✅ NEW: Professional filter
        $professionalId = $request->input('professional_id'); // id or "all"/null

        if ($professionalId === '' || $professionalId === 'all' || $professionalId === null) {
            $professionalId = null;
        } else {
            $professionalId = (int)$professionalId;
            if ($professionalId <= 0) $professionalId = null;
        }

        /**
         * 🔹 Collection appointments (όχι DB query)
         */
        $appointmentsCollection = $customer->appointments
            ->sortByDesc('start_time')
            ->values();

        // ✅ List professionals που υπάρχουν σε ραντεβού (για dropdown)
        $appointmentProfessionals = $appointmentsCollection
            ->map(fn($a) => $a->professional)
            ->filter()
            ->unique('id')
            ->sortBy(fn($p) => mb_strtolower(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')))
            ->values();

        $filteredAppointments = $appointmentsCollection;

        // ✅ Date range filter
        if ($from && $to) {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($from, $to) {
                if (!$a->start_time) return false;
                $d = $a->start_time->toDateString();
                return $d >= $from && $d <= $to;
            });
        }

        // ✅ NEW: Professional filter
        if ($professionalId) {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($professionalId) {
                return (int)($a->professional_id ?? 0) === (int)$professionalId;
            });
        }

        // ✅ Payment status filter based on payments sum
        if ($paymentStatus && $paymentStatus !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($paymentStatus) {
                $total = (float)($a->total_price ?? 0);
                $paid  = (float)$a->payments->sum('amount');

                return match ($paymentStatus) {
                    'unpaid'   => $paid <= 0,
                    'partial'  => $paid > 0 && $paid < $total,
                    'full'     => $total > 0 && $paid >= $total,
                    default    => true,
                };
            });
        }

        // ✅ Method filter (cash/card): true αν υπάρχει έστω μία πληρωμή με method
        if ($paymentMethod && $paymentMethod !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($paymentMethod) {
                return $a->payments->contains(fn($p) => $p->method === $paymentMethod);
            });
        }

        $filteredAppointments = $filteredAppointments->values();

        /**
         * ✅ Τα badges & table δείχνουν totals από ΦΙΛΤΡΑΡΙΣΜΕΝΑ ραντεβού
         */
        $appointments = $filteredAppointments;

        $globalAppointmentsCount = $appointments->count();

        $globalTotalAmount = $appointments->sum(fn($a) => (float)($a->total_price ?? 0));
        $globalPaidTotal   = $appointments->sum(fn($a) => (float)$a->payments->sum('amount'));
        $globalOutstandingTotal = max($globalTotalAmount - $globalPaidTotal, 0);

        // 🔹 Totals filtered (cash/card) - πάνω στο filtered
        $appointmentsCount = $appointments->count();
        $cashTotal = $appointments->sum(fn($a) => (float)$a->payments->where('method', 'cash')->sum('amount'));
        $cardTotal = $appointments->sum(fn($a) => (float)$a->payments->where('method', 'card')->sum('amount'));

        /**
         * ✅ OUTSTANDING PREVIEW (ΟΛΑ τα χρωστούμενα, χωρίς ημερομηνίες)
         */
        [$outstandingCount, $outstandingAmount] = $this->calcOutstandingForCustomer($customer->id);

        /**
         * 🔹 Prev/Next URLs + Filters array (κρατάμε ΚΑΙ professional_id)
         */
        $filters = [
            'range' => $range,
            'day' => $day,
            'month' => $month,
            'payment_status' => $paymentStatus ?? 'all',
            'payment_method' => $paymentMethod ?? 'all',
            'professional_id' => $professionalId ?? 'all',
        ];

        $prevUrl = null;
        $nextUrl = null;

        if ($range !== 'all') {
            $baseQuery = $request->query();
            unset($baseQuery['nav']);

            // κράτα και το professional_id μέσα στα query strings
            if ($professionalId) {
                $baseQuery['professional_id'] = $professionalId;
            } else {
                // αν είναι all μην το βάζεις υποχρεωτικά
                unset($baseQuery['professional_id']);
            }

            if ($range === 'day') {
                $baseQuery['range'] = 'day';
                $baseQuery['day'] = $day ?: now()->toDateString();
                unset($baseQuery['month']);
            } elseif ($range === 'month') {
                $baseQuery['range'] = 'month';
                $baseQuery['month'] = $month ?: now()->format('Y-m');
                unset($baseQuery['day']);
            }

            $prevUrl = $request->url() . '?' . http_build_query(array_merge($baseQuery, ['nav' => 'prev']));
            $nextUrl = $request->url() . '?' . http_build_query(array_merge($baseQuery, ['nav' => 'next']));
        }

        $prepayment = \App\Models\CustomerPrepayment::where('customer_id', $customer->id)->first();

        $selectedLabel = 'Όλα';
        if ($range === 'day' && $day) {
            $selectedLabel = Carbon::parse($day)->locale('el')->translatedFormat('D d/m/Y');
        } elseif ($range === 'month' && $month) {
            $selectedLabel = Carbon::createFromFormat('Y-m', $month)->locale('el')->translatedFormat('F Y');
        }

        return view('customers.show', compact(
            'customer',
            'appointments',
            'appointmentsCount',

            // ✅ FILTERED totals
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
            'outstandingAmount',

            // ✅ NEW for filter dropdown
            'appointmentProfessionals',
            'prepayment',
            'taxFixLogs',
            'receipts',
        ));
    }

    
    public function updatePaymentsDayTotal(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'day_key' => 'required|string', // "Y-m-d" ή "no-date"
            'total'   => 'required|numeric|min:0',
        ]);

        $dayKey   = $data['day_key'];
        $newTotal = (float)$data['total'];

        // helper: βρες payments query για την ημέρα
        $paymentsQuery = Payment::where('customer_id', $customer->id);

        $paidAtForNew = null; // αν day_key = no-date, paid_at NULL
        if ($dayKey === 'no-date') {
            $paymentsQuery->whereNull('paid_at');
            $paidAtForNew = null;
        } else {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $dayKey)->startOfDay();
                $end   = Carbon::createFromFormat('Y-m-d', $dayKey)->endOfDay();
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Μη έγκυρη ημερομηνία.'], 422);
            }

            $paymentsQuery->whereBetween('paid_at', [$start, $end]);
            // για νέες πληρωμές κράτα την ίδια μέρα (βάζω "τώρα" αλλά στη συγκεκριμένη ημερομηνία)
            $paidAtForNew = Carbon::createFromFormat('Y-m-d', $dayKey)->setTimeFromTimeString(now()->format('H:i:s'));
        }

        DB::transaction(function () use ($customer, $paymentsQuery, $newTotal, $paidAtForNew, &$responsePayload) {

            // payments της ημέρας (τελευταία πρώτα) για “μείωση”
            $dayPaymentsDesc = (clone $paymentsQuery)
                ->orderByRaw('paid_at IS NULL DESC') // null τελευταίο/πρώτο δεν έχει σημασία πολύ
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            $currentTotal = (float)$dayPaymentsDesc->sum('amount');
            $delta = $newTotal - $currentTotal;

            // Αν είναι ίδιο, τέλος
            if (abs($delta) < 0.0001) {
                $responsePayload = [
                    'success' => true,
                    'formatted_total' => number_format($currentTotal, 2, ',', '.') . ' €',
                    'old_total' => $currentTotal,
                    'new_total' => $currentTotal,
                ];
                return;
            }

            // Θα χρειαστούμε defaults για νέες πληρωμές (method/tax/bank) από την πιο πρόσφατη της ημέρας
            $lastPayment = $dayPaymentsDesc->first();
            $defaultMethod = $lastPayment?->method ?? 'cash';
            $defaultTax    = $lastPayment?->tax ?? 'Y';
            $defaultBank   = $lastPayment?->bank ?? null;

            // =========================
            //  A) ΜΕΙΩΣΗ (delta < 0)
            // =========================
            if ($delta < 0) {
                $toRemove = abs($delta);

                // ξεκινάμε από τις πιο πρόσφατες πληρωμές
                foreach ($dayPaymentsDesc as $p) {
                    if ($toRemove <= 0) break;

                    $amt = (float)$p->amount;
                    if ($amt <= 0) {
                        // καθάρισε τυχόν σκουπίδια
                        $p->delete();
                        continue;
                    }

                    if ($amt <= $toRemove + 0.0001) {
                        // αυτή η πληρωμή μηδενίζεται -> delete
                        $toRemove -= $amt;
                        $p->delete();
                    } else {
                        // μειώνουμε ποσό και κρατάμε την πληρωμή
                        $p->amount = $amt - $toRemove;
                        $p->save();
                        $toRemove = 0;
                    }
                }

                // Αν ο χρήστης ζήτησε π.χ. 0 και “έφαγες” όλες, ΟΚ.
                // Αν δεν υπήρχαν αρκετά χρήματα να αφαιρεθούν (πρακτικά δεν γίνεται γιατί currentTotal>=newTotal), αγνόησε.

                // Μετά το delete/updates, ανανέωσε totals
                $newComputed = (float)(clone $paymentsQuery)->sum('amount');

                // ✅ Ενημέρωσε is_full flags σωστά στα affected appointments
                $affectedAppointmentIds = Payment::where('customer_id', $customer->id)
                    ->whereNotNull('appointment_id')
                    ->pluck('appointment_id')
                    ->unique()
                    ->values()
                    ->all();

                $this->recalcIsFullForAppointments($affectedAppointmentIds);

                $responsePayload = [
                    'success' => true,
                    'formatted_total' => number_format($newComputed, 2, ',', '.') . ' €',
                    'old_total' => $currentTotal,
                    'new_total' => $newComputed,
                ];
                return;
            }

            // =========================
            //  B) ΑΥΞΗΣΗ (delta > 0)
            // =========================
            $toAdd = $delta;

            // Βρες ραντεβού με υπόλοιπο (oldest first)
            $appointments = Appointment::where('customer_id', $customer->id)
                ->with('payments')
                ->orderBy('start_time', 'asc')
                ->lockForUpdate()
                ->get();

            // Φτιάξε λίστα (appointment_id => due)
            $dueList = [];
            $dueTotal = 0.0;

            foreach ($appointments as $a) {
                $total = (float)($a->total_price ?? 0);
                if ($total <= 0) continue;

                $paid = (float)$a->payments->sum('amount');
                $due  = max(0, $total - $paid);

                if ($due > 0.0001) {
                    $dueList[] = ['id' => $a->id, 'due' => $due];
                    $dueTotal += $due;
                }
            }

            // Αν δεν υπάρχουν χρωστούμενα, μπορείς:
            // - είτε να το απορρίψεις
            // - είτε να το αφήσεις σαν "προκαταβολή" (χωρίς appointment_id)
            // Εσύ λες "δημιούργησε αντίστοιχες πληρωμές" -> άρα μόνο σε ραντεβού.
            if ($dueTotal <= 0.0001) {
                throw new \Exception('Δεν υπάρχουν χρωστούμενα ραντεβού για να μοιραστεί το ποσό.');
            }

            // Μην επιτρέψεις να πληρώσει παραπάνω από τα χρωστούμενα (αν θες να επιτρέπεται overpay πες μου)
            if ($toAdd > $dueTotal + 0.0001) {
                throw new \Exception('Το ποσό είναι μεγαλύτερο από το συνολικό υπόλοιπο των χρωστούμενων ραντεβού.');
            }

            // Allocate σε πολλά appointments (oldest first)
            $createdAppointmentIds = [];

            foreach ($dueList as $row) {
                if ($toAdd <= 0) break;

                $apptId = (int)$row['id'];
                $due    = (float)$row['due'];

                $payNow = min($due, $toAdd);

                Payment::create([
                    'appointment_id' => $apptId,
                    'customer_id'    => $customer->id,
                    'amount'         => $payNow,
                    'is_full'        => 0, // θα το ξανα-υπολογίσουμε μετά
                    'paid_at'        => $paidAtForNew ?? now(), // αν no-date -> null (επιτρέπεται)
                    'method'         => $defaultMethod,
                    'tax'            => $defaultTax,
                    'bank'           => $defaultBank,
                    'notes'          => '[AUTO_DAY_TOTAL] Προσαρμογή ημερήσιου συνόλου.',
                    'created_by'     => Auth::id(),
                ]);

                $createdAppointmentIds[] = $apptId;
                $toAdd -= $payNow;
            }

            // cleanup: σβήσε τυχόν μηδενικές πληρωμές της ημέρας (αν υπήρχαν ήδη)
            (clone $paymentsQuery)->where('amount', '<=', 0)->delete();

            // ✅ Recalc is_full στα appointments που επηρεάστηκαν
            $this->recalcIsFullForAppointments(array_values(array_unique($createdAppointmentIds)));

            $newComputed = (float)(clone $paymentsQuery)->sum('amount');

            $responsePayload = [
                'success' => true,
                'formatted_total' => number_format($newComputed, 2, ',', '.') . ' €',
                'old_total' => $currentTotal,
                'new_total' => $newComputed,
            ];
        });

        return response()->json($responsePayload ?? ['success' => false, 'message' => 'Άγνωστο σφάλμα.'], 200);
    }

    /**
     * Επανυπολογίζει το is_full για τα payments ανά appointment:
     * - αν paid >= total => το τελευταίο payment γίνεται is_full=1, τα υπόλοιπα 0
     * - αλλιώς όλα 0
     */
    private function recalcIsFullForAppointments(array $appointmentIds): void
    {
        if (empty($appointmentIds)) return;

        $appointments = Appointment::whereIn('id', $appointmentIds)
            ->with('payments')
            ->get();

        foreach ($appointments as $a) {
            $total = (float)($a->total_price ?? 0);
            $paid  = (float)$a->payments->sum('amount');

            // κάνε reset
            Payment::where('appointment_id', $a->id)->update(['is_full' => 0]);

            if ($total > 0 && $paid >= $total) {
                $last = Payment::where('appointment_id', $a->id)
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->first();

                if ($last) {
                    $last->is_full = 1;
                    $last->save();
                }
            }
        }
    }


    public function inlineUpdate(Request $request)
    {
        $data = $request->validate([
            'model' => 'required|in:customer,appointment',
            'id'    => 'required|integer',
            'field' => 'required|string',
            'value' => 'nullable',
        ]);

        // allow-list fields (ΠΟΛΥ ΣΗΜΑΝΤΙΚΟ)
        $allowed = [
            'customer' => ['first_name','last_name','phone','email','tax_office','vat_number','informations'],
            'appointment' => ['total_price','notes','status','start_time'],
        ];

        if (!in_array($data['field'], $allowed[$data['model']], true)) {
            return response()->json(['success' => false, 'message' => 'Field not allowed'], 403);
        }

        if ($data['model'] === 'customer') {
            $item = Customer::findOrFail($data['id']);

            // basic rules per field (προαιρετικά αλλά καλό)
            $rulesPerField = [
                'first_name'   => 'nullable|string|max:100',
                'last_name'    => 'nullable|string|max:100',
                'phone'        => 'nullable|string|max:100',
                'email'        => 'nullable|email|max:150',
                'tax_office'   => 'nullable|string|max:100',
                'vat_number'   => 'nullable|string|max:20',
                'informations' => 'nullable|string',
            ];
            $request->validate(['value' => $rulesPerField[$data['field']] ?? 'nullable']);

            $item->{$data['field']} = $data['value'];
            $item->save();

            return response()->json([
                'success' => true,
                'value'   => (string)($item->{$data['field']} ?? ''),
            ]);
        }

        // appointment
        $item = Appointment::findOrFail($data['id']);

        $rulesPerField = [
            'total_price' => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string|max:5000',
            'status'      => 'nullable|in:completed,cancelled,no_show,pending',
            'start_time'  => 'nullable|date',
        ];
        $request->validate(['value' => $rulesPerField[$data['field']] ?? 'nullable']);

        $item->{$data['field']} = $data['value'];
        $item->save();

        // formatting για price
        $val = $item->{$data['field']};
        $formatted = match ($data['field']) {
            'total_price' => number_format((float)$val, 2, ',', '.') . ' €',
            'start_time'  => $val ? Carbon::parse($val)->format('d/m/Y H:i') : '-',
            default       => (string)($val ?? ''),
        };

        return response()->json([
            'success'   => true,
            'value'     => (string)($val ?? ''),
            'formatted' => $formatted,
        ]);
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
            // όλα γίνονται προπληρωμή
            DB::transaction(function () use ($customer, $cashY, $cashN, $card, $data, $paidAt) {
                $prepay = CustomerPrepayment::where('customer_id', $customer->id)
                    ->lockForUpdate()
                    ->first();

                if (!$prepay) {
                    $prepay = CustomerPrepayment::create([
                        'customer_id'     => $customer->id,
                        'cash_y_balance'  => 0,
                        'cash_n_balance'  => 0,
                        'card_balance'    => 0,
                        'card_bank'       => $data['card_bank'] ?? null,
                        'last_paid_at'    => $paidAt,
                        'created_by'      => Auth::id(),
                        'notes'           => 'Χειροκίνητη προπληρωμή (χωρίς χρωστούμενα).',
                    ]);
                }

                $prepay->cash_y_balance += (float)$cashY;
                $prepay->cash_n_balance += (float)$cashN;
                $prepay->card_balance   += (float)$card;

                if (!empty($data['card_bank'])) {
                    $prepay->card_bank = $data['card_bank'];
                }

                $prepay->last_paid_at = $paidAt;
                $prepay->save();
            });

            $incoming = $cashY + $cashN + $card;
            return back()->with('success', 'Καταχωρήθηκε προπληρωμή: ' . number_format($incoming, 2, ',', '.') . ' €');
        }


        $incoming = $cashY + $cashN + $card;

    
        DB::transaction(function () use ($appointments, $customer, $cashY, $cashN, $card, $data, $paidAt) {

            // helper allocate to due appointments (oldest first) returning leftover from bucket
            $allocateToDue = function (float $amount, string $method, string $tax, ?string $bank = null)
                use (&$appointments, $customer, $data, $paidAt) : float {

                $remaining = $amount;

                foreach ($appointments as $a) {
                    if ($remaining <= 0) break;

                    $total = (float)($a->total_price ?? 0);
                    if ($total <= 0) continue;

                    $paid  = (float)$a->payments->sum('amount');
                    $due   = max(0, $total - $paid);

                    if ($due <= 0) continue;

                    $payNow = min($due, $remaining);

                    $payment = Payment::create([
                        'appointment_id' => $a->id,
                        'customer_id'    => $customer->id,
                        'amount'         => $payNow,
                        'is_full'        => 0,
                        'paid_at'        => $paidAt,
                        'method'         => $method,
                        'tax'            => $tax,
                        'bank'           => $bank,
                        'notes'          => $data['notes'] ?? 'Πληρωμή χρωστούμενων (split).',
                        'created_by'     => Auth::id(),
                    ]);

                    $a->payments->push($payment);
                    $remaining -= $payNow;
                }

                return $remaining; // ✅ leftover = προπληρωμή
            };

            // 1) allocate to due (όσο υπάρχει due)
            $leftCashY = $cashY > 0 ? $allocateToDue($cashY, 'cash', 'Y', null) : 0;
            $leftCashN = $cashN > 0 ? $allocateToDue($cashN, 'cash', 'N', null) : 0;

            $leftCard  = 0;
            if ($card > 0) {
                $bank = $data['card_bank'] ?? null;
                $leftCard = $allocateToDue($card, 'card', 'Y', $bank);
            }

            // 2) update is_full για όσα καλύφθηκαν
            foreach ($appointments as $a) {
                $total = (float)($a->total_price ?? 0);
                if ($total <= 0) continue;

                $paid = (float)$a->payments->sum('amount');

                Payment::where('appointment_id', $a->id)->update(['is_full' => 0]);

                if ($paid >= $total) {
                    $last = Payment::where('appointment_id', $a->id)
                        ->orderByDesc('paid_at')
                        ->orderByDesc('id')
                        ->first();

                    if ($last) {
                        $last->is_full = 1;
                        $last->save();
                    }
                }
            }

            // 3) Αν περίσσεψε κάτι => προπληρωμή
            $extraTotal = (float)$leftCashY + (float)$leftCashN + (float)$leftCard;

            if ($extraTotal > 0.0001) {
                $prepay = CustomerPrepayment::where('customer_id', $customer->id)
                    ->lockForUpdate()
                    ->first();

                if (!$prepay) {
                    $prepay = CustomerPrepayment::create([
                        'customer_id'     => $customer->id,
                        'cash_y_balance'  => 0,
                        'cash_n_balance'  => 0,
                        'card_balance'    => 0,
                        'card_bank'       => $data['card_bank'] ?? null,
                        'last_paid_at'    => $paidAt,
                        'created_by'      => Auth::id(),
                        'notes'           => 'Αυτόματη προπληρωμή από φόρμα χρωστούμενων.',
                    ]);
                }

                $prepay->cash_y_balance += (float)$leftCashY;
                $prepay->cash_n_balance += (float)$leftCashN;
                $prepay->card_balance   += (float)$leftCard;

                if (!empty($data['card_bank'])) {
                    $prepay->card_bank = $data['card_bank'];
                }

                $prepay->last_paid_at = $paidAt;
                $prepay->updated_at = now();
                $prepay->save();
            }
        });

        if ($incoming > $dueTotal + 0.0001) {
            $extra = $incoming - $dueTotal;
            return back()->with('success', 'Η πληρωμή καταχωρήθηκε. Προπληρωμή: ' . number_format($extra, 2, ',', '.') . ' €');
        }
        return back()->with('success', 'Η πληρωμή καταχωρήθηκε επιτυχώς.');
    }

    public function toggleCompleted(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'completed' => 'required|in:0,1',
        ]);

        $customer->completed = (int)$data['completed'];
        $customer->save();

        return back()->with('success', 'Ενημερώθηκε η κατάσταση Completed.');
    }


    /**
     * ✅ Διαγραφή πληρωμών grouped ανά ημέρα (paid_at)
     */
    public function destroyPaymentsByDay(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'day_key' => 'required|string', // "Y-m-d" ή "no-date"
        ]);

        $dayKey = $data['day_key'];

        // 1) Query των payments που θα σβηστούν
        $paymentsQuery = Payment::where('customer_id', $customer->id);

        if ($dayKey === 'no-date') {
            $paymentsQuery->whereNull('paid_at');
        } else {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $dayKey)->startOfDay();
                $end   = Carbon::createFromFormat('Y-m-d', $dayKey)->endOfDay();
            } catch (\Exception $e) {
                return back()->with('error', 'Μη έγκυρη ημερομηνία.');
            }

            $paymentsQuery->whereBetween('paid_at', [$start, $end]);
        }

        $deleted = 0;
        $logsDeleted = 0;

        DB::transaction(function () use ($customer, $paymentsQuery, &$deleted, &$logsDeleted) {

            // 2) IDs payments που θα διαγραφούν
            $idsToDelete = (clone $paymentsQuery)->pluck('id')->all();

            // 3) Διαγραφή logs που έχουν μέσα payment_ids κάποιο από αυτά
            if (!empty($idsToDelete)) {
                $logQuery = DB::table('customer_tax_fix_logs')
                    ->where('customer_id', $customer->id)
                    ->where(function ($q) use ($idsToDelete) {
                        foreach ($idsToDelete as $pid) {
                            // ✅ MariaDB-safe: 2nd arg πρέπει να είναι valid JSON text (π.χ. "287" ή 287)
                            $q->orWhereRaw(
                                "JSON_CONTAINS(payment_ids, ?, '$')",
                                [json_encode((int)$pid)]
                            );
                        }
                    });

                $logsDeleted = $logQuery->delete();
            }

            // 4) Διαγραφή payments
            $deleted = (clone $paymentsQuery)->delete();
        });

        if ($dayKey === 'no-date') {
            return back()->with(
                'success',
                "Διαγράφηκαν {$deleted} πληρωμές (χωρίς ημερομηνία) και {$logsDeleted} logs."
            );
        }

        return back()->with(
            'success',
            "Διαγράφηκαν {$deleted} πληρωμές για {$dayKey} και {$logsDeleted} logs."
        );
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
            $customer->is_active ? 'Το περιστατικό ενεργοποιήθηκε.' : 'Το περιστατικό απενεργοποιήθηκε.'
        );
    }

    public function taxFixOldestCashNoReceipt(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'fix_amount' => ['required','integer','min:5', function ($attr, $value, $fail) {
                if ($value % 5 !== 0) $fail('Το ποσό πρέπει να είναι πολλαπλάσιο του 5 (5,10,15...).');
            }],
            'run_at'  => 'required|date',         // ✅ date only
            'method'  => 'required|in:cash,card', // ✅ user choice
            'comment' => 'nullable|string|max:1000',
        ]);

        $fixAmount = (int)$data['fix_amount'];
        $x = (int)($fixAmount / 5);
        if ($x <= 0) return back()->with('error', 'Μη έγκυρη τιμή.');

        $baseQuery = Payment::where('customer_id', $customer->id)
            ->where('method', 'cash')
            ->where('tax', 'N');

        if (!$baseQuery->exists()) {
            return back()->with('error', 'Δεν βρέθηκε κανένα payment cash χωρίς απόδειξη (tax=N).');
        }

        // ✅ paid_at will be date at start of day (00:00:00)
        $runAt  = Carbon::parse($data['run_at'])->startOfDay();
        $method = $data['method'];

        $changedPayments = 0;
        $createdAddons   = 0;
        $changedAppointments = 0;

        DB::transaction(function () use (
            $customer, $x, $runAt, $method, $data,
            &$changedPayments, &$createdAddons, &$changedAppointments
        ) {

            $payments = Payment::where('customer_id', $customer->id)
                ->where('method', 'cash')
                ->where('tax', 'N')
                ->orderByRaw('paid_at IS NULL DESC')
                ->orderBy('paid_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($x)
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) return;

            $paymentIds = $payments->pluck('id')->all();

            $appointmentIds = $payments->pluck('appointment_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            // ✅ 1) Mark old payments fixed (DON'T change amount)
            $changedPayments = Payment::whereIn('id', $paymentIds)->update([
                'tax'          => 'Y',
                'is_tax_fixed' => 1,
                'tax_fixed_at' => $runAt,
                'updated_at'   => now(),
            ]);

            // ✅ 2) Create NEW payment 5€ for each old payment (method chosen by user)
            foreach ($payments as $p) {
                if (!$p->appointment_id) continue;

                Payment::create([
                    'appointment_id' => $p->appointment_id,
                    'customer_id'    => $customer->id,
                    'amount'         => 5.00,
                    'is_full'        => 0,
                    'paid_at'        => $runAt,
                    'method'         => $method,
                    'tax'            => 'Y',
                    'bank'           => null, // ✅ no bank
                    'notes'          => '[TAX_FIX_ADDON] +5€ για διόρθωση παλαιού cash χωρίς απόδειξη.'
                                    . (!empty($data['comment']) ? ' ' . $data['comment'] : ''),
                    'created_by'     => Auth::id(),
                ]);

                $createdAddons++;
            }

            // ✅ 3) Increase total_price by +5 (not set)
            if (!empty($appointmentIds)) {
                $changedAppointments = Appointment::whereIn('id', $appointmentIds)->update([
                    'total_price' => DB::raw('COALESCE(total_price,0) + 5.00'),
                    'updated_at'  => now(),
                ]);
            }

            // ✅ 4) recalc is_full
            $this->recalcIsFullForAppointments($appointmentIds);

            // ✅ 5) log
            DB::table('customer_tax_fix_logs')->insert([
                'customer_id' => $customer->id,
                'created_by'  => Auth::id(),

                'fix_amount'  => $x * 5,
                'x_payments'  => $x,

                'old_amount'  => 0.00,
                'new_amount'  => 5.00,

                'changed_payments'     => (int)$changedPayments,
                'changed_appointments' => (int)$changedAppointments,

                'run_at'  => $runAt,                 // ✅ date only
                'comment' => $data['comment'] ?? null,

                'payment_ids'     => json_encode($paymentIds, JSON_UNESCAPED_UNICODE),
                'appointment_ids' => json_encode($appointmentIds, JSON_UNESCAPED_UNICODE),

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if ($changedPayments === 0) {
            return back()->with('error', 'Δεν βρέθηκαν πληρωμές για διόρθωση.');
        }

        return back()->with(
            'success',
            "Ολοκληρώθηκε: διορθώθηκαν {$changedPayments} παλιές πληρωμές και δημιουργήθηκαν {$createdAddons} νέα payments των 5€."
        );
    }






}
