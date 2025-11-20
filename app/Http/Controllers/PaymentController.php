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
                'amount'  => 'nullable|numeric|min:0',
                'is_full' => 'nullable|boolean',
                'method'  => 'nullable|in:cash,card',
                'tax'     => 'nullable|in:Y,N',
                'notes'   => 'nullable|string',
            ],
            [
                'amount.numeric' => 'Το ποσό πρέπει να είναι αριθμός.',
                'method.in'      => 'Η μέθοδος πληρωμής πρέπει να είναι Μετρητά ή Κάρτα.',
                'tax.in'         => 'Η τιμή ΦΠΑ πρέπει να είναι Ν ή Y.',
            ]
        );

        $total = $appointment->total_price ?? 0;

        $amount = $data['amount'] ?? 0;
        $isFull = $request->boolean('is_full');
        $method = $data['method'] ?? null;

        // Αν χρήστης τσεκάρει "πλήρης εξόφληση" και δεν βάλει ποσό → βάλε total
        if ($isFull && $amount <= 0 && $total > 0) {
            $amount = $total;
        }

        // Αν ποσό 0 → σβήνουμε πληρωμή
        if ($amount <= 0) {
            Payment::where('appointment_id', $appointment->id)->delete();

            $redirectTo = $request->input('redirect_to');
            if ($redirectTo) {
                return redirect($redirectTo)
                    ->with('success', 'Η πληρωμή διαγράφηκε.');
            }

            return redirect()
                ->route('appointments.index')
                ->with('success', 'Η πληρωμή διαγράφηκε.');
        }

        // Αν ποσό >= total → full
        if ($total > 0 && $amount >= $total) {
            $isFull = true;
        }

        // TAX LOGIC
        $tax = null;

        if ($method === 'card') {
            // Κάρτα ⇒ πάντα ΦΠΑ = Y
            $tax = 'Y';
        } elseif ($method === 'cash') {
            // Μετρητά ⇒ επιλέγει ο χρήστης, default N
            $taxInput = $request->input('tax');
            $tax = ($taxInput === 'Y') ? 'Y' : 'N';
        } else {
            // Αν δεν έχει οριστεί μέθοδος, μπορούμε να αφήσουμε null ή N.
            $tax = $data['tax'] ?? 'N';
        }

        Payment::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'customer_id' => $appointment->customer_id,
                'amount'      => $amount,
                'is_full'     => $isFull,
                'paid_at'     => now(),
                'method'      => $method,
                'tax'         => $tax,
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
