<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role, ['owner', 'grammatia'], true)) {
            abort(403, 'Δεν έχετε πρόσβαση.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        if (Company::where('name', $data['name'])->exists()) {
            return back()->withErrors(['name' => 'Υπάρχει ήδη εταιρεία με αυτό το όνομα.'])->withInput();
        }

        $company = Company::create([
            'name' => $data['name'],
            'city' => $data['city'] ?? null,
            'is_active' => 1,
        ]);

        return redirect()
            ->route('customers.index', array_merge($request->query(), ['company_id' => $company->id]))
            ->with('success', 'Η εταιρεία δημιουργήθηκε.');
    }

    public function destroy(Request $request, Company $company)
    {
        $user = Auth::user();

        // Συνήθως delete μόνο owner (πιο ασφαλές)
         if (!$user || !in_array($user->role, ['owner', 'grammatia'], true)) {
            abort(403, 'Δεν έχετε πρόσβαση.');
        }

        $companyId = (int) $company->id;

        // ✅ Μην διαγράφεις αν υπάρχουν σχετικές εγγραφές
        $hasAppointments = Appointment::where('company_id', $companyId)->exists();
        $hasCustomers    = Customer::where('company_id', $companyId)->exists();
        $hasExpenses     = Expense::where('company_id', $companyId)->exists();
        $hasSettlements  = Settlement::where('company_id', $companyId)->exists();
        $hasProfessionals= Professional::where('company_id', $companyId)->exists();

        // pivot
        $hasPivotPros = DB::table('company_professional')->where('company_id', $companyId)->exists();

        if ($hasAppointments || $hasCustomers || $hasExpenses || $hasSettlements || $hasProfessionals || $hasPivotPros) {
            return back()->withErrors([
                'company_delete' => 'Δεν μπορεί να διαγραφεί: υπάρχουν σχετικές εγγραφές (ραντεβού/πελάτες/έξοδα/εκκαθαρίσεις/θεραπευτές).'
            ]);
        }

        $company->delete();

        // αν ήσουν φιλτραρισμένος σε αυτή την company, γύρνα σε "Όλοι"
        $query = $request->query();
        if ((string)($query['company_id'] ?? '') === (string)$companyId) {
            unset($query['company_id']);
        }

        return redirect()->route('customers.index', $query)->with('success', 'Η εταιρεία διαγράφηκε.');
    }
}
