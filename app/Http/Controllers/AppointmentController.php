<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Payment;
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
                'end_time'        => 'nullable|date|after_or_equal:start_time',
                'status'          => 'nullable|string',
                'total_price'     => 'nullable|numeric|min:0',
                'notes'           => 'nullable|string',
                'mark_as_paid'    => 'nullable|boolean',
                'payment_amount'  => 'nullable|numeric|min:0',
            ],
            [
                'customer_id.required'     => 'Ο πελάτης είναι υποχρεωτικός.',
                'professional_id.required' => 'Ο επαγγελματίας είναι υποχρεωτικός.',
                'company_id.required'      => 'Η εταιρεία είναι υποχρεωτική.',
                'start_time.required'      => 'Η ημερομηνία/ώρα είναι υποχρεωτική.',
            ]
        );

        $professional = Professional::findOrFail($data['professional_id']);

        // Αν δεν δώσεις total_price, παίρνουμε τη χρέωση του επαγγελματία
        $total = $data['total_price'] ?? $professional->service_fee;

        $professionalAmount = $total * ($professional->percentage_cut / 100);
        $companyAmount      = $total - $professionalAmount;

        $data['total_price']         = $total;
        $data['professional_amount'] = $professionalAmount;
        $data['company_amount']      = $companyAmount;
        $data['created_by']          = Auth::id();

        $appointment = Appointment::create($data);

        // Αν έχει τσεκαριστεί ότι πληρώθηκε, δημιουργούμε και μια πληρωμή
        if ($request->boolean('mark_as_paid')) {
            $paymentAmount = $data['payment_amount'] ?? $total;

            if ($paymentAmount > 0) {
                Payment::create([
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

    public function show(Appointment $appointment)
    {
        $appointment->load(['customer', 'professional', 'company', 'payment']);

        return view('appointments.show', compact('appointment'));
    }

    public function edit(Appointment $appointment)
    {
        $appointment->load(['customer', 'professional', 'company']);

        $customers     = Customer::orderBy('last_name')->get();
        $professionals = Professional::orderBy('last_name')->get();
        $companies     = Company::all();

        return view('appointments.edit', compact('appointment', 'customers', 'professionals', 'companies'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $request->validate(
            [
                'customer_id'     => 'required|exists:customers,id',
                'professional_id' => 'required|exists:professionals,id',
                'company_id'      => 'required|exists:companies,id',
                'start_time'      => 'required|date',
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

        // Ξαναυπολογίζουμε οικονομικά με βάση τον επαγγελματία
        $professional = Professional::findOrFail($data['professional_id']);

        $total = $data['total_price'] ?? $professional->service_fee;

        $professionalAmount = $total * ($professional->percentage_cut / 100);
        $companyAmount      = $total - $professionalAmount;

        $data['total_price']         = $total;
        $data['professional_amount'] = $professionalAmount;
        $data['company_amount']      = $companyAmount;

        $appointment->update($data);

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Το ραντεβού ενημερώθηκε επιτυχώς.');
    }

    public function destroy(Appointment $appointment)
    {
        // Σβήνουμε πρώτα τυχόν πληρωμές (αν δεν έχεις ON DELETE CASCADE)
        $appointment->payments()->delete();

        $appointment->delete();

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Το ραντεβού διαγράφηκε επιτυχώς.');
    }
}
