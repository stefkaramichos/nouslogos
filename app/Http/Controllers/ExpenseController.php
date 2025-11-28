<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Company;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->get('company_id');

        $query = Expense::with('company')->orderByDesc('created_at');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $expenses  = $query->paginate(20)->withQueryString();
        $companies = Company::orderBy('name')->get();

        return view('expenses.index', [
            'expenses'           => $expenses,
            'companies'          => $companies,
            'selectedCompanyId'  => $companyId,
        ]);
    }

    public function create()
    {
        $companies = Company::orderBy('name')->get();

        return view('expenses.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id'  => ['required', 'exists:companies,id'],
            'amount'      => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        Expense::create($validated);

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Το έξοδο καταχωρήθηκε επιτυχώς.');
    }

    public function edit(Expense $expense)
    {
        $companies = Company::orderBy('name')->get();

        return view('expenses.edit', [
            'expense'   => $expense,
            'companies' => $companies,
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'company_id'  => ['required', 'exists:companies,id'],
            'amount'      => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $expense->update($validated);

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Το έξοδο ενημερώθηκε επιτυχώς.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Το έξοδο διαγράφηκε επιτυχώς.');
    }
}
