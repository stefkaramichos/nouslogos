<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // Φόρμα επεξεργασίας / δημιουργίας πληρωμής για ένα ραντεβού
    public function edit(Appointment $appointment)
    {
        $appointment->load(['customer', 'professional', 'company', 'payment']);

        $payment = $appointment->payment; // μπορεί να είναι null

        return view('payments.edit', compact('appointment', 'payment'));
    }

    // Αποθήκευση / ενημέρωση πληρωμής
    public function update(Request $request, Appointment $appointment)
    {
        $data = $request->validate(
            [
                'amount'   => 'nullable|numeric|min:0',
                'is_full'  => 'nullable|boolean',
                'method'   => 'nullable|in:cash,card',
                'notes'    => 'nullable|string',
            ],
            [
                'amount.numeric' => 'Το ποσό πρέπει να είναι αριθμός.',
                'method.in'      => 'Η μέθοδος πληρωμής πρέπει να είναι Μετρητά ή Κάρτα.',
            ]
        );

        $total = $appointment->total_price ?? 0;

        $amount = $data['amount'] ?? 0;
        $isFull = $request->boolean('is_full');

        // Αν χρήστης τσεκάρει "πλήρης εξόφληση" και δεν βάλει ποσό → βάλε total
        if ($isFull && $amount <= 0 && $total > 0) {
            $amount = $total;
        }

        // Αν ποσό 0 και δεν είναι full → σβήνουμε πληρωμή (προαιρετικό)
        if ($amount <= 0) {
            // αν υπάρχει πληρωμή, μπορείς είτε να τη σβήσεις είτε να την αφήσεις
            // εδώ επιλέγω delete:
            Payment::where('appointment_id', $appointment->id)->delete();

            return redirect()
                ->back()
                ->with('success', 'Η πληρωμή διαγράφηκε.');
        }

        // Αν ποσό >= total → θεώρησέ το full
        if ($total > 0 && $amount >= $total) {
            $isFull = true;
        }

        Payment::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'customer_id' => $appointment->customer_id,
                'amount'      => $amount,
                'is_full'     => $isFull,
                'paid_at'     => now(),
                'method'      => $data['method'] ?? null,
                'notes'       => $data['notes'] ?? null,
            ]
        );

         $redirectTo = $request->input('redirect_to');

        if ($redirectTo) {
            return redirect($redirectTo)
                ->with('success', 'Η πληρωμή ενημερώθηκε επιτυχώς.');
        }

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Η πληρωμή ενημερώθηκε επιτυχώς.');
    }
}
