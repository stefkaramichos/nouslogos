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
                    <select name="customer_id" id="customer_select" class="form-select select2" required>
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
                    <select name="professional_id" id="professional_select" class="form-select select2" required>
                        <option value="">-- Επιλέξτε επαγγελματία --</option>
                        @foreach($professionals as $professional)
                            <option
                                value="{{ $professional->id }}"
                                data-role="{{ $professional->role }}"
                                @selected(old('professional_id') == $professional->id)
                            >
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
                <div class="mb-3" id="professional_amount_group">
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(function () {
    // flatpickr για ημερομηνία/ώρα
    flatpickr("#start_time", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        minuteIncrement: 15
    });

    // Select2 init
    $('#customer_select, #professional_select, #company_select, #status_select').select2({
        width: '100%',
        placeholder: '-- Επιλέξτε --',
        allowClear: true
    });

    const lastAppointmentUrl      = "{{ route('customers.lastAppointment') }}";
    const professionalCompanyUrl  = "{{ route('professionals.getCompany') }}";

    function toggleProfessionalAmount() {
        const selectedOption = $('#professional_select').find('option:selected');
        const role = selectedOption.data('role');

        if (role === 'owner') {
            $('#professional_amount_group').show();
        } else {
            $('#professional_amount_group').hide();
            // Optional: καθάρισε το πεδίο όταν δεν είναι owner
            $('#professional_amount_input').val('');
        }
    }

    // Bind change event
    $('#professional_select').on('change', function () {
        toggleProfessionalAmount();
        // (κρατάς και τα δικά σου υπάρχοντα actions εδώ)
    });

    // Αρχική κατάσταση όταν φορτώνει η σελίδα
    toggleProfessionalAmount();

    // === ΕΠΑΓΓΕΛΜΑΤΙΑΣ change -> φέρε εταιρεία ===
    $('#professional_select').on('change', function () {
        const profId = $(this).val();

        if (!profId) {
            $('#company_select').val(null).trigger('change');
            return;
        }

        $.getJSON(professionalCompanyUrl, { professional_id: profId })
            .done(function (data) {
                if (data.found) {
                    $('#company_select').val(data.company_id).trigger('change');
                }
            })
            .fail(function (err) {
                console.error("Σφάλμα στο fetch εταιρείας επαγγελματία:", err);
            });
    });

    // === ΠΕΛΑΤΗΣ change -> φέρε στοιχεία τελευταίου ραντεβού ===
    $('#customer_select').on('change', function () {
        const customerId = $(this).val();

        if (!customerId) {
            return;
        }

        $.getJSON(lastAppointmentUrl, { customer_id: customerId })
            .done(function (data) {
                if (!data.found) {
                    return;
                }

                // Επαγγελματίας (και αυτόματα εταιρεία μέσω change)
                if (data.professional_id) {
                    $('#professional_select')
                        .val(data.professional_id)
                        .trigger('change'); // θα πυροδοτήσει και το event του επαγγελματία
                }

                // Υπηρεσία (status)
                if (data.status) {
                    $('#status_select').val(data.status).trigger('change');
                }

                // Χρέωση ραντεβού
                if (typeof data.total_price !== 'undefined') {
                    $('#total_price_input').val(data.total_price);
                }

                // Ποσό επαγγελματία
                if (typeof data.professional_amount !== 'undefined') {
                    $('#professional_amount_input').val(data.professional_amount);
                }

                // Σημειώσεις (μόνο αν είναι άδειο το πεδίο)
                if (data.notes && !$('#notes_textarea').val()) {
                    $('#notes_textarea').val(data.notes);
                }
            })
            .fail(function (err) {
                console.error('Σφάλμα στο fetch τελευταίου ραντεβού:', err);
            });
    });

    // Αν ήρθες με ήδη επιλεγμένο πελάτη (customer_id στο URL κλπ),
    // κάνε αυτόματα load τα στοιχεία
    if ($('#customer_select').val()) {
        $('#customer_select').trigger('change');
    }
});
</script>
@endpush
