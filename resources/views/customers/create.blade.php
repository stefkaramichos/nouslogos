@extends('layouts.app')

@section('title', 'Νέος Πελάτης')

@section('content')
    <div class="card">
        <div class="card-header">
            Νέος Πελάτης
        </div>
        <div class="card-body">
            <form action="{{ route('customers.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Όνομα</label>
                    <input type="text" name="first_name" class="form-control"
                           value="{{ old('first_name') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Επίθετο</label>
                    <input type="text" name="last_name" class="form-control"
                           value="{{ old('last_name') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Τηλέφωνο</label>
                    <input type="text" name="phone" class="form-control"
                           value="{{ old('phone') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email (προαιρετικό)</label>
                    <input type="email" name="email" class="form-control"
                           value="{{ old('email') }}">
                </div>

                {{-- ΔΟΥ --}}
                <div class="mb-3">
                    <label class="form-label">ΔΟΥ</label>
                    <input type="text" name="tax_office" class="form-control"
                           value="{{ old('tax_office') }}">
                </div>

                {{-- ΑΦΜ --}}
                <div class="mb-3">
                    <label class="form-label">ΑΦΜ</label>
                    <input type="text" name="vat_number" class="form-control"
                           value="{{ old('vat_number') }}">
                </div>

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

                <button class="btn btn-primary">Αποθήκευση</button>
                <a href="{{ route('customers.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
