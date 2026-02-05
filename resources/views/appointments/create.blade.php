@extends('layouts.app')

@section('title', 'Νέο Ραντεβού (Πολλαπλά)')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Νέο Ραντεβού (πολλαπλά)</span>

        <button type="button" id="addRowBtn" class="btn btn-sm btn-outline-primary">
            + Προσθήκη γραμμής
        </button>
    </div>

    <div class="card-body">
        <form action="{{ route('appointments.storeMultiple') }}" method="POST">
            @csrf
            
            <input type="hidden" name="redirect_to" value="{{ request('redirect') ?? url()->previous() }}">

            {{-- ΠΕΛΑΤΗΣ --}}
            <div class="mb-3">
                <label class="form-label">Περιστατικό</label>
                <select name="customer_id" id="customer_select" class="form-select select2" required>
                    <option value="">-- Επιλέξτε περιστατικό --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}"
                            @selected(old('customer_id', request('customer_id')) == $customer->id)>
                            {{ $customer->last_name }} {{ $customer->first_name }} ({{ $customer->phone }})
                        </option>
                    @endforeach
                </select>
            </div>

            <hr>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Γραμμές Ραντεβού</h6>
                <small class="text-muted">
                    Μπορείς να δημιουργήσεις πολλές γραμμές, κάθε γραμμή μπορεί να έχει δικό της θεραπευτή/εταιρεία/υπηρεσία/ημερομηνία κλπ.
                </small>
            </div>

            <div id="rowsContainer"></div>

            <div class="mt-3 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-success">
                    Αποθήκευση όλων
                </button>
                <a href="{{ route('appointments.index') }}" class="btn btn-secondary">
                    Ακύρωση
                </a>
            </div>
        </form>
    </div>
</div>

