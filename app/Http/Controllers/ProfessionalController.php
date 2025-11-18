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
}
