@extends('layouts.app')

@section('title', 'Επεξεργασία Πελάτη')

@section('content')
    <div class="card">
        <div class="card-header">
            Επεξεργασία Πελάτη
        </div>
        <div class="card-body">
            <form action="{{ route('customers.update', $customer) }}" method="POST">
                @csrf
                @method('PUT')

                <input type="hidden" name="redirect_to" value="{{ $redirect }}">
                
                <div class="mb-3">
                    <label class="form-label">Όνομα</label>
                    <input
                        type="text"
                        name="first_name"
                        class="form-control"
                        value="{{ old('first_name', $customer->first_name) }}"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Επίθετο</label>
                    <input
                        type="text"
                        name="last_name"
                        class="form-control"
                        value="{{ old('last_name', $customer->last_name) }}"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Τηλέφωνο</label>
                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="{{ old('phone', $customer->phone) }}"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Email (προαιρετικό)</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="{{ old('email', $customer->email) }}"
                    >
                </div>
                <div class="mb-3">
                <label class="form-label">ΑΦΜ</label>
                <input
                    type="text"
                    name="vat_number"
                    class="form-control"
                    value="{{ old('vat_number', $customer->vat_number) }}"
                    
                >
            </div>

            <div class="mb-3">
                <label class="form-label">ΔΟΥ</label>
                <input
                    type="text"
                    name="tax_office"
                    class="form-control"
                    value="{{ old('tax_office', $customer->tax_office) }}"
                    
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Πληροφορίες</label>
                <textarea
                    name="informations"
                    class="form-control"
                    rows="3"
                >{{ old('informations', $customer->informations) }}</textarea>
            </div>



                <div class="mb-3">
                    <label class="form-label">Εταιρεία</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- Επιλέξτε εταιρεία --</option>
                        @foreach($companies as $company)
                            <option
                                value="{{ $company->id }}"
                                @selected(old('company_id', $customer->company_id) == $company->id)
                            >
                                {{ $company->name }} ({{ $company->city }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <button class="btn btn-primary">Αποθήκευση Αλλαγών</button>
                <a href="{{ route('customers.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
