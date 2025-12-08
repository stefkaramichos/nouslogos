<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\TherapistAppointment;
use App\Models\Professional;
  use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ProfessionalController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $professionals = Professional::with('companies')
            ->when($search, function ($query) use ($search) {
                $query->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhereHas('companies', function ($q) use ($search) {
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
                'first_name'     => 'required|string|max:100',
                'last_name'      => 'nullable|string|max:100',
                'phone'          => 'nullable|string|max:30',
                'email'          => 'nullable|email|max:150',
                'companies'      => 'required|array',
                'companies.*'    => 'exists:companies,id',
                'service_fee'    => 'nullable|numeric|min:0',
                'percentage_cut' => 'nullable|numeric|min:0',
                'salary'         => 'nullable|numeric|min:0',

                // password
                'password'       => 'required|string|min:6|confirmed',
            ],
            [
                'password.required'  => 'Ο κωδικός είναι υποχρεωτικός.',
                'password.confirmed' => 'Οι κωδικοί δεν ταιριάζουν.',
            ]
        );

        // Hash το password
        $data['password'] = \Hash::make($request->password);

        // Πάρε τις εταιρείες σε ξεχωριστή μεταβλητή
        $companyIds = $data['companies'];
        unset($data['companies']);

        // ✅ Γέμισε και το παλιό πεδίο company_id (π.χ. με την πρώτη εταιρεία)
        $data['company_id'] = $companyIds[0]; // είναι safe, γιατί companies είναι required & array

        // Δημιουργία επαγγελματία
        $professional = Professional::create($data);

        // Πολλές εταιρείες στο pivot
        $professional->companies()->sync($companyIds);

        return redirect()
            ->route('professionals.index')
            ->with('success', 'Ο επαγγελματίας δημιουργήθηκε επιτυχώς.');
    }



    public function toggleActive(Professional $professional)
    {
        $professional->is_active = !$professional->is_active;
        $professional->save();

        return redirect()
            ->route('professionals.index')
            ->with(
                'success',
                $professional->is_active
                    ? 'Ο επαγγελματίας ενεργοποιήθηκε επιτυχώς.'
                    : 'Ο επαγγελματίας απενεργοποιήθηκε επιτυχώς.'
            );
    }


    public function edit(Professional $professional)
    {
        $companies = Company::all();
        return view('professionals.edit', compact('professional', 'companies'));
    }

    public function getCompany(Request $request)
    {
        $professional = \App\Models\Professional::find($request->professional_id);

        if (!$professional) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'company_id' => $professional->company_id,
        ]);
    }

  

    public function update(Request $request, Professional $professional)
    {
        // Βασικοί κανόνες για όλους
        $rules = [
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:150',
            'companies'      => 'required|array',
            'companies.*'    => 'exists:companies,id',
            'service_fee'    => 'nullable|numeric|min:0',
            'percentage_cut' => 'nullable|numeric|min:0',
            'salary'         => 'nullable|numeric|min:0',
        ];

        // Αν ο τρέχων χρήστης είναι owner, επιτρέπουμε αλλαγή κωδικού
        if (Auth::user()->role === 'owner') {
            $rules['password'] = 'nullable|string|min:6|confirmed';
        }

        $messages = [
            'first_name.required' => 'Το μικρό όνομα είναι υποχρεωτικό.',
            'last_name.required'  => 'Το επίθετο είναι υποχρεωτικό.',
            // 'phone.required'      => 'Το τηλέφωνο είναι υποχρεωτικό.', // το έχεις nullable πάνω
            'password.confirmed'  => 'Οι κωδικοί δεν ταιριάζουν.',
            'password.min'        => 'Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες.',
        ];

        $data = $request->validate($rules, $messages);

        // Password για owner
        if (Auth::user()->role === 'owner' && !empty($data['password'])) {
            $professional->password = Hash::make($data['password']);
        }

        // Κρατάμε τις εταιρείες ξεχωριστά
        $companyIds = $data['companies'] ?? [];
        unset($data['password'], $data['password_confirmation'], $data['companies']);

        // Optional: ενημέρωσε και το παλιό company_id με την πρώτη εταιρεία
        // $data['company_id'] = $companyIds[0] ?? null;

        if (!empty($companyIds)) {
            $data['company_id'] = $companyIds[0];
        }

        // Update βασικών πεδίων
        $professional->update($data);

        // Sync εταιρειών
        if (!empty($companyIds)) {
            $professional->companies()->sync($companyIds);
        }

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

    // ----------------- MAIN APPOINTMENTS -----------------
    $appointments = $professional->appointments()
        ->with(['customer', 'company', 'payment'])
        ->orderBy('start_time', 'desc')
        ->get();

    // Φίλτρα πάνω στην collection (appointments)
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
            $full    = mb_strtolower($a->customer->first_name.' '.$a->customer->last_name);
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

    // Μετά τα φίλτρα
    $appointmentsCount = $appointments->count();
    $totalAmount = $appointments->sum(fn($a) => $a->total_price ?? 0);

    $professionalTotalCut = $appointments->sum(function ($a) use ($professional) {
        if (!is_null($a->professional_amount)) {
            return $a->professional_amount;
        }
        return $professional->percentage_cut;
    });

    $paidTotal = $appointments->sum(fn($a) => $a->payment->amount ?? 0);
    $outstandingTotal = max($totalAmount - $paidTotal, 0);

    $professionalPaid = $appointments->sum(function ($a) {
        if (!$a->payment) {
            return 0;
        }
        $total = $a->total_price ?? 0;
        $paid  = $a->payment->amount ?? 0;

        return ($total > 0 && $paid >= $total)
            ? ($a->professional_amount ?? 0)
            : 0;
    });

    $professionalOutstanding = max($professionalTotalCut - $professionalPaid, 0);

    // ----------------- THERAPIST APPOINTMENTS ΜΕ ΦΙΛΤΡΑ -----------------

    $therapistQuery = TherapistAppointment::with('customer')
        ->where('professional_id', $professional->id);

    if ($from) {
        $therapistQuery->whereDate('start_time', '>=', $from);
    }

    if ($to) {
        $therapistQuery->whereDate('start_time', '<=', $to);
    }

    if ($customerName) {
        $name = mb_strtolower($customerName);
        $therapistQuery->whereHas('customer', function ($q) use ($name) {
            $q->whereRaw("LOWER(CONCAT(first_name,' ',last_name)) like ?", ["%{$name}%"])
              ->orWhereRaw("LOWER(CONCAT(last_name,' ',first_name)) like ?", ["%{$name}%"]);
        });
    }

    $therapistAppointments = $therapistQuery->get();

    // Map για main appointments (customer + date) για εύκολο matching
    $mainKeys = [];
    foreach ($appointments as $a) {
        if ($a->customer_id && $a->start_time) {
            $key = $a->customer_id.'|'.$a->start_time->toDateString();
            $mainKeys[$key] = true;
        }
    }

    $therapistMatches = [];
    $therapistMissing = [];

    foreach ($therapistAppointments as $ta) {
        if (!$ta->start_time) {
            continue;
        }

        $key = $ta->customer_id.'|'.$ta->start_time->toDateString();

        if (isset($mainKeys[$key])) {
            $therapistMatches[$key] = true;
        } else {
            // ΜΟΝΟ όσα ανήκουν στις φιλτραρισμένες ημερομηνίες / πελάτες φτάνουν εδώ
            $therapistMissing[] = $ta;
        }
    }

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
        'filters',
        'therapistMatches',
        'therapistMissing'
    ));
}
}
