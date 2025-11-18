@extends('layouts.app')

@section('title', 'Πληρωμή Ραντεβού #' . $appointment->id)

@section('content')
    <div class="mb-3">
        <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm">← Πίσω</a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Στοιχεία Ραντεβού
        </div>
        <div class="card-body">
            <p><strong>Ραντεβού #:</strong> {{ $appointment->id }}</p>
            <p><strong>Ημ/νία & Ώρα:</strong> {{ $appointment->start_time?->format('d/m/Y H:i') }}</p>
            <p><strong>Πελάτης:</strong>
                {{ $appointment->customer->last_name ?? '' }}
                {{ $appointment->customer->first_name ?? '' }}
            </p>
            <p><strong>Επαγγελματίας:</strong>
                {{ $appointment->professional->last_name ?? '' }}
                {{ $appointment->professional->first_name ?? '' }}
            </p>
            <p><strong>Εταιρεία:</strong> {{ $appointment->company->name ?? '-' }}</p>
            <p><strong>Συνολικό Ποσό Ραντεβού:</strong>
                {{ number_format($appointment->total_price ?? 0, 2, ',', '.') }} €
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Πληρωμή
        </div>
        <div class="card-body">
            <form action="{{ route('appointments.payment.update', $appointment) }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Ποσό Πληρωμής (€)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="amount"
                        class="form-control"
                        value="{{ old('amount', $payment->amount ?? '') }}"
                    >
                    <small class="text-muted">
                        Αν αφήσετε κενό και επιλέξετε "Πλήρης εξόφληση", θα χρησιμοποιηθεί το συνολικό ποσό ραντεβού.
                        Αν το ποσό είναι 0, η πληρωμή θα διαγραφεί.
                    </small>
                </div>

                <div class="mb-3 form-check">
                    <input
                        type="checkbox"
                        class="form-check-input"
                        id="is_full"
                        name="is_full"
                        value="1"
                        @checked(old('is_full', $payment->is_full ?? false))
                    >
                    <label class="form-check-label" for="is_full">
                        Πλήρης εξόφληση
                    </label>
                </div>

                <div class="mb-3">
                    <label class="form-label">Μέθοδος Πληρωμής</label>
                    <select name="method" class="form-select">
                        <option value="">-- Επιλέξτε --</option>
                        <option value="cash" @selected(old('method', $payment->method ?? '') === 'cash')>
                            Μετρητά
                        </option>
                        <option value="card" @selected(old('method', $payment->method ?? '') === 'card')>
                            Κάρτα
                        </option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Σημειώσεις</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $payment->notes ?? '') }}</textarea>
                </div>

                <button class="btn btn-primary">Αποθήκευση</button>
                <a href="{{ route('appointments.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
