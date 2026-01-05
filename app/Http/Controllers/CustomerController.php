<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Appointment;
use App\Models\Professional;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Http\Request;
use App\Models\CustomerFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // ✅ If user clicked "Όλοι" (clear button)
        if ($request->boolean('clear_company')) {
            $request->session()->forget('customers_company_id');
        }

        // ✅ If user explicitly clicked a company button (company_id is present)
        // (Do NOT store when clear_company is used)
        if (!$request->boolean('clear_company') && $request->has('company_id')) {
            $request->session()->put('customers_company_id', $request->input('company_id'));
        }

        // ✅ Use URL company_id if present, otherwise the remembered one from session
        $companyId = $request->has('company_id')
            ? $request->input('company_id')
            : $request->session()->get('customers_company_id');

        // normalize empty to null
        if ($companyId === '' || $companyId === null) {
            $companyId = null;
        }

        $customers = Customer::query()
            ->with(['company', 'professionals'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($qc) => $qc->where('name', 'like', "%{$search}%"));
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
            'companyId' => $companyId, // ✅ pass to blade for "active" button + hidden input
        ]);
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
        $mime = $uploaded->getClientMimeType();
        $size = $uploaded->getSize();

        // αποθήκευση στο storage/app/customer-files/{customer_id}/
        $disk = 'local'; // ✅

        $storedName = Str::random(12) . '_' . time() . '_' . preg_replace('/\s+/', '_', $originalName);
        $dir  = "customer-files/{$customer->id}";
        $path = $uploaded->storeAs($dir, $storedName, $disk); // ✅

        CustomerFile::create([
            'customer_id'    => $customer->id,
            'uploaded_by'    => Auth::user()?->id,
            'original_name'  => $originalName,
            'stored_name'    => $storedName,
            'path'           => $path,
            'disk'           => $disk, // ✅ ΠΟΛΥ ΣΗΜΑΝΤΙΚΟ
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
        // ασφάλεια: να ανήκει στον πελάτη
        if ((int)$file->customer_id !== (int)$customer->id) {
            abort(404);
        }

        // σβήνουμε πρώτα το φυσικό αρχείο
        if ($file->path && Storage::exists($file->path)) {
            Storage::delete($file->path);
        }

        $file->delete();

        return back()->with('success', 'Το αρχείο διαγράφηκε επιτυχώς.');
    }


    public function create()
    {
        $companies = Company::all();

        // pick what you want here:
        // a) all active professionals
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

            // ✅ NEW
            'professionals' => 'nullable|array',
            'professionals.*' => 'exists:professionals,id',
        ]);

        $professionalIds = $data['professionals'] ?? [];
        unset($data['professionals']);

        $customer = Customer::create($data);

        // ✅ link in pivot
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

        // so blade can show selected professionals
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

            // ✅ NEW
            'professionals' => 'nullable|array',
            'professionals.*' => 'exists:professionals,id',
        ]);

        $professionalIds = $data['professionals'] ?? [];
        unset($data['professionals']);

        $customer->update($data);

        // ✅ update pivot
        $customer->professionals()->sync($professionalIds);

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('success', 'Ο πελάτης ενημερώθηκε επιτυχώς.');
        }

        return redirect()->route('customers.index')->with('success', 'Ο πελάτης ενημερώθηκε επιτυχώς.');
    }


    public function show(Request $request, Customer $customer)
    {
        // Φορτώνουμε τις βασικές σχέσεις του πελάτη
        $customer->load([
            'company',
            'professionals',
            'appointments.professional',
            'appointments.company',
            'appointments.payment',
            'appointments.creator',
            'files.uploader'
        ]);

        /**
         * 🔹 Ιστορικό πληρωμών (ομαδοποιημένο ανά ημερομηνία)
         */
        $payments = Payment::where('customer_id', $customer->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $paymentsByDate = $payments->groupBy(function ($payment) {
            if (!$payment->paid_at) {
                return 'Χωρίς ημερομηνία';
            }

            return Carbon::parse($payment->paid_at)->toDateString(); // Y-m-d
        });

        /**
         * 🔹 ΝΕΟ FILTER ΗΜΕΡΟΜΗΝΙΑΣ:
         * range = month | day | all
         * default = current month
         */
        $range = $request->input('range', 'month'); // month/day/all
        $nav   = $request->input('nav');            // prev/next

        $day   = $request->input('day');            // Y-m-d
        $month = $request->input('month');          // Y-m

        // Default values
        if ($range === 'day') {
            $day = $day ?: now()->toDateString(); // default = today
            $month = null;
        } elseif ($range === 'month') {
            $month = $month ?: now()->format('Y-m'); // default = current month
            $day = null;
        } else {
            // all
            $day = null;
            $month = null;
        }

        // Handle prev/next navigation
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

        // Compute from/to (date strings) depending on range
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
            // all => no date filtering
            $from = null;
            $to   = null;
        }

        /**
         * 🔹 Παίρνουμε τα φίλτρα από το request
         */
        $status        = $request->input('status');           // (προαιρετικά, αν το χρησιμοποιήσεις αργότερα)
        $paymentStatus = $request->input('payment_status');
        $paymentMethod = $request->input('payment_method');

        /**
         * 🔹 Ξεκινάμε από όλα τα ραντεβού του πελάτη (όχι διαγραμμένα)
         * Η σχέση appointments ήδη φιλτράρει soft-deleted λόγω SoftDeletes.
         */
        $appointmentsCollection = $customer->appointments
            ->sortByDesc('start_time')
            ->values();

        $filteredAppointments = $appointmentsCollection;

        /**
         * 🔹 Φίλτρο: Ημερομηνία (σύμφωνα με range)
         */
        if ($from && $to) {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($from, $to) {
                if (!$a->start_time) {
                    return false;
                }
                $d = $a->start_time->toDateString();
                return $d >= $from && $d <= $to;
            });
        }

        /**
         * 🔹 Φίλτρο: Κατάσταση πληρωμής (unpaid / partial / full)
         */
        if ($paymentStatus && $paymentStatus !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($paymentStatus) {
                $payment = $a->payment;
                $total   = $a->total_price ?? 0;
                $paid    = $payment->amount ?? 0;

                switch ($paymentStatus) {
                    case 'unpaid':
                        return $paid <= 0;
                    case 'partial':
                        return $paid > 0 && $paid < $total;
                    case 'full':
                        return $total > 0 && $paid >= $total;
                    default:
                        return true;
                }
            });
        }

        /**
         * 🔹 Φίλτρο: Τρόπος πληρωμής (cash / card)
         */
        if ($paymentMethod && $paymentMethod !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($paymentMethod) {
                if (!$a->payment) {
                    return false;
                }
                return $a->payment->method === $paymentMethod;
            });
        }

        // Αν θέλεις στο μέλλον να φιλτράρεις και με βάση appointment->status:
        /*
        if ($status && $status !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($status) {
                return $a->status === $status;
            });
        }
        */

        // Κάνουμε reset τα keys της συλλογής
        $filteredAppointments = $filteredAppointments->values();

        /**
         * 🔹 GLOBAL Στατιστικά (χωρίς φίλτρα) - αυτά θα δείχνεις πάνω
         */
        $allAppointments = $appointmentsCollection; // όλα τα ραντεβού του πελάτη (όχι soft deleted)

        $globalAppointmentsCount = $allAppointments->count();

        $globalTotalAmount = $allAppointments->sum(function ($a) {
            return $a->total_price ?? 0;
        });

        $globalPaidTotal = $allAppointments->sum(function ($a) {
            return $a->payment->amount ?? 0;
        });

        $globalOutstandingTotal = max($globalTotalAmount - $globalPaidTotal, 0);

        /**
         * 🔹 Στατιστικά με βάση ΤΑ ΦΙΛΤΡΑΡΙΣΜΕΝΑ ραντεβού
         */
        $appointmentsCount = $filteredAppointments->count();

        $filteredTotalAmount = $filteredAppointments->sum(function ($a) {
            return $a->total_price ?? 0;
        });

        $filteredPaidTotal = $filteredAppointments->sum(function ($a) {
            return $a->payment->amount ?? 0;
        });

        $filteredOutstandingTotal = max($filteredTotalAmount - $filteredPaidTotal, 0);

        $cashTotal = $filteredAppointments->sum(function ($a) {
            return ($a->payment && $a->payment->method === 'cash')
                ? $a->payment->amount
                : 0;
        });

        $cardTotal = $filteredAppointments->sum(function ($a) {
            return ($a->payment && $a->payment->method === 'card')
                ? $a->payment->amount
                : 0;
        });

        /**
         * 🔹 ΧΩΡΙΣ PAGINATION: περνάμε ΟΛΗ τη συλλογή στο blade
         */
        $appointments = $filteredAppointments;

        /**
         * 🔹 Φίλτρα που περνάμε στο Blade
         */
        $filters = [
            'range'          => $range,
            'day'            => $day,
            'month'          => $month,

            'status'         => $status ?? 'all',
            'payment_status' => $paymentStatus ?? 'all',
            'payment_method' => $paymentMethod ?? 'all',
        ];

        /**
         * 🔹 URLs για Prev/Next (ΣΤΑΘΕΡΑ: πάντα κουβαλάνε day/month)
         */
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

        /**
         * 🔹 Label για Blade: "Έχετε επιλέξει: ..."
         */
        $selectedLabel = 'Όλα';

        if ($range === 'day' && $day) {
            $selectedLabel = Carbon::parse($day)->locale('el')->translatedFormat('D d/m/Y'); // Δευ 05/01/2026
        } elseif ($range === 'month' && $month) {
            $selectedLabel = Carbon::createFromFormat('Y-m', $month)->locale('el')->translatedFormat('F Y'); // Ιανουάριος 2026
        }

        return view('customers.show', compact(
            'customer',
            'appointments',
            'appointmentsCount',

            // ✅ GLOBAL totals για το πάνω summary
            'globalAppointmentsCount',
            'globalTotalAmount',
            'globalPaidTotal',
            'globalOutstandingTotal',

            // (αν θες να τα δείχνεις κάπου αλλού)
            // 'filteredTotalAmount',
            // 'filteredPaidTotal',
            // 'filteredOutstandingTotal',

            'cashTotal',
            'cardTotal',
            'filters',
            'paymentsByDate',
            'prevUrl',
            'nextUrl',
            'selectedLabel'
        ));
    }

    public function paymentPreview(Request $request, Customer $customer)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $from = Carbon::parse($request->from)->startOfDay();
        $to   = Carbon::parse($request->to)->endOfDay();

        $appointments = Appointment::where('customer_id', $customer->id)
            ->whereNotNull('start_time')
            ->whereBetween('start_time', [$from, $to])
            ->get();

        $total = $appointments->sum(function ($a) {
            return $a->total_price ?? 0;
        });

        return response()->json([
            'count' => $appointments->count(),
            'total' => round($total, 2),
            'formatted' => number_format($total, 2, ',', '.') . ' €',
        ]);
    }


    public function payAll(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'from'   => 'required|date',
            'to'     => 'required|date|after_or_equal:from',
            'method' => 'required|in:cash,card',
            'tax'    => 'nullable|in:Y,N',
            'bank'   => 'nullable|string|max:255', // ✅ NEW
        ], [
            'from.required' => 'Πρέπει να επιλέξετε Από ημερομηνία.',
            'to.required'   => 'Πρέπει να επιλέξετε Έως ημερομηνία.',
            'to.after_or_equal' => 'Το Έως πρέπει να είναι μετά ή ίσο με το Από.',
            'method.required' => 'Πρέπει να επιλέξετε τρόπο πληρωμής.',
            'method.in' => 'Η μέθοδος πληρωμής πρέπει να είναι Μετρητά ή Κάρτα.',
            'tax.in' => 'Η τιμή ΦΠΑ πρέπει να είναι Ν ή Y.',
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to   = Carbon::parse($data['to'])->endOfDay();

        $method = $data['method'];

        // TAX – κοινό για όλα
        if ($method === 'card') {
            // Κάρτα ⇒ πάντα με απόδειξη
            $tax = 'Y';
        } else {
            // Μετρητά ⇒ επιλογή χρήστη, default N
            $tax = ($request->input('tax') === 'Y') ? 'Y' : 'N';
        }

        $bank = $data['bank'] ?? null;

        // Παίρνουμε όλα τα ραντεβού του πελάτη στο διάστημα
        $appointments = Appointment::where('customer_id', $customer->id)
            ->whereNotNull('start_time')
            ->whereBetween('start_time', [$from, $to])
            ->get();

        if ($appointments->isEmpty()) {
            return back()->with('error', 'Δεν βρέθηκαν ραντεβού στο επιλεγμένο χρονικό διάστημα.');
        }

        $updated = 0;

        foreach ($appointments as $appointment) {
            $total = $appointment->total_price ?? 0;

            // Αγνόησε ραντεβού χωρίς ποσό
            if ($total <= 0) {
                continue;
            }

            Payment::updateOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'customer_id' => $customer->id,
                    'amount'      => $total,
                    'is_full'     => true,
                    'paid_at'     => now(),
                    'method'      => $method,
                    'tax'         => $tax,
                    'bank'        => $bank, // ✅ NEW
                    'notes'       => 'Μαζική πληρωμή βάσει ημερομηνιών.',
                ]
            );

            $updated++;
        }

        if ($updated === 0) {
            return back()->with('error', 'Δεν υπήρχαν ραντεβού με ποσό > 0 στο διάστημα.');
        }

        return back()->with('success', "Ενημερώθηκαν πληρωμές για {$updated} ραντεβού στο επιλεγμένο διάστημα.");
    }



    public function deleteAppointments(Request $request, Customer $customer)
    {
        $appointmentIds = $request->input('appointments', []);

        if (empty($appointmentIds)) {
            return back()->with('error', 'Δεν επιλέχθηκαν ραντεβού για διαγραφή.');
        }

        // Μόνο ραντεβού αυτού του πελάτη
        $appointments = Appointment::whereIn('id', $appointmentIds)
            ->where('customer_id', $customer->id)
            ->get();

        if ($appointments->isEmpty()) {
            return back()->with('error', 'Δεν βρέθηκαν έγκυρα ραντεβού για διαγραφή.');
        }

        foreach ($appointments as $appointment) {
            $appointment->delete(); // 👈 soft delete
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
