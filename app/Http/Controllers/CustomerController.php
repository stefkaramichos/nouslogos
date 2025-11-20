<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
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
            ->get();

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
                'phone'      => 'required|string|max:30',
                'email'      => 'nullable|email|max:150',
                'company_id' => 'required|exists:companies,id',
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
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'phone'      => 'required|string|max:30',
            'email'      => 'nullable|email|max:150',
            'company_id' => 'required|exists:companies,id',
        ]);

        $customer->update($data);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Ο πελάτης ενημερώθηκε επιτυχώς.');
    }

    
   public function show(Request $request, Customer $customer)
    {
        $customer->load([
            'company',
            'appointments.professional',
            'appointments.company',
            'appointments.payment',
        ]);

        // Παίρνουμε τα φίλτρα από το request
        $from          = $request->input('from');             // date
        $to            = $request->input('to');               // date
        $status        = $request->input('status');           // all / scheduled / completed / cancelled / no_show
        $paymentStatus = $request->input('payment_status');   // all / unpaid / partial / full
        $paymentMethod = $request->input('payment_method');   // all / cash / card

        // Βασικό σύνολο ραντεβών πελάτη (πριν τα φίλτρα)
        $appointments = $customer->appointments
            ->sortByDesc('start_time')
            ->values(); // to reset keys

        // Εφαρμογή φίλτρων σε collection

        if ($from) {
            $appointments = $appointments->filter(function ($a) use ($from) {
                return $a->start_time && $a->start_time->toDateString() >= $from;
            });
        }

        if ($to) {
            $appointments = $appointments->filter(function ($a) use ($to) {
                return $a->start_time && $a->start_time->toDateString() <= $to;
            });
        }

        if ($status && $status !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($status) {
                return $a->status === $status;
            });
        }

        if ($paymentStatus && $paymentStatus !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentStatus) {
                $total = $a->total_price ?? 0;
                $paid  = $a->payment->amount ?? 0;

                if ($paymentStatus === 'unpaid') {
                    return $paid <= 0;
                }

                if ($paymentStatus === 'partial') {
                    return $paid > 0 && $paid < $total;
                }

                if ($paymentStatus === 'full') {
                    return $total > 0 && $paid >= $total;
                }

                return true;
            });
        }

        if ($paymentMethod && $paymentMethod !== 'all') {
            $appointments = $appointments->filter(function ($a) use ($paymentMethod) {
                if (!$a->payment) {
                    return false;
                }
                return $a->payment->method === $paymentMethod;
            });
        }

        // Τώρα τα ραντεβού είναι φιλτραρισμένα
        $appointmentsCount = $appointments->count();

        $totalAmount = $appointments->sum(function ($a) {
            return $a->total_price ?? 0;
        });

        $paidTotal = $appointments->sum(function ($a) {
            return $a->payment->amount ?? 0;
        });

        $outstandingTotal = max($totalAmount - $paidTotal, 0);

        $cashTotal = $appointments->sum(function ($a) {
            return ($a->payment && $a->payment->method === 'cash')
                ? $a->payment->amount
                : 0;
        });

        $cardTotal = $appointments->sum(function ($a) {
            return ($a->payment && $a->payment->method === 'card')
                ? $a->payment->amount
                : 0;
        });

        // Για να κρατάμε τις τιμές στα inputs
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
            'filters'
        ));
    }




    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Ο πελάτης διαγράφηκε επιτυχώς.');
    }
}
