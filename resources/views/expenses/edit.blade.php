@extends('layouts.app')

@section('title', 'Επεξεργασία Εξόδου')

@section('content')
    <div class="card">
        <div class="card-header">
            Επεξεργασία Εξόδου
        </div>

        <div class="card-body">
            <form action="{{ route('expenses.update', $expense) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Εταιρεία</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- Επιλέξτε εταιρεία --</option>
                        @foreach($companies as $company)
                            <option
                                value="{{ $company->id }}"
                                @selected(old('company_id', $expense->company_id) == $company->id)
                            >
                                {{ $company->name }} ({{ $company->city }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ποσό</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="amount"
                        class="form-control"
                        value="{{ old('amount', $expense->amount) }}"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Περιγραφή</label>
                    <textarea
                        name="description"
                        class="form-control"
                        rows="3"
                    >{{ old('description', $expense->description) }}</textarea>
                </div>

                <button class="btn btn-primary">Αποθήκευση Αλλαγών</button>
                <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
