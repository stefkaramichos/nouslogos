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
                    <label class="form-label">Θεραπευτής</label>
                    <select name="professional_id" id="professional_select" class="form-select select2" required>
                        <option value="">-- Επιλέξτε Θεραπευτή --</option>
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
                    <select name="company_id" id="company_select" class="form-select select2" required>
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

                {{-- ΠΟΣΕΣ ΕΒΔΟΜΑΔΕΣ (ΕΠΑΝΑΛΗΨΗ) --}}
                <div class="mb-3">
                    <label class="form-label">Επανάληψη ανά εβδομάδα</label>
                    <select name="weeks" id="weeks_select" class="form-select">
                        @for ($i = 1; $i <= 52; $i++)
                            <option value="{{ $i }}" @selected(old('weeks', 1) == $i)>
                                {{ $i }} εβδομάδα{{ $i > 1 ? 'ς' : '' }}
                            </option>
                        @endfor
                    </select>
                    <small class="text-muted">
                        Πόσες εβδομάδες να δημιουργηθεί το ίδιο ραντεβού (ανά 7 ημέρες). Προεπιλογή: 1 εβδομάδα.
                    </small>
                </div>

                {{-- ΥΠΗΡΕΣΙΑ (status) - ΠΟΛΛΑΠΛΟ --}}
                @php
                    $oldStatuses = old('status', []); // array
                @endphp
                <div class="mb-3">
                    <label class="form-label">Υπηρεσία</label>
                    <select name="status[]" id="status_select" class="form-select select2" multiple>
                        <option value="logotherapia"  @selected(in_array('logotherapia', $oldStatuses))>Λογοθεραπεία</option>
                        <option value="psixotherapia" @selected(in_array('psixotherapia', $oldStatuses))>Ψυχοθεραπεία</option>
                        <option value="ergotherapia"  @selected(in_array('ergotherapia', $oldStatuses))>Εργοθεραπεία</option>
                        <option value="omadiki"       @selected(in_array('omadiki', $oldStatuses))>Ομαδική</option>
                        <option value="eidikos"       @selected(in_array('eidikos', $oldStatuses))>Ειδικός παιδαγωγός</option>
                        <option value="aksiologisi"   @selected(in_array('aksiologisi', $oldStatuses))>Αξιολόγηση</option>
                    </select>
                    <small class="text-muted">
                        Μπορείς να επιλέξεις πάνω από μία υπηρεσίες.
                    </small>
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
                    <label class="form-label">Ποσό Θεραπευτή (€)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="professional_amount"
                        id="professional_amount_input"
                        class="form-control"
                        value="{{ old('professional_amount') }}"
                    >
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
$(function () {
    // flatpickr
    flatpickr("#start_time", {
        enableTime: true,
        time_24hr: true,
        dateFormat: "d-m-Y H:i",
        minuteIncrement: 15,
        locale: {
            ...flatpickr.l10ns.el,
            firstDayOfWeek: 1
        }
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
        const professionalId = parseInt(selectedOption.val(), 10);

        if (role === 'owner' || professionalId === 17) {
            $('#professional_amount_group').show();
        } else {
            $('#professional_amount_group').hide();
            $('#professional_amount_input').val('');
        }
    }

    $('#professional_select').on('change', function () {
        toggleProfessionalAmount();
    });

    toggleProfessionalAmount();

    // ΕΠΑΓΓΕΛΜΑΤΙΑΣ -> εταιρεία
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

    // ΠΕΛΑΤΗΣ -> τελευταία στοιχεία
    $('#customer_select').on('change', function () {
        const customerId = $(this).val();
        if (!customerId) return;

        $.getJSON(lastAppointmentUrl, { customer_id: customerId })
            .done(function (data) {
                if (!data.found) return;

                if (data.professional_id) {
                    $('#professional_select').val(data.professional_id).trigger('change');
                }

                // status (σε νέο multi select): αν το API γυρνά string, το κάνουμε array
                if (data.status) {
                    const arr = String(data.status).split(',').filter(Boolean);
                    $('#status_select').val(arr).trigger('change');
                }

                if (typeof data.total_price !== 'undefined') {
                    $('#total_price_input').val(data.total_price);
                }

                if (typeof data.professional_amount !== 'undefined') {
                    $('#professional_amount_input').val(data.professional_amount);
                }

                if (data.notes && !$('#notes_textarea').val()) {
                    $('#notes_textarea').val(data.notes);
                }
            })
            .fail(function (err) {
                console.error('Σφάλμα στο fetch τελευταίου ραντεβού:', err);
            });
    });

    if ($('#customer_select').val()) {
        $('#customer_select').trigger('change');
    }
});
</script>
@endpush