{{-- TEMPLATE (ΜΗΝ βάλεις άλλο row έξω από εδώ) --}}
<template id="rowTemplate">
    <div class="border rounded p-3 mb-3 appointment-row" data-index="__INDEX__">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="row-title">Ραντεβού #__NUM__</strong>

            <button type="button" class="btn btn-sm btn-outline-danger removeRowBtn">
                Αφαίρεση
            </button>
        </div>

        <div class="row g-2">
            {{-- professional --}}
            <div class="col-md-4">
                <label class="form-label">Θεραπευτής</label>
                <select name="rows[__INDEX__][professional_id]" class="form-select select2 professional_select" required>
                    <option value="">-- Επιλέξτε --</option>
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}" data-role="{{ $professional->role }}">
                            {{ $professional->last_name }} {{ $professional->first_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- company --}}
            <div class="col-md-4">
                <label class="form-label">Εταιρεία</label>
                <select name="rows[__INDEX__][company_id]" class="form-select select2 company_select" required>
                    <option value="">-- Επιλέξτε --</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">
                            {{ $company->name }} ({{ $company->city }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- start_time --}}
            <div class="col-md-4">
                <label class="form-label">Ημερομηνία & Ώρα Έναρξης</label>
                <input type="text"
                       name="rows[__INDEX__][start_time]"
                       class="form-control start_time_input"
                       required>
            </div>

            {{-- weeks --}}
            <div class="col-md-3">
                <label class="form-label">Επανάληψη (εβδομάδες)</label>
                <select name="rows[__INDEX__][weeks]" class="form-select">
                    @for ($i = 1; $i <= 52; $i++)
                        <option value="{{ $i }}" @selected($i === 1)>{{ $i }} εβδομάδα{{ $i > 1 ? 'ς' : '' }}</option>
                    @endfor
                </select>
                <small class="text-muted">1 = μόνο αυτή η ημερομηνία</small>
            </div>

            {{-- status --}}
            <div class="col-md-6">
                <label class="form-label">Υπηρεσία</label>
                <select name="rows[__INDEX__][status][]" class="form-select select2 status_select" multiple>
                    <option value="logotherapia">Λογοθεραπεία</option>
                    <option value="psixotherapia">Ψυχοθεραπεία</option>
                    <option value="ergotherapia">Εργοθεραπεία</option>
                    <option value="omadiki">Ομαδική</option>
                    <option value="eidikos">Ειδικός παιδαγωγός</option>
                    <option value="aksiologisi">Αξιολόγηση / Τηλ. Επικοινωνία / Ενημερωτικό</option>
                </select>
                <small class="text-muted">Μπορείς να επιλέξεις πάνω από μία.</small>
            </div>

            {{-- total --}}
            <div class="col-md-3">
                <label class="form-label">Χρέωση (€)</label>
                <input type="number" step="0.01" min="0"
                       name="rows[__INDEX__][total_price]"
                       class="form-control total_price_input"
                       placeholder="π.χ. 35.00">
            </div>

            {{-- professional amount (conditional) --}}
            <div class="col-md-3 professional_amount_group" style="display:none;">
                <label class="form-label">Ποσό Θεραπευτή (€)</label>
                <input type="number" step="0.01" min="0"
                       name="rows[__INDEX__][professional_amount]"
                       class="form-control professional_amount_input">
            </div>

            {{-- notes --}}
            <div class="col-12">
                <label class="form-label">Σημειώσεις</label>
                <textarea name="rows[__INDEX__][notes]" class="form-control notes_textarea" rows="2"></textarea>
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
$(function () {
    // ✅ guard να μην τρέξει 2 φορές
    if (window.__multiAppointmentInit) return;
    window.__multiAppointmentInit = true;

    const professionalCompanyUrl = "{{ route('professionals.getCompany') }}";
    const lastAppointmentUrl     = "{{ route('customers.lastAppointment') }}";

    let rowIndex = 0;

    function initSelect2($scope) {
        $scope.find('.select2').select2({
            width: '100%',
            placeholder: '-- Επιλέξτε --',
            allowClear: true
        });
    }

    function initFlatpickr($scope) {
        $scope.find('.start_time_input').each(function () {
            flatpickr(this, {
                enableTime: true,
                time_24hr: true,
                dateFormat: "d-m-Y H:i",
                minuteIncrement: 15,
                locale: {
                    ...flatpickr.l10ns.el,
                    firstDayOfWeek: 1
                }
            });
        });
    }

    function renumberRows() {
        $('#rowsContainer .appointment-row').each(function(i){
            $(this).find('.row-title').text('Ραντεβού #' + (i + 1));
        });
    }

    function shouldShowProfessionalAmount($professionalSelect) {
        const selected = $professionalSelect.find('option:selected');
        const role = selected.data('role');
        const id = parseInt($professionalSelect.val(), 10);
        return (role === 'owner' || id === 17);
    }

    function toggleProfessionalAmount($row) {
        const $prof = $row.find('.professional_select');
        const show = shouldShowProfessionalAmount($prof);

        const $group = $row.find('.professional_amount_group');
        const $input = $row.find('.professional_amount_input');

        if (show) {
            $group.show();
        } else {
            $group.hide();
            $input.val('');
        }
    }

    function autoFillCompany($row) {
        const profId = $row.find('.professional_select').val();
        if (!profId) {
            $row.find('.company_select').val(null).trigger('change');
            return;
        }

        $.getJSON(professionalCompanyUrl, { professional_id: profId })
            .done(function (data) {
                if (data && data.found) {
                    $row.find('.company_select').val(data.company_id).trigger('change');
                }
            })
            .fail(function (err) {
                console.error("Σφάλμα στο fetch εταιρείας επαγγελματία:", err);
            });
    }

    function getFirstRow() {
        return $('#rowsContainer .appointment-row').first();
    }

    function addRow() {
        const tpl = document.getElementById('rowTemplate').innerHTML
            .replaceAll('__INDEX__', String(rowIndex))
            .replaceAll('__NUM__', String(rowIndex + 1));

        const $row = $(tpl);
        $('#rowsContainer').append($row);

        initSelect2($row);
        initFlatpickr($row);

        $row.on('change', '.professional_select', function () {
            toggleProfessionalAmount($row);
            autoFillCompany($row);
        });

        toggleProfessionalAmount($row);

        rowIndex++;
        renumberRows();
        return $row;
    }

    // init customer select2
    $('#customer_select').select2({ width: '100%', placeholder: '-- Επιλέξτε --', allowClear: true });

    // ✅ ΜΟΝΟ 1 αρχική γραμμή
    addRow();

    $('#addRowBtn').on('click', function () {
        addRow();
    });

    $('#rowsContainer').on('click', '.removeRowBtn', function () {
        $(this).closest('.appointment-row').remove();
        renumberRows();
    });

    // ✅ auto-complete από τελευταίο ραντεβού πελάτη
    $('#customer_select').on('change', function () {
        const customerId = $(this).val();
        if (!customerId) return;

        const $row = getFirstRow();
        if (!$row.length) return;

        $.getJSON(lastAppointmentUrl, { customer_id: customerId })
            .done(function (data) {
                if (!data || !data.found) return;

                if (data.professional_id) {
                    $row.find('.professional_select')
                        .val(String(data.professional_id))
                        .trigger('change');
                }

                if (data.status) {
                    const arr = String(data.status).split(',').filter(Boolean);
                    $row.find('.status_select').val(arr).trigger('change');
                }

                if (typeof data.total_price !== 'undefined' && data.total_price !== null) {
                    $row.find('.total_price_input').val(data.total_price);
                }

                if (typeof data.professional_amount !== 'undefined' && data.professional_amount !== null) {
                    $row.find('.professional_amount_input').val(data.professional_amount);
                }

                if (data.notes && !$row.find('.notes_textarea').val()) {
                    $row.find('.notes_textarea').val(data.notes);
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
