<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    public function index()
    {
        $appointments = Appointment::with(['customer', 'professional', 'company', 'payment'])
            ->orderBy('start_time', 'desc')
            ->get();

        return view('appointments.index', compact('appointments'));
    }


    public function create()
    {
        $customers     = Customer::orderBy('last_name')->get();
        $professionals = Professional::orderBy('last_name')->get();
        $companies     = Company::all();

        return view('appointments.create', compact('customers', 'professionals', 'companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'customer_id'     => 'required|exists:customers,id',
                'professional_id' => 'required|exists:professionals,id',
                'company_id'      => 'required|exists:companies,id',
                'start_time'      => 'required|date',
                'mark_as_paid'   => 'nullable|boolean',
                'payment_amount' => 'nullable|numeric|min:0',
                'end_time'        => 'nullable|date|after_or_equal:start_time',
                'status'          => 'nullable|string',
                'total_price'     => 'nullable|numeric|min:0',
                'notes'           => 'nullable|string',
            ],
            [
                'customer_id.required'     => 'Ο πελάτης είναι υποχρεωτικός.',
                'professional_id.required' => 'Ο επαγγελματίας είναι υποχρεωτικός.',
                'company_id.required'      => 'Η εταιρεία είναι υποχρεωτική.',
                'start_time.required'      => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
            ]
        );

        $professional = Professional::findOrFail($data['professional_id']);

        $total = $data['total_price'] ?? $professional->service_fee;

        $professionalAmount = $total * ($professional->percentage_cut / 100);
        $companyAmount      = $total - $professionalAmount;

        $data['total_price']         = $total;
        $data['professional_amount'] = $professionalAmount;
        $data['company_amount']      = $companyAmount;
        $data['created_by']          = Auth::id(); // μπορεί να είναι και null αν δεν έχεις auth

        Appointment::create($data);

        // Αν τσεκαρίστηκε ότι πληρώθηκε
        if ($request->boolean('mark_as_paid')) {
            $paymentAmount = $data['payment_amount'] ?? $total;

            if ($paymentAmount > 0) {
                \App\Models\Payment::create([
                    'appointment_id' => $appointment->id,
                    'customer_id'    => $appointment->customer_id,
                    'amount'         => $paymentAmount,
                    'is_full'        => $paymentAmount >= $total,
                    'paid_at'        => now(),
                    'method'         => null,
                    'notes'          => 'Καταχώρηση από τη φόρμα δημιουργίας ραντεβού.',
                ]);
            }
        }

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Το ραντεβού δημιουργήθηκε επιτυχώς.');

    }

    // Προαιρετικά edit/update/delete, όπως στα άλλα controllers
}
