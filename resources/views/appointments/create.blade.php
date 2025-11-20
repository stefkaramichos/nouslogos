@extends('layouts.app')

@section('title', 'Νέο Ραντεβού')

@section('content')
    <div class="card">
        <div class="card-header">
            Νέο Ραντεβού
        </div>
        <div class="card-body">
            <form action="{{ route('appointments.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Πελάτης</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">-- Επιλέξτε πελάτη --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>
                                {{ $customer->last_name }} {{ $customer->first_name }} ({{ $customer->phone }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Επαγγελματίας</label>
                    <select name="professional_id" class="form-select" required>
                        <option value="">-- Επιλέξτε επαγγελματία --</option>
                        @foreach($professionals as $professional)
                            <option value="{{ $professional->id }}" @selected(old('professional_id') == $professional->id)>
                                {{ $professional->last_name }} {{ $professional->first_name }} ({{ $professional->phone }})
                            </option>
                        @endforeach
                    </select>
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
                    <label class="form-label">Ημερομηνία & Ώρα Έναρξης</label>
                    <input id="start_time" type="text" name="start_time" class="form-control"
                           value="{{ old('start_time') }}" required>
                </div>

                {{-- <div class="mb-3">
                    <label class="form-label">Ημερομηνία & Ώρα Λήξης (προαιρετικό)</label>
                    <input type="datetime-local" name="end_time" class="form-control"
                           value="{{ old('end_time') }}">
                </div> --}}

                <div class="mb-3">
                    <label class="form-label">Κατάσταση</label>
                    <select name="status" class="form-select">
                        <option value="logotherapia" >Λογοθεραπεία</option>
                        <option value="psixotherapia" >Ψυχοθεραπεία</option>
                        <option value="ergotherapia" >Εργοθεραπεία</option>
                        <option value="omadiki" >Ομαδική</option>
                        <option value="eidikos" >Ειδικός παιδαγωγός</option>
                    </select>
                </div>

                  <div class="mb-3">
                    <label class="form-label">Χρέωση Ραντεβού (€)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="total_price"
                        class="form-control"
                    >
                    <small class="text-muted">
                        Αν το αφήσετε κενό, θα χρησιμοποιηθεί η χρέωση του επαγγελματία.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Σημειώσεις</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox"
                        class="form-check-input"
                        id="mark_as_paid"
                        name="mark_as_paid"
                        value="1"
                        @checked(old('mark_as_paid'))>
                    <label class="form-check-label" for="mark_as_paid">
                        Ο πελάτης πλήρωσε (ολικά ή μερικώς) για αυτό το ραντεβού
                    </label>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Ποσό Πληρωμής (€)
                        <small class="text-muted d-block">
                            Αν το αφήσετε κενό και τσεκάρετε πληρωμή, θα θεωρηθεί ότι πληρώθηκε όλο το ποσό.
                        </small>
                    </label>
                    <input type="number"
                        step="0.01"
                        name="payment_amount"
                        class="form-control"
                        value="{{ old('payment_amount') }}">
                </div>

                <button class="btn btn-primary">Αποθήκευση</button>
                <a href="{{ route('appointments.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection


@push('scripts')
<script>
    flatpickr("#start_time", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        minuteIncrement: 15
    });
</script>
@endpush
