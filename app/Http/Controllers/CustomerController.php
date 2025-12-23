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
    public function index()
    {
        $search = request('search');
        $companyId = request('company_id');

        $customers = Customer::query()
            ->with(['company', 'professionals'])   // ✅ add professionals here
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

        return view('customers.index', compact('customers', 'companies', 'search'));
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
        $storedName = Str::random(12) . '_' . time() . '_' . preg_replace('/\s+/', '_', $originalName);
        $dir = "customer-files/{$customer->id}";
        $path = $uploaded->storeAs($dir, $storedName); // default disk = local

        CustomerFile::create([
            'customer_id'    => $customer->id,
            'uploaded_by'    => Auth::user()?->id, // professional id
            'original_name'  => $originalName,
            'stored_name'    => $storedName,
            'path'           => $path,
            'mime_type'      => $mime,
            'size'           => $size,
            'notes'          => $request->input('notes'),
        ]);

        return back()->with('success', 'Το αρχείο ανέβηκε επιτυχώς.');
    }

    public function downloadFile(Customer $customer, CustomerFile $file)
    {
        // ασφάλεια: να ανήκει στον πελάτη
        if ((int)$file->customer_id !== (int)$customer->id) {
            abort(404);
        }

        if (!Storage::exists($file->path)) {
            return back()->with('error', 'Το αρχείο δεν βρέθηκε στο storage.');
        }

        return Storage::download($file->path, $file->original_name);
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
         * 🔹 Παίρνουμε τα φίλτρα από το request
         */
        $from          = $request->input('from');
        $to            = $request->input('to');
        $status        = $request->input('status');           // (προαιρετικά, αν το χρησιμοποιήσεις αργότερα)
        $paymentStatus = $request->input('payment_status');
        $paymentMethod = $request->input('payment_method');

        // Αν δεν έχει σταλεί ΚΑΝΕΝΑ φίλτρο, βάζουμε default τρέχον μήνα
        if (!$request->hasAny(['from', 'to', 'status', 'payment_status', 'payment_method'])) {
            $from = now()->startOfMonth()->toDateString();
            $to   = now()->endOfMonth()->toDateString();
        }

        /**
         * 🔹 Ξεκινάμε από όλα τα ραντεβού του πελάτη (όχι διαγραμμένα)
         * Η σχέση appointments ήδη φιλτράρει soft-deleted λόγω SoftDeletes.
         */
        $appointmentsCollection = $customer->appointments
            ->sortByDesc('start_time')
            ->values();

        $filteredAppointments = $appointmentsCollection;

        /**
         * 🔹 Φίλτρο: Ημερομηνία από
         */
        if ($from) {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($from) {
                if (!$a->start_time) {
                    return false;
                }
                return $a->start_time->toDateString() >= $from;
            });
        }

        /**
         * 🔹 Φίλτρο: Ημερομηνία έως
         */
        if ($to) {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($to) {
                if (!$a->start_time) {
                    return false;
                }
                return $a->start_time->toDateString() <= $to;
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
         * 🔹 Στατιστικά με βάση ΤΑ ΦΙΛΤΡΑΡΙΣΜΕΝΑ ραντεβού
         */
        $appointmentsCount = $filteredAppointments->count();

        $totalAmount = $filteredAppointments->sum(function ($a) {
            return $a->total_price ?? 0;
        });

        $paidTotal = $filteredAppointments->sum(function ($a) {
            return $a->payment->amount ?? 0;
        });

        $outstandingTotal = max($totalAmount - $paidTotal, 0);

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
         * 🔹 Manual pagination πάνω στη filtered συλλογή
         */
        $perPage     = 25;
        $currentPage = Paginator::resolveCurrentPage() ?: 1;

        $currentItems = $filteredAppointments
            ->forPage($currentPage, $perPage);

        $appointments = new LengthAwarePaginator(
            $currentItems,
            $filteredAppointments->count(),
            $perPage,
            $currentPage,
            [
                'path'  => $request->url(),
                'query' => $request->query(), // κρατάμε τα φίλτρα στο pagination links
            ]
        );

        /**
         * 🔹 Φίλτρα που περνάμε στο Blade
         */
        $filters = [
            'from'           => $from,
            'to'             => $to,
            'status'         => $status ?? 'all',
            'payment_status' => $paymentStatus ?? 'all',
            'payment_method' => $paymentMethod ?? 'all',
        ];

        return view('customers.show', compact(
            'customer',
            'appointments',
            'appointmentsCount',
            'totalAmount',
            'paidTotal',
            'outstandingTotal',
            'cashTotal',
            'cardTotal',
            'filters',
            'paymentsByDate'
        ));
    }


    public function payAll(Request $request, Customer $customer)
    {
        // IDs των επιλεγμένων ραντεβών
        $appointmentIds = $request->input('appointments', []);

        if (empty($appointmentIds)) {
            return back()->with('error', 'Δεν επιλέχθηκαν ραντεβού για πληρωμή.');
        }

        // Κοινός τρόπος πληρωμής για όλα
        $method = $request->input('method');

        if (!in_array($method, ['cash', 'card'], true)) {
            return back()->with('error', 'Πρέπει να επιλέξετε τρόπο πληρωμής (μετρητά ή κάρτα).');
        }

        // TAX – κοινό για όλα
        if ($method === 'card') {
            // Κάρτα ⇒ πάντα με απόδειξη
            $tax = 'Y';
        } else {
            // Μετρητά ⇒ επιλογή χρήστη
            $tax = $request->input('tax') === 'Y' ? 'Y' : 'N';
        }

        // Φορτώνουμε ραντεβού του συγκεκριμένου πελάτη για ασφάλεια
        $customer->load(['appointments.payment']);

        foreach ($customer->appointments as $appointment) {
            if (!in_array($appointment->id, $appointmentIds)) {
                continue;
            }

            $total = $appointment->total_price ?? 0;
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
                    'notes'       => 'Μαζική πληρωμή επιλεγμένων ραντεβού.',
                ]
            );
        }

        return back()->with('success', 'Οι πληρωμές για τα επιλεγμένα ραντεβού ενημερώθηκαν επιτυχώς.');
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
