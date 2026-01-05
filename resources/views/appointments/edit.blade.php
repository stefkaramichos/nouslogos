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

                <input type="hidden" name="redirect_to" value="{{ request('redirect') }}">

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
                    <select name="professional_id" id="professional_select" class="form-select" required>
                        <option value="">-- Επιλέξτε επαγγελματία --</option>
                        @foreach($professionals as $professional)
                            <option
                                value="{{ $professional->id }}"
                                data-role="{{ $professional->role }}"
                                @selected(old('professional_id', $appointment->professional_id) == $professional->id)
                            >
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
                        type="text"
                        name="start_time" id="start_time" 
                        class="form-control"
                        value="{{ old('start_time', $appointment->start_time ? $appointment->start_time->format('Y-m-d\TH:i') : '') }}"
                        required
                    >
                </div>

                {{-- Ημερομηνία & ώρα λήξης --}}
                {{-- <div class="mb-3">
                    <label class="form-label">Ημερομηνία & Ώρα Λήξης (προαιρετικό)</label>
                    <input
                        type="datetime-local"
                        name="end_time"
                        class="form-control"
                        value="{{ old('end_time', $appointment->end_time ? $appointment->end_time->format('Y-m-d\TH:i') : '') }}"
                    >
                </div> --}}

                {{-- Κατάσταση --}}
                <div class="mb-3">
                    <label class="form-label">Υπηρεσία</label>
                    <select name="status" class="form-select">
                        @php
                            $status = old('status', $appointment->status);
                        @endphp
                        <option value="logotherapia" @selected($status === 'logotherapia')>Λογοθεραπεία</option>
                        <option value="psixotherapia" @selected($status === 'psixotherapia')>Ψυχοθεραπεία</option>
                        <option value="ergotherapia" @selected($status === 'ergotherapia')>Εργοθεραπεία</option>
                        <option value="omadiki" @selected($status === 'omadiki')>Ομαδική</option>
                        <option value="eidikos" @selected($status === 'eidikos')>Ειδικός παιδαγωγός</option>
                        <option value="aksiologisi" @selected($status === 'aksiologisi')>Αξιολόγηση</option>
                    </select>
                </div>

                {{-- Συνολικό ποσό --}}
                <div class="mb-3">
                    <label class="form-label">Χρέωση Ραντεβού (€)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="total_price"
                        class="form-control"
                        value="{{ old('total_price', $appointment->total_price) }}"
                    >
                </div>

                <div class="mb-3" id="professional_amount_group">
                    <label class="form-label">Ποσό Επαγγελματία (€)</label>
                    <input type="number" step="0.01" name="professional_amount"
                        class="form-control"
                        value="{{ old('professional_amount', $appointment->professional_amount ?? null) }}">
                    <small class="text-muted">
                        Αν μείνει κενό, θα χρησιμοποιηθεί το ποσό από το προφίλ του επαγγελματία.
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
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // flatpickr init
        flatpickr("#start_time", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minuteIncrement: 15
        });

        function toggleProfessionalAmount() {
            const select  = document.getElementById('professional_select');
            const group   = document.getElementById('professional_amount_group');
            const input   = document.querySelector('input[name="professional_amount"]');

            if (!select || !group) return;

            const selectedOption = select.options[select.selectedIndex];
            const role = selectedOption ? selectedOption.getAttribute('data-role') : null;

            if (role === 'owner') {
                group.style.display = ''; // show
            } else {
                group.style.display = 'none'; // hide
                if (input) {
                    input.value = ''; // καθαρισμός τιμής
                }
            }
        }

        const professionalSelect = document.getElementById('professional_select');
        if (professionalSelect) {
            professionalSelect.addEventListener('change', toggleProfessionalAmount);
        }

        // αρχική κατάσταση (τρέχον επαγγελματίας ραντεβού)
        toggleProfessionalAmount();
    });
</script>
@endpush
