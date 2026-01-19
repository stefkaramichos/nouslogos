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
            <p><strong>Περιστατικό:</strong>
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

                <input type="hidden" name="redirect_to" value="{{ request('redirect') }}">

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
                    <select name="method" class="form-select" id="payment_method">
                        <option value="">-- Επιλέξτε --</option>
                        <option value="cash" @selected(old('method', $payment->method ?? '') === 'cash')>
                            Μετρητά
                        </option>
                        <option value="card" @selected(old('method', $payment->method ?? '') === 'card')>
                            Κάρτα
                        </option>
                    </select>
                </div>

                {{-- TAX --}}
                <div class="mb-3" id="tax-wrapper">
                    <label class="form-label">ΦΠΑ / Απόδειξη</label>
                    @php
                        $currentTax = old('tax', $payment->tax ?? 'N');
                    @endphp
                    <select name="tax" class="form-select" id="tax_select">
                        <option value="">-- Επιλέξτε --</option>
                        <option value="Y" @selected($currentTax === 'Y')>Ναι</option>
                        <option value="N" @selected($currentTax === 'N')>Όχι</option>
                    </select>
                    <small class="text-muted" id="tax_help">
                        Αν επιλέξετε Κάρτα, ο ΦΠΑ θα είναι πάντα "Ναι".
                    </small>
                </div>

                {{-- Αν θες σημειώσεις, ξεκλείδωσέ το --}}
                {{-- 
                <div class="mb-3">
                    <label class="form-label">Σημειώσεις</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $payment->notes ?? '') }}</textarea>
                </div>
                --}}

                <button class="btn btn-primary">Αποθήκευση</button>
                <a href="{{ route('appointments.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>

    {{-- Μικρό script για να κρύβει/δείχνει το tax ανάλογα με τη μέθοδο --}}
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const methodSelect = document.getElementById('payment_method');
                const taxWrapper   = document.getElementById('tax-wrapper');
                const taxSelect    = document.getElementById('tax_select');
                const taxHelp      = document.getElementById('tax_help');

                function updateTaxUI() {
                    const method = methodSelect.value;

                    if (method === 'card') {
                        // Κρύβουμε το πεδίο, θέτουμε tax = Y
                        taxWrapper.classList.add('d-none');
                        if (taxSelect) {
                            taxSelect.value = 'Y';
                        }
                        taxHelp.textContent = 'Πληρωμή με κάρτα: ο ΦΠΑ ορίζεται αυτόματα σε "Ναι".';
                    } else if (method === 'cash') {
                        // Δείχνουμε το πεδίο και αφήνουμε τον χρήστη να επιλέξει
                        taxWrapper.classList.remove('d-none');
                        taxHelp.textContent = 'Επιλέξτε αν θα κοπεί απόδειξη (ΦΠΑ) για πληρωμή με μετρητά.';
                    } else {
                        // Καμία μέθοδος επιλεγμένη
                        taxWrapper.classList.remove('d-none');
                        taxHelp.textContent = 'Επιλέξτε μέθοδο πληρωμής και στη συνέχεια ΦΠΑ.';
                    }
                }

                if (methodSelect) {
                    methodSelect.addEventListener('change', updateTaxUI);
                    updateTaxUI();
                }
            });
        </script>
    @endpush
@endsection
