<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\TherapistAppointment;
use App\Models\Professional;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ProfessionalController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        // ✅ If user clicked "Όλοι"
        if ($request->boolean('clear_company')) {
            $request->session()->forget('professionals_company_id');
        }

        // ✅ Store company when user clicks a company button
        // (do NOT store when clear_company is used)
        if (!$request->boolean('clear_company') && $request->has('company_id')) {
            $request->session()->put('professionals_company_id', $request->input('company_id'));
        }

        // ✅ Determine active company
        $companyId = $request->has('company_id')
            ? $request->input('company_id')
            : $request->session()->get('professionals_company_id');

        // normalize
        if ($companyId === '' || $companyId === null) {
            $companyId = null;
        }

        $professionals = Professional::with(['companies', 'customers'])
            ->when($companyId, function ($query) use ($companyId) {
                $query->whereHas('companies', function ($q) use ($companyId) {
                    $q->where('companies.id', $companyId);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('companies', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('customers', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('last_name')
            ->get();

        $companies = Company::where('is_active', 1)->orderBy('id')->get();

        return view('professionals.index', [
            'professionals' => $professionals,
            'search'        => $search,
            'companies'     => $companies,
            'companyId'     => $companyId,
        ]);
    }

    public function create()
    {
        $companies  = Company::all();
        $customers  = Customer::orderBy('last_name')->orderBy('first_name')->get();

        return view('professionals.create', compact('companies', 'customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'first_name'     => 'required|string|max:100',
                'last_name'      => 'nullable|string|max:100',
                'phone'          => 'nullable|string|max:30',
                'email'          => 'nullable|email|max:150',

                'eidikotita'     => 'nullable|in:Λογοθεραπευτής,Ειδικός παιδαγωγός,Εργοθεραπευτής,Ψυχοθεραπευτής',

                'companies'      => 'required|array',
                'companies.*'    => 'exists:companies,id',

                'customers'      => 'nullable|array',
                'customers.*'    => 'exists:customers,id',

                'service_fee'    => 'nullable|numeric|min:0',
                'percentage_cut' => 'nullable|numeric|min:0',
                'salary'         => 'nullable|numeric|min:0',

                'password'       => 'required|string|min:6|confirmed',
                'profile_image'  => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048',
            ],
            [
                'password.required'  => 'Ο κωδικός είναι υποχρεωτικός.',
                'password.confirmed' => 'Οι κωδικοί δεν ταιριάζουν.',
            ]
        );

        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('professionals', 'public');
            $data['profile_image'] = $path;
        }

        $companyIds = $data['companies'];
        unset($data['companies']);

        $customerIds = $data['customers'] ?? [];
        unset($data['customers']);

        $data['company_id'] = $companyIds[0];

        $professional = Professional::create($data);

        $professional->companies()->sync($companyIds);
        $professional->customers()->sync($customerIds);

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
        $customers = Customer::orderBy('last_name')->orderBy('first_name')->get();

        $professional->load('customers', 'companies');

        return view('professionals.edit', compact('professional', 'companies', 'customers'));
    }

    public function getCompany(Request $request)
    {
        $professional = Professional::find($request->professional_id);

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
        $rules = [
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:150',

            'eidikotita'     => 'nullable|in:Λογοθεραπευτής,Ειδικός παιδαγωγός,Εργοθεραπευτής,Ψυχοθεραπευτής',

            'companies'      => 'required|array',
            'companies.*'    => 'exists:companies,id',

            'customers'      => 'nullable|array',
            'customers.*'    => 'exists:customers,id',

            'service_fee'    => 'nullable|numeric|min:0',
            'percentage_cut' => 'nullable|numeric|min:0',
            'salary'         => 'nullable|numeric|min:0',

            'profile_image'  => 'nullable|image|mimes:jpeg,png,jpg,webp,gif',
        ];

        if (Auth::user()->role === 'owner') {
            $rules['password'] = 'nullable|string|min:6|confirmed';
        }

        $messages = [
            'first_name.required' => 'Το μικρό όνομα είναι υποχρεωτικό.',
            'password.confirmed'  => 'Οι κωδικοί δεν ταιριάζουν.',
            'password.min'        => 'Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες.',
        ];

        $data = $request->validate($rules, $messages);

        if (Auth::user()->role === 'owner' && !empty($data['password'])) {
            $professional->password = Hash::make($data['password']);
        }

        $companyIds  = $data['companies'] ?? [];
        $customerIds = $data['customers'] ?? [];

        unset(
            $data['password'],
            $data['password_confirmation'],
            $data['companies'],
            $data['customers']
        );

        if ($request->hasFile('profile_image')) {
            if ($professional->profile_image) {
                Storage::disk('public')->delete($professional->profile_image);
            }
            $path = $request->file('profile_image')->store('professionals', 'public');
            $data['profile_image'] = $path;
        }

        if (!empty($companyIds)) {
            $data['company_id'] = $companyIds[0];
        }

        $professional->update($data);

        if (!empty($companyIds)) {
            $professional->companies()->sync($companyIds);
        }

        $professional->customers()->sync($customerIds);

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
        // Φίλτρα
        $from         = $request->input('from');
        $to           = $request->input('to');
        $customerName = $request->input('customer');

        // (κρατάω τα πεδία για να μη σπάσουν links, αλλά δεν τα εμφανίζεις τώρα στο view)
        $paymentStatus = $request->input('payment_status'); // all/unpaid/partial/full
        $paymentMethod = $request->input('payment_method'); // all/cash/card

        // ✅ Default: σημερινή ημέρα αν δεν έχει δοθεί τίποτα
        if (!$request->hasAny(['from', 'to', 'customer', 'payment_status', 'payment_method'])) {
            $from = now()->toDateString();
            $to   = now()->toDateString();
        }

        // ----------------- MAIN APPOINTMENTS (με payments) -----------------
        $appointmentsCollection = $professional->appointments()
            ->with(['customer', 'company', 'payments']) // ✅ ΟΧΙ payment
            ->orderBy('start_time', 'desc')
            ->get();

        $filteredAppointments = $appointmentsCollection;

        if ($from) {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($from) {
                return $a->start_time && $a->start_time->toDateString() >= $from;
            });
        }

        if ($to) {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($to) {
                return $a->start_time && $a->start_time->toDateString() <= $to;
            });
        }

        if ($customerName) {
            $name = mb_strtolower($customerName);
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($name) {
                if (!$a->customer) return false;

                $full    = mb_strtolower($a->customer->first_name . ' ' . $a->customer->last_name);
                $fullRev = mb_strtolower($a->customer->last_name . ' ' . $a->customer->first_name);

                return str_contains($full, $name) || str_contains($fullRev, $name);
            });
        }

        // ✅ payment status με βάση SUM(payments)
        if ($paymentStatus && $paymentStatus !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($paymentStatus) {
                $total = (float)($a->total_price ?? 0);
                $paid  = (float)$a->payments->sum('amount');

                return match ($paymentStatus) {
                    'unpaid'  => $paid <= 0,
                    'partial' => $paid > 0 && $paid < $total,
                    'full'    => $total > 0 && $paid >= $total,
                    default   => true,
                };
            });
        }

        // ✅ method: αν έχει έστω μία πληρωμή με το method
        if ($paymentMethod && $paymentMethod !== 'all') {
            $filteredAppointments = $filteredAppointments->filter(function ($a) use ($paymentMethod) {
                return $a->payments->contains(fn($p) => $p->method === $paymentMethod);
            });
        }

        // Στατιστικά στα φιλτραρισμένα
        $appointmentsCount = $filteredAppointments->count();

        $totalAmount = $filteredAppointments->sum(fn($a) => (float)($a->total_price ?? 0));

        $professionalTotalCut = $filteredAppointments->sum(function ($a) use ($professional) {
            // αν το ραντεβού έχει override, πάρε το, αλλιώς default του επαγγελματία
            return (float)($a->professional_amount ?? $professional->percentage_cut ?? 0);
        });

        $paidTotal = $filteredAppointments->sum(fn($a) => (float)$a->payments->sum('amount'));
        $outstandingTotal = max($totalAmount - $paidTotal, 0);

        // Πόσο "θεωρείται" πληρωμένος ο επαγγελματίας (μόνο όταν appointment είναι fully paid)
        $professionalPaid = $filteredAppointments->sum(function ($a) {
            $total = (float)($a->total_price ?? 0);
            $paid  = (float)$a->payments->sum('amount');

            return ($total > 0 && $paid >= $total)
                ? (float)($a->professional_amount ?? 0)
                : 0.0;
        });

        $professionalOutstanding = max($professionalTotalCut - $professionalPaid, 0);

        // ----------------- THERAPIST APPOINTMENTS (προσωπικό ημερολόγιο θεραπευτή) -----------------
        $therapistQuery = TherapistAppointment::with('customer')
            ->where('professional_id', $professional->id);

        if ($from) $therapistQuery->whereDate('start_time', '>=', $from);
        if ($to)   $therapistQuery->whereDate('start_time', '<=', $to);

        if ($customerName) {
            $name = mb_strtolower($customerName);
            $therapistQuery->whereHas('customer', function ($q) use ($name) {
                $q->whereRaw("LOWER(CONCAT(first_name,' ',last_name)) like ?", ["%{$name}%"])
                  ->orWhereRaw("LOWER(CONCAT(last_name,' ',first_name)) like ?", ["%{$name}%"]);
            });
        }

        $therapistAppointments = $therapistQuery->get();

        // Matching main vs therapist appointments by (customer_id + date)
        $mainKeys = [];
        foreach ($filteredAppointments as $a) {
            if ($a->customer_id && $a->start_time) {
                $key = $a->customer_id . '|' . $a->start_time->toDateString();
                $mainKeys[$key] = true;
            }
        }

        $therapistMatches = [];
        $therapistMissing = [];

        foreach ($therapistAppointments as $ta) {
            if (!$ta->start_time) continue;

            $key = $ta->customer_id . '|' . $ta->start_time->toDateString();

            if (isset($mainKeys[$key])) {
                $therapistMatches[$key] = true;
            } else {
                $therapistMissing[] = $ta;
            }
        }

        // ✅ Manual pagination για filtered appointments
        $perPage = 25;
        $currentPage = Paginator::resolveCurrentPage() ?: 1;

        $currentItems = $filteredAppointments
            ->values()
            ->forPage($currentPage, $perPage);

        $appointments = new LengthAwarePaginator(
            $currentItems,
            $filteredAppointments->count(),
            $perPage,
            $currentPage,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

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
