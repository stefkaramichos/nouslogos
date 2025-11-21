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

                {{-- ΠΕΛΑΤΗΣ --}}
               <div class="mb-3">
                    <label class="form-label">Πελάτης</label>
                    <select name="customer_id" id="customer_select" class="form-select" required>
                        <option value="">-- Επιλέξτε πελάτη --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}"
                                @selected(old('customer_id', request('customer_id')) == $customer->id)>
                                {{ $customer->last_name }} {{ $customer->first_name }} ({{ $customer->phone }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- ΕΠΑΓΓΕΛΜΑΤΙΑΣ --}}
                <div class="mb-3">
                    <label class="form-label">Επαγγελματίας</label>
                    <select name="professional_id" id="professional_select" class="form-select" required>
                        <option value="">-- Επιλέξτε επαγγελματία --</option>
                        @foreach($professionals as $professional)
                            <option value="{{ $professional->id }}" @selected(old('professional_id') == $professional->id)>
                                {{ $professional->last_name }} {{ $professional->first_name }} ({{ $professional->phone }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- ΕΤΑΙΡΕΙΑ --}}
                <div class="mb-3">
                    <label class="form-label">Εταιρεία</label>
                    <select name="company_id" id="company_select" class="form-select" required>
                        <option value="">-- Επιλέξτε εταιρεία --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>
                                {{ $company->name }} ({{ $company->city }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- ΗΜ/ΝΙΑ & ΩΡΑ --}}
                <div class="mb-3">
                    <label class="form-label">Ημερομηνία & Ώρα Έναρξης</label>
                    <input id="start_time" type="text" name="start_time" class="form-control"
                           value="{{ old('start_time') }}" required>
                </div>
                <input type="hidden" name="redirect_to" value="{{ request('redirect') }}">
                {{-- ΥΠΗΡΕΣΙΑ (status) --}}
                <div class="mb-3">
                    <label class="form-label">Υπηρεσία</label>
                    @php $oldStatus = old('status', 'logotherapia'); @endphp
                    <select name="status" id="status_select" class="form-select">
                        <option value="logotherapia"  @selected($oldStatus === 'logotherapia')>Λογοθεραπεία</option>
                        <option value="psixotherapia" @selected($oldStatus === 'psixotherapia')>Ψυχοθεραπεία</option>
                        <option value="ergotherapia"  @selected($oldStatus === 'ergotherapia')>Εργοθεραπεία</option>
                        <option value="omadiki"       @selected($oldStatus === 'omadiki')>Ομαδική</option>
                        <option value="eidikos"       @selected($oldStatus === 'eidikos')>Ειδικός παιδαγωγός</option>
                    </select>
                </div>

                {{-- ΧΡΕΩΣΗ ΡΑΝΤΕΒΟΥ --}}
                <div class="mb-3">
                    <label class="form-label">Χρέωση Ραντεβού (€)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="total_price"
                        id="total_price_input"
                        class="form-control"
                        value="{{ old('total_price') }}"
                    >
                </div>

                {{-- ΠΟΣΟ ΕΠΑΓΓΕΛΜΑΤΙΑ --}}
                <div class="mb-3">
                    <label class="form-label">Ποσό Επαγγελματία (€)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="professional_amount"
                        id="professional_amount_input"
                        class="form-control"
                        value="{{ old('professional_amount') }}"
                    >
                    <small class="text-muted">
                        {{-- Αν μείνει κενό, θα χρησιμοποιηθεί το ποσό από το προφίλ του επαγγελματία (ή ο αυτόματος υπολογισμός). --}}
                    </small>
                </div>

                {{-- ΣΗΜΕΙΩΣΕΙΣ --}}
                <div class="mb-3">
                    <label class="form-label">Σημειώσεις</label>
                    <textarea name="notes" id="notes_textarea" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>

                <button class="btn btn-primary">Αποθήκευση</button>
                <a href="{{ route('appointments.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection



@push('scripts')
<script>
    // flatpickr για ημερομηνία/ώρα
    flatpickr("#start_time", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        minuteIncrement: 15
    });

    document.addEventListener('DOMContentLoaded', function () {
        const customerSelect          = document.getElementById('customer_select');
        const professionalSelect      = document.getElementById('professional_select');
        const companySelect           = document.getElementById('company_select');
        const statusSelect            = document.getElementById('status_select');
        const totalPriceInput         = document.getElementById('total_price_input');
        const professionalAmountInput = document.getElementById('professional_amount_input');
        const notesTextarea           = document.getElementById('notes_textarea');

        const lastAppointmentUrl = "{{ route('customers.lastAppointment') }}";

        const professionalCompanyUrl = "{{ route('professionals.getCompany') }}";

        if (professionalSelect) {
            professionalSelect.addEventListener('change', function () {
                const profId = this.value;

                if (!profId) {
                    companySelect.value = "";
                    return;
                }

                fetch(professionalCompanyUrl + "?professional_id=" + profId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.found) {
                            companySelect.value = data.company_id;
                        }
                    })
                    .catch(err => console.error("Σφάλμα στο fetch εταιρείας επαγγελματία:", err));
            });
        }


        if (customerSelect) {
            customerSelect.addEventListener('change', function () {
                const customerId = this.value;

                if (!customerId) {
                    // Αν καθαρίσει ο πελάτης, δεν κάνουμε τίποτα (ή καθαρίζουμε πεδία αν θες)
                    return;
                }

                fetch(lastAppointmentUrl + '?customer_id=' + encodeURIComponent(customerId))
                    .then(response => response.json())
                    .then(data => {
                        if (!data.found) {
                            // Δεν υπάρχει προηγούμενο ραντεβού → δεν πειράζουμε τίποτα
                            return;
                        }

                        // Επαγγελματίας
                        if (data.professional_id && professionalSelect) {
                            professionalSelect.value = data.professional_id;
                        }

                        if (data.professional_id && professionalSelect) {
                            professionalSelect.value = data.professional_id;

                            // μόλις βάλουμε επαγγελματία, 
                            // αφήνουμε το event change να φέρει την εταιρεία του
                            professionalSelect.dispatchEvent(new Event('change'));
                        }

                        // Υπηρεσία (status)
                        if (data.status && statusSelect) {
                            statusSelect.value = data.status;
                        }

                        // Χρέωση ραντεβού
                        if (typeof data.total_price !== 'undefined' && totalPriceInput) {
                            totalPriceInput.value = data.total_price;
                        }

                        // Ποσό επαγγελματία
                        if (typeof data.professional_amount !== 'undefined' && professionalAmountInput) {
                            professionalAmountInput.value = data.professional_amount;
                        }

                        // Σημειώσεις (αν θες να τις φέρνεις)
                        if (data.notes && notesTextarea && !notesTextarea.value) {
                            notesTextarea.value = data.notes;
                        }
                    })
                    .catch(err => {
                        console.error('Σφάλμα στο fetch τελευταίου ραντεβού:', err);
                    });
            });
        }
    });
</script>
@endpush


@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {

    const customerSelect = document.getElementById('customer_select');

    // Αν ήρθες από το κουμπί "Προσθήκη Ραντεβού" στη σελίδα του πελάτη
    // και υπάρχει customer_id στο URL, κάνε αυτόματα load τα στοιχεία
    if (customerSelect && customerSelect.value) {
        customerSelect.dispatchEvent(new Event('change'));
    }
    

});
</script>
@endpush
