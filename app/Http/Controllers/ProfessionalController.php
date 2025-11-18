<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Professional;
use Illuminate\Http\Request;

class ProfessionalController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $professionals = Professional::with('company')
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

        return view('professionals.index', compact('professionals', 'search'));
    }

    public function create()
    {
        $companies = Company::all();

        return view('professionals.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'first_name'      => 'required|string|max:100',
                'last_name'       => 'required|string|max:100',
                'phone'           => 'required|string|max:30',
                'email'           => 'nullable|email|max:150',
                'company_id'      => 'required|exists:companies,id',
                'service_fee'     => 'required|numeric|min:0',
                'percentage_cut'  => 'required|numeric|min:0|max:100',
            ],
            [
                'first_name.required'     => 'Το μικρό όνομα είναι υποχρεωτικό.',
                'last_name.required'      => 'Το επίθετο είναι υποχρεωτικό.',
                'phone.required'          => 'Το τηλέφωνο είναι υποχρεωτικό.',
                'company_id.required'     => 'Η εταιρεία είναι υποχρεωτική.',
                'service_fee.required'    => 'Η χρέωση υπηρεσίας είναι υποχρεωτική.',
                'percentage_cut.required' => 'Το ποσοστό είναι υποχρεωτικό.',
            ]
        );

        Professional::create($data);

        return redirect()
            ->route('professionals.index')
            ->with('success', 'Ο επαγγελματίας δημιουργήθηκε επιτυχώς.');
    }

    public function edit(Professional $professional)
    {
        $companies = Company::all();
        return view('professionals.edit', compact('professional', 'companies'));
    }

    public function update(Request $request, Professional $professional)
    {
        $data = $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'phone'           => 'required|string|max:30',
            'email'           => 'nullable|email|max:150',
            'company_id'      => 'required|exists:companies,id',
            'service_fee'     => 'required|numeric|min:0',
            'percentage_cut'  => 'required|numeric|min:0|max:100',
        ]);

        $professional->update($data);

        return redirect()
            ->route('professionals.index')
            ->with('success', 'Ο επαγγελματίας ενημερώθηκε επιτυχώς.');
    }

    public function destroy(Professional $professional)
    {
        $professional->delete();

        return redirect()
            ->route('professionals.index')
            ->with('success', 'Ο επαγγελματίας διαγράφηκε επιτυχώς.');
    }
    
    public function show(Request $request, Professional $professional)
    {
        // Παίρνουμε τα φίλτρα από το request
        $from           = $request->input('from');            // date
        $to             = $request->input('to');              // date
        $customerName   = $request->input('customer');        // text
        $paymentStatus  = $request->input('payment_status');  // all / unpaid / partial / full
        $paymentMethod  = $request->input('payment_method');  // all / cash / card

        // Φορτώνουμε όλα τα ραντεβού του επαγγελματία με σχέσεις
        $appointments = $professional->appointments()
            ->with(['customer', 'company', 'payment'])
            ->orderBy('start_time', 'desc')
            ->get();

        // Εφαρμογή φίλτρων σε collection (αρκετό για κλασικό ιατρείο / γραφείο)
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

        if ($customerName) {
            $name = mb_strtolower($customerName);
            $appointments = $appointments->filter(function ($a) use ($name) {
                if (!$a->customer) {
                    return false;
                }
                $full = mb_strtolower($a->customer->first_name.' '.$a->customer->last_name);
                $fullRev = mb_strtolower($a->customer->last_name.' '.$a->customer->first_name);
                return str_contains($full, $name) || str_contains($fullRev, $name);
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

        // Τώρα τα appointments είναι ήδη φιλτραρισμένα
        $appointmentsCount = $appointments->count();

        // Συνολικά ποσά
        $totalAmount = $appointments->sum(fn($a) => $a->total_price ?? 0);

        $professionalTotalCut = $appointments->sum(fn($a) => $a->professional_amount ?? 0);

        // Πόσα έχουν πληρωθεί από πελάτες
        $paidTotal = $appointments->sum(fn($a) => $a->payment->amount ?? 0);

        // Πόσα ραντεβού παραμένουν απλήρωτα (ως ποσό)
        $outstandingTotal = max($totalAmount - $paidTotal, 0);

        // Πόσα έχει πληρωθεί ο επαγγελματίας (απλή υπόθεση: όταν το ραντεβού είναι πλήρως πληρωμένο)
        $professionalPaid = $appointments->sum(function ($a) {
            if (!$a->payment) {
                return 0;
            }
            $total = $a->total_price ?? 0;
            $paid  = $a->payment->amount ?? 0;
            return ($total > 0 && $paid >= $total) ? ($a->professional_amount ?? 0) : 0;
        });

        $professionalOutstanding = max($professionalTotalCut - $professionalPaid, 0);

        // για να κρατάμε τις τιμές στα inputs
        $filters = [
            'from'           => $from,
            'to'             => $to,
            'customer'       => $customerName,
            'payment_status' => $paymentStatus ?? 'all',
            'payment_method' => $paymentMethod ?? 'all',
        ];

        return view('professionals.show', compact(
            'professional',
            'appointments',
            'appointmentsCount',
            'totalAmount',
            'professionalTotalCut',
            'paidTotal',
            'outstandingTotal',
            'professionalPaid',
            'professionalOutstanding',
            'filters'
        ));
    }

}
