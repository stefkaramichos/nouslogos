@extends('layouts.app')

@section('title', 'Επεξεργασία Επαγγελματία')

@section('content')
    <div class="card">
        <div class="card-header">
            Επεξεργασία Επαγγελματία
        </div>
        <div class="card-body">
            <form action="{{ route('professionals.update', $professional) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Όνομα</label>
                    <input
                        type="text"
                        name="first_name"
                        class="form-control"
                        value="{{ old('first_name', $professional->first_name) }}"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Επίθετο</label>
                    <input
                        type="text"
                        name="last_name"
                        class="form-control"
                        value="{{ old('last_name', $professional->last_name) }}"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Τηλέφωνο</label>
                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="{{ old('phone', $professional->phone) }}"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Email (προαιρετικό)</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="{{ old('email', $professional->email) }}"
                    >
                </div>

                {{-- Μισθός --}}
                <div class="mb-3">
                    <label class="form-label">Μισθός (€/μήνα)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="salary"
                        class="form-control"
                        value="{{ old('salary', $professional->salary) }}"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Εταιρεία</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- Επιλέξτε εταιρεία --</option>
                        @foreach($companies as $company)
                            <option
                                value="{{ $company->id }}"
                                @selected(old('company_id', $professional->company_id) == $company->id)
                            >
                                {{ $company->name }} ({{ $company->city }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <button class="btn btn-primary">Αποθήκευση Αλλαγών</button>
                <a href="{{ route('professionals.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
