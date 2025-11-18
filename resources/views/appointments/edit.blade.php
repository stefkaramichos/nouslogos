@extends('layouts.app')

@section('title', 'Επεξεργασία Ραντεβού #' . $appointment->id)

@section('content')
    <div class="mb-3">
        <a href="{{ route('appointments.index') }}" class="btn btn-secondary btn-sm">← Πίσω στη λίστα ραντεβού</a>
    </div>

    <div class="card">
        <div class="card-header">
            Επεξεργασία Ραντεβού
        </div>
        <div class="card-body">
            <form action="{{ route('appointments.update', $appointment) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- Πελάτης --}}
                <div class="mb-3">
                    <label class="form-label">Πελάτης</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">-- Επιλέξτε πελάτη --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}"
                                @selected(old('customer_id', $appointment->customer_id) == $customer->id)>
                                {{ $customer->last_name }} {{ $customer->first_name }} ({{ $customer->phone }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Επαγγελματίας --}}
                <div class="mb-3">
                    <label class="form-label">Επαγγελματίας</label>
                    <select name="professional_id" class="form-select" required>
                        <option value="">-- Επιλέξτε επαγγελματία --</option>
                        @foreach($professionals as $professional)
                            <option value="{{ $professional->id }}"
                                @selected(old('professional_id', $appointment->professional_id) == $professional->id)>
                                {{ $professional->last_name }} {{ $professional->first_name }} ({{ $professional->phone }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Εταιρεία --}}
                <div class="mb-3">
                    <label class="form-label">Εταιρεία</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- Επιλέξτε εταιρεία --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}"
                                @selected(old('company_id', $appointment->company_id) == $company->id)>
                                {{ $company->name }} ({{ $company->city }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Ημερομηνία & ώρα έναρξης --}}
                <div class="mb-3">
                    <label class="form-label">Ημερομηνία & Ώρα Έναρξης</label>
                    <input
                        type="datetime-local"
                        name="start_time"
                        class="form-control"
                        value="{{ old('start_time', $appointment->start_time ? $appointment->start_time->format('Y-m-d\TH:i') : '') }}"
                        required
                    >
                </div>

                {{-- Ημερομηνία & ώρα λήξης --}}
                <div class="mb-3">
                    <label class="form-label">Ημερομηνία & Ώρα Λήξης (προαιρετικό)</label>
                    <input
                        type="datetime-local"
                        name="end_time"
                        class="form-control"
                        value="{{ old('end_time', $appointment->end_time ? $appointment->end_time->format('Y-m-d\TH:i') : '') }}"
                    >
                </div>

                {{-- Κατάσταση --}}
                <div class="mb-3">
                    <label class="form-label">Κατάσταση</label>
                    <select name="status" class="form-select">
                        @php
                            $status = old('status', $appointment->status);
                        @endphp
                        <option value="scheduled" @selected($status === 'scheduled')>Προγραμματισμένο</option>
                        <option value="completed" @selected($status === 'completed')>Ολοκληρωμένο</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Ακυρωμένο</option>
                        <option value="no_show" @selected($status === 'no_show')>Δεν προσήλθε</option>
                    </select>
                </div>

                {{-- Συνολικό ποσό --}}
                <div class="mb-3">
                    <label class="form-label">Συνολικό Ποσό (€)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="total_price"
                        class="form-control"
                        value="{{ old('total_price', $appointment->total_price) }}"
                    >
                    <small class="text-muted">
                        Αν το αφήσετε κενό, θα χρησιμοποιηθεί η χρέωση του επαγγελματία.
                    </small>
                </div>

                {{-- Σημειώσεις --}}
                <div class="mb-3">
                    <label class="form-label">Σημειώσεις</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $appointment->notes) }}</textarea>
                </div>

                <button class="btn btn-primary">Αποθήκευση Αλλαγών</button>
                <a href="{{ route('appointments.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
