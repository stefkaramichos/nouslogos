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

    public function index(Request $request)
    {
        $search = $request->input('search');

        if ($request->boolean('clear_company')) {
            $request->session()->forget('customers_company_id');
        }

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

    public function uploadFile(Request $request, Customer $customer)
    {
        $request->validate([
            'file'  => 'required|file|max:10240',
            'notes' => 'nullable|string|max:1000',
        ], [
            'file.required' => 'Πρέπει να επιλέξετε αρχείο.',
            'file.file'     => 'Μη έγκυρο αρχείο.',
            'file.max'      => 'Το αρχείο δεν μπορεί να ξεπερνά τα 10MB.',
        ]);

        $uploaded = $request->file('file');

        $originalName = $uploaded->getClientOriginalName();
        $mime = $uploaded->getClientMimeType();
        $size = $uploaded->getSize();

        $disk = 'local';

        $storedName = Str::random(12) . '_' . time() . '_' . preg_replace('/\s+/', '_', $originalName);
        $dir  = "customer-files/{$customer->id}";
        $path = $uploaded->storeAs($dir, $storedName, $disk);

        CustomerFile::create([
            'customer_id'    => $customer->id,
            'uploaded_by'    => Auth::user()?->id,
            'original_name'  => $originalName,
            'stored_name'    => $storedName,
            'path'           => $path,
            'disk'           => $disk,
            'mime_type'      => $mime,
            'size'           => $size,
            'notes'          => $request->input('notes'),
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

    public function create()
    {
        $companies = Company::all();

        $professionals = Professional::where('is_active', 1)
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        return view('customers.create', compact('companies', 'professionals'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'phone'      => 'nullable|string|max:100',
            'email'      => 'nullable|email|max:150',
            'company_id' => 'nullable|exists:companies,id',
            'tax_office' => 'nullable|string|max:100',
            'vat_number' => 'nullable|string|max:20',
            'informations' => 'nullable|string',

            'professionals' => 'nullable|array',
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
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $redirect = $request->input('redirect');
        $customer->load('professionals');

        return view('customers.edit', compact('customer', 'companies', 'professionals', 'redirect'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'phone'        => 'nullable|string|max:100',
            'email'        => 'nullable|email|max:150',
            'company_id'   => 'nullable|exists:companies,id',
            'tax_office'   => 'nullable|string|max:100',
            'vat_number'   => 'nullable|string|max:20',
            'informations' => 'nullable|string',

            'professionals' => 'nullable|array',
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

    public function show(Request $request, Customer $customer)
    {
        // ✅ CRITICAL: φορτώνουμε payments (όχι μόνο latest payment)
        $customer->load([
            'company',
            'professionals',
            'appointments.professional',
            'appointments.company',
            'appointments.payments',   // ✅ split totals
            'appointments.creator',
            'files.uploader'
        ]);

        // 🔹 Ιστορικό πληρωμών (όλες οι πληρωμές του πελάτη)
        $payments = Payment::where('customer_id', $customer->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $paymentsByDate = $payments->groupBy(function ($payment) {
            if (!$payment->paid_at) return 'Χωρίς ημερομηνία';
            return Carbon::parse($payment->paid_at)->toDateString();
        });

        // 🔹 Date filter
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

        $appointmentsCollection = $customer->appointments
            ->sortByDesc('start_time')
            ->values();

        $filteredAppointments = $appointmentsCollection;

        // ημερομηνία
        if ($from && $to) {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($from, $to) {
                if (!$a->start_time) return false;
                $d = $a->start_time->toDateString();
                return $d >= $from && $d <= $to;
            });
        }

        // ✅ πληρωμή status βασισμένο σε payments sum
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

        // ✅ method filter: αν έχει έστω μία πληρωμή με το method
        if ($paymentMethod && $paymentMethod !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($paymentMethod) {
                return $a->payments->contains(fn($p) => $p->method === $paymentMethod);
            });
        }

        $filteredAppointments = $filteredAppointments->values();

        // GLOBAL totals
        $allAppointments = $appointmentsCollection;

        $globalAppointmentsCount = $allAppointments->count();

        $globalTotalAmount = $allAppointments->sum(fn($a) => (float)($a->total_price ?? 0));
        $globalPaidTotal   = $allAppointments->sum(fn($a) => (float)$a->payments->sum('amount'));
        $globalOutstandingTotal = max($globalTotalAmount - $globalPaidTotal, 0);

        // Filtered totals
        $appointmentsCount = $filteredAppointments->count();

        $filteredTotalAmount = $filteredAppointments->sum(fn($a) => (float)($a->total_price ?? 0));
        $filteredPaidTotal   = $filteredAppointments->sum(fn($a) => (float)$a->payments->sum('amount'));
        $filteredOutstandingTotal = max($filteredTotalAmount - $filteredPaidTotal, 0);

        $cashTotal = $filteredAppointments->sum(fn($a) => (float)$a->payments->where('method', 'cash')->sum('amount'));
        $cardTotal = $filteredAppointments->sum(fn($a) => (float)$a->payments->where('method', 'card')->sum('amount'));

        $appointments = $filteredAppointments;

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
            'selectedLabel'
        ));
    }

    // ✅ preview: δείχνει "πόσο απομένει" στο διάστημα, με split payments
    public function paymentPreview(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to   = Carbon::parse($data['to'])->endOfDay();

        $appointments = Appointment::where('customer_id', $customer->id)
            ->whereBetween('start_time', [$from, $to])
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->with('payments')
            ->get();

        $dueTotal = 0.0;

        foreach ($appointments as $a) {
            $total = (float)($a->total_price ?? 0);
            $paid  = (float)$a->payments->sum('amount');
            $dueTotal += max(0, $total - $paid);
        }

        return response()->json([
            'count'     => $appointments->count(),
            'amount'    => round($dueTotal, 2),
            'formatted' => number_format($dueTotal, 2, ',', '.') . ' €',
        ]);
    }

    // ✅ split πληρωμή βάσει ημερομηνιών
    public function payAllSplit(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'from'        => 'required|date',
            'to'          => 'required|date|after_or_equal:from',

            'cash_amount' => 'nullable|numeric|min:0',
            'cash_tax'    => 'nullable|in:Y,N',

            'card_amount' => 'nullable|numeric|min:0',
            'card_bank'   => 'nullable|string|max:255',

            'notes'       => 'nullable|string|max:1000',
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to   = Carbon::parse($data['to'])->endOfDay();

        $cashAmount = (float)($data['cash_amount'] ?? 0);
        $cardAmount = (float)($data['card_amount'] ?? 0);

        if ($cashAmount <= 0 && $cardAmount <= 0) {
            return back()->with('error', 'Βάλτε ποσό σε Μετρητά ή/και Κάρτα.');
        }

        $appointments = Appointment::where('customer_id', $customer->id)
            ->whereBetween('start_time', [$from, $to])
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->orderBy('start_time')
            ->with('payments')
            ->get();

        if ($appointments->isEmpty()) {
            return back()->with('error', 'Δεν βρέθηκαν ραντεβού με ποσό στο επιλεγμένο διάστημα.');
        }

        $dueTotal = 0.0;
        foreach ($appointments as $a) {
            $total = (float)$a->total_price;
            $paid  = (float)$a->payments->sum('amount');
            $dueTotal += max(0, $total - $paid);
        }

        $incoming = $cashAmount + $cardAmount;

        if ($incoming > $dueTotal + 0.0001) {
            return back()->with('error', 'Το ποσό που δώσατε είναι μεγαλύτερο από το υπόλοιπο του διαστήματος.');
        }

        DB::transaction(function () use ($appointments, $customer, $cashAmount, $cardAmount, $data) {

            $allocate = function (float $amount, string $method) use (&$appointments, $customer, $data) {
                $remaining = $amount;

                foreach ($appointments as $a) {
                    if ($remaining <= 0) break;

                    $total = (float)$a->total_price;
                    $paid  = (float)$a->payments->sum('amount');
                    $due   = max(0, $total - $paid);

                    if ($due <= 0) continue;

                    $payNow = min($due, $remaining);

                    if ($method === 'card') {
                        $tax  = 'Y';
                        $bank = $data['card_bank'] ?? null;
                    } else {
                        $tax  = (($data['cash_tax'] ?? 'N') === 'Y') ? 'Y' : 'N';
                        $bank = null;
                    }

                    $payment = Payment::create([
                        'appointment_id' => $a->id,
                        'customer_id'    => $customer->id,
                        'amount'         => $payNow,
                        'is_full'        => false, // θα το μαρκάρουμε κάτω αν καλύφθηκε
                        'paid_at'        => now(),
                        'method'         => $method,
                        'tax'            => $tax,
                        'bank'           => $bank,
                        'notes'          => $data['notes'] ?? 'Split πληρωμή βάσει διαστήματος.',
                    ]);

                    // update in-memory collection για να δουλεύουν τα sums στο ίδιο request
                    $a->payments->push($payment);

                    $remaining -= $payNow;
                }

                return $remaining;
            };

            if ($cashAmount > 0) $allocate($cashAmount, 'cash');
            if ($cardAmount > 0) $allocate($cardAmount, 'card');

            // is_full: μαρκάρουμε την τελευταία πληρωμή του appointment αν πλέον καλύφθηκε
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

        return back()->with('success', 'Η split πληρωμή καταχωρήθηκε επιτυχώς.');
    }

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
            $appointment->delete();
        }

        return back()->with('success', 'Τα επιλεγμένα ραντεβού διαγράφηκαν επιτυχώς.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Ο πελάτης διαγράφηκε επιτυχώς.');
    }
}
