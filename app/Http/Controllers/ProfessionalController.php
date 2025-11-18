<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Professional;
use Illuminate\Http\Request;

class ProfessionalController extends Controller
{
    public function index()
    {
        $professionals = Professional::with('company')->orderBy('last_name')->get();

        return view('professionals.index', compact('professionals'));
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
    public function show(Professional $professional)
    {
        $professional->load([
            'company',
            'appointments.customer',
            'appointments.company',
            'appointments.payment'
        ]);

        $appointments = $professional->appointments;

        $appointmentsCount = $appointments->count();

        // Συνολικά έσοδα ραντεβών
        $totalAmount = $appointments->sum(fn($a) => $a->total_price ?? 0);

        // Συνολικά χρήματα που δικαιούται ο επαγγελματίας
        $professionalTotalCut = $appointments->sum(fn($a) => $a->professional_amount ?? 0);

        // Πόσα έχουν πληρωθεί από τον πελάτη (όχι στον επαγγελματία)
        $paidTotal = $appointments->sum(fn($a) => $a->payment->amount ?? 0);

        // Πόσα ΔΕΝ έχουν πληρωθεί ακόμα
        $outstandingTotal = max($totalAmount - $paidTotal, 0);

        // Πόσα έχει πληρωθεί ο επαγγελματίας (αν δεν έχεις πίνακα payments_for_professional)
        // Για τώρα θεωρούμε ότι πληρώνεται ΜΟΝΟ αν το ραντεβού έχει πληρωθεί πλήρως
        $professionalPaid = $appointments->sum(function ($a) {
            if (!$a->payment) {
                return 0;
            }
            // αν έχει πλήρη πληρωμή → παίρνει το ποσό του
            return ($a->payment->amount >= $a->total_price) ? $a->professional_amount : 0;
        });

        // Πόσα του ΧΡΩΣΤΑΝΕ ακόμα
        $professionalOutstanding = max($professionalTotalCut - $professionalPaid, 0);


        return view('professionals.show', compact(
            'professional',
            'appointments',
            'appointmentsCount',
            'totalAmount',
            'professionalTotalCut',
            'paidTotal',
            'outstandingTotal',
            'professionalPaid',
            'professionalOutstanding'
        ));
    }

}
