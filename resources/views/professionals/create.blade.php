@extends('layouts.app')

@section('title', 'Νέος Επαγγελματίας')

@section('content')
    <div class="card">
        <div class="card-header">
            Νέος Επαγγελματίας
        </div>
        <div class="card-body">
            <form action="{{ route('professionals.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Όνομα</label>
                    <input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Επίθετο</label>
                    <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Τηλέφωνο</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email (προαιρετικό)</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}">
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

                <div class="mb-3">
                    <label class="form-label">Χρέωση Υπηρεσίας (€)</label>
                    <input type="number" step="0.01" name="service_fee" class="form-control"
                           value="{{ old('service_fee') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ποσοστό που παίρνει ο επαγγελματίας (%)</label>
                    <input type="number" step="0.01" name="percentage_cut" class="form-control"
                           value="{{ old('percentage_cut') }}" required>
                </div>

                <button class="btn btn-primary">Αποθήκευση</button>
                <a href="{{ route('professionals.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
