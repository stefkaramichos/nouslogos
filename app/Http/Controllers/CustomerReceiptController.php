<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CustomerReceiptController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'amount'       => 'nullable|numeric|min:0',
            'comment'      => 'required|string|max:1000',
            'receipt_date' => 'nullable|date',
            'is_issued'    => 'nullable|in:1', // checkbox
        ]);

        CustomerReceipt::create([
            'customer_id'  => $customer->id,
            'amount'       => (float)$data['amount'],
            'comment'      => $data['comment'] ?? null,
            'receipt_date' => !empty($data['receipt_date']) ? Carbon::parse($data['receipt_date'])->toDateString() : null,
            'is_issued'    => isset($data['is_issued']) ? 1 : 0,
            'created_by'   => Auth::id(),
        ]);

        return back()->with('success', 'Η απόδειξη καταχωρήθηκε.');
    }

    public function destroy(Customer $customer, CustomerReceipt $receipt)
    {
        abort_unless((int)$receipt->customer_id === (int)$customer->id, 404);

        $receipt->delete();

        return back()->with('success', 'Η απόδειξη διαγράφηκε.');
    }

    public function inlineUpdate(Request $request, Customer $customer, CustomerReceipt $receipt)
    {
        abort_unless((int)$receipt->customer_id === (int)$customer->id, 404);

        $data = $request->validate([
            'field' => 'required|string',
            'value' => 'nullable',
        ]);

        // ✅ allow-list (ασφάλεια)
        $allowed = ['amount', 'comment', 'receipt_date', 'is_issued'];
        if (!in_array($data['field'], $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Field not allowed'], 403);
        }

        // ✅ validation per field
        $field = $data['field'];
        $value = $data['value'];

        if ($field === 'amount') {
            $request->validate(['value' => 'required|numeric|min:0']);
            $receipt->amount = (float)$value;
        }
        elseif ($field === 'comment') {
            $request->validate(['value' => 'nullable|string|max:1000']);
            $receipt->comment = $value ?: null;
        }
        elseif ($field === 'receipt_date') {
            // επιτρέπεις null ή date
            if ($value === '' || $value === null) {
                $receipt->receipt_date = null;
            } else {
                $request->validate(['value' => 'date']);
                $receipt->receipt_date = Carbon::parse($value)->toDateString();
            }
        }
        elseif ($field === 'is_issued') {
            // από το select θα έρθει "0"/"1"
            $request->validate(['value' => 'required|in:0,1']);
            $receipt->is_issued = (int)$value;
        }

        $receipt->save();

        // Αν θες να μην κάνεις reload, μπορείς να γυρνάς formatted values.
        return response()->json([
            'success' => true,
            'value'   => $receipt->{$field},
            'formatted' => match ($field) {
                'amount' => number_format((float)$receipt->amount, 2, ',', '.') . ' €',
                'receipt_date' => $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('d/m/Y') : '-',
                'is_issued' => (int)$receipt->is_issued,
                default => (string)($receipt->comment ?? ''),
            }
        ]);
    }
}
