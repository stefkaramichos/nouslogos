<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $customers = Customer::with('company')
            ->when($search, function ($query) use ($search) {
                $query->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhereHas('company', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    });
            })
            ->orderBy('last_name')
            ->paginate(25)
            ->withQueryString();   // <-- keeps search when switching page

        return view('customers.index', compact('customers', 'search'));
    }

    public function create()
    {
        $companies = Company::all();

        return view('customers.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'first_name' => 'required|string|max:100',
                'last_name'  => 'required|string|max:100',
                'phone'      => 'nullable|string|max:30',
                'email'      => 'nullable|email|max:150',
                'company_id' => 'nullable|exists:companies,id',

                // ΝΕΑ ΠΕΔΙΑ
                'tax_office' => 'nullable|string|max:100', // ΔΟΥ
                'vat_number' => 'nullable|string|max:20',  // ΑΦΜ

                'informations' => 'nullable|string',       // 👈 ΝΕΟ
            ],
            [
                'first_name.required' => 'Το μικρό όνομα είναι υποχρεωτικό.',
                'last_name.required'  => 'Το επίθετο είναι υποχρεωτικό.',
                'phone.required'      => 'Το τηλέφωνο είναι υποχρεωτικό.',
                'company_id.required' => 'Η εταιρεία είναι υποχρεωτική.',
            ]
        );

        Customer::create($data);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Ο πελάτης δημιουργήθηκε επιτυχώς.');
    }

    public function edit(Customer $customer)
    {
        $companies = Company::all();
        return view('customers.edit', compact('customer', 'companies'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'phone'        => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:150',
            'company_id'   => 'nullable|exists:companies,id',
            'tax_office'   => 'nullable|string|max:100',
            'vat_number'   => 'nullable|string|max:20',
            'informations' => 'nullable|string',   // 👈 ΝΕΟ
        ]);

        $customer->update($data);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Ο πελάτης ενημερώθηκε επιτυχώς.');
    }

    public function show(Request $request, Customer $customer)
{
    // Φορτώνουμε τις βασικές σχέσεις του πελάτη
    $customer->load([
        'company',
        'appointments.professional',
        'appointments.company',
        'appointments.payment',
        'appointments.creator'
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
