@extends('layouts.app')

@section('title', 'Νέο Έξοδο')

@section('content')
    <div class="card">
        <div class="card-header">
            Νέο Έξοδο
        </div>

        <div class="card-body">
            <form action="{{ route('expenses.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Εταιρεία</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- Επιλέξτε εταιρεία --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>
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
                        value="{{ old('amount') }}"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Περιγραφή</label>
                    <textarea
                        name="description"
                        class="form-control"
                        rows="3"
                    >{{ old('description') }}</textarea>
                </div>

                <button class="btn btn-primary">Αποθήκευση</button>
                <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
