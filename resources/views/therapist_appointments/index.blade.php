@extends('layouts.app')

@section('title', 'Τα ραντεβού μου')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Τα ραντεβού μου</strong>

        <div class="d-flex gap-2">
            {{-- Mobile: κουμπί φίλτρων --}}
            <button type="button" class="btn btn-outline-secondary btn-sm d-md-none" data-bs-toggle="modal" data-bs-target="#filtersModal">
                Φίλτρα
            </button>

            <a href="{{ route('therapist_appointments.create') }}" class="btn btn-primary btn-sm">
                + Νέο Ραντεβού
            </a>
        </div>
    </div>

    <div class="card-body">

        {{-- Quick buttons (desktop) --}}
        <div class="d-none d-md-flex flex-wrap gap-2 mb-3">
            <a class="btn btn-outline-primary btn-sm {{ ($quick ?? request('quick')) === 'today' ? 'active' : '' }}"
               href="{{ route('therapist_appointments.index', array_merge(request()->except('page'), ['quick' => 'today'])) }}">
                Σήμερα
            </a>

            <a class="btn btn-outline-primary btn-sm {{ ($quick ?? request('quick')) === 'tomorrow' ? 'active' : '' }}"
               href="{{ route('therapist_appointments.index', array_merge(request()->except('page'), ['quick' => 'tomorrow'])) }}">
                Αύριο
            </a>

            <a class="btn btn-outline-primary btn-sm {{ ($quick ?? request('quick')) === 'week' ? 'active' : '' }}"
               href="{{ route('therapist_appointments.index', array_merge(request()->except('page'), ['quick' => 'week'])) }}">
                Αυτή την εβδομάδα
            </a>

            <a class="btn btn-outline-primary btn-sm {{ ($quick ?? request('quick')) === 'month' ? 'active' : '' }}"
               href="{{ route('therapist_appointments.index', array_merge(request()->except('page'), ['quick' => 'month'])) }}">
                Αυτόν τον μήνα
            </a>

            <a class="btn btn-outline-secondary btn-sm"
               href="{{ route('therapist_appointments.index') }}">
                Καθαρισμός
            </a>
        </div>

        @php
            $partyType = $partyType ?? request('party_type');
            $partyId   = $partyId ?? request('party_id');
        @endphp

        {{-- Filters (desktop inline) --}}
        <form method="GET" class="row g-3 mb-3 d-none d-md-flex" id="filtersFormDesktop">
            <input type="hidden" name="quick" value="{{ $quick ?? request('quick') }}">

            <div class="col-md-3">
                <label class="form-label">Από</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Έως</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>

            @if($user_role === 'owner')
                {{-- OWNER: unified party filter --}}
                <div class="col-md-3">
                    <label class="form-label">Με (Πελάτης / Επαγγελματίας)</label>

                    <input type="hidden" name="party_type" id="party_type_desktop" value="{{ $partyType }}">
                    <select name="party_id" id="party_select_desktop" class="form-select js-party-select">
                        <option value="">Όλοι</option>

                        <optgroup label="Πελάτες">
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}"
                                    data-type="customer"
                                    {{ ($partyType === 'customer' && (string)$partyId === (string)$c->id) ? 'selected' : '' }}>
                                    {{ $c->last_name }} {{ $c->first_name }}
                                </option>
                            @endforeach
                        </optgroup>

                        <optgroup label="Επαγγελματίες">
                            @foreach($allProfessionalsForParties as $p)
                                <option value="{{ $p->id }}"
                                    data-type="professional"
                                    {{ ($partyType === 'professional' && (string)$partyId === (string)$p->id) ? 'selected' : '' }}>
                                    {{ $p->last_name }} {{ $p->first_name }}
                                </option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Επαγγελματίας</label>
                    <select name="professional_id" class="form-select js-professional-select">
                        <option value="">Όλοι οι επαγγελματίες</option>
                        @foreach($professionals as $p)
                            <option value="{{ $p->id }}"
                                {{ (string)$p->id === (string)($professionalId ?? request('professional_id')) ? 'selected' : '' }}>
                                {{ $p->last_name }} {{ $p->first_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @else
                {{-- NON-OWNER: original customer-only filter --}}
                <div class="col-md-3">
                    <label class="form-label">Πελάτης</label>
                    <select name="customer_id" class="form-select js-customer-select">
                        <option value="">Όλοι οι πελάτες</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}"
                                {{ (string)$c->id === (string)($customerId ?? request('customer_id')) ? 'selected' : '' }}>
                                {{ $c->last_name }} {{ $c->first_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-outline-primary w-100">Φιλτράρισμα</button>
                <a href="{{ route('therapist_appointments.index') }}" class="btn btn-outline-secondary">
                    Καθαρισμός
                </a>
            </div>
        </form>

        {{-- Results --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    @if($user_role === 'owner')
                        <th>Με</th>
                    @else
                        <th>Πελάτης</th>
                    @endif
                    <th>Ημερομηνία & Ώρα</th>
                    <th>Σημειώσεις</th>
                    <th class="text-nowrap">Ενέργειες</th>
                </tr>
                </thead>

                <tbody>
                @forelse($appointments as $a)
                    @php
                        $displayName = '-';
                        if ($a->customer) {
                            $displayName = $a->customer->last_name . ' ' . $a->customer->first_name;
                        } elseif ($user_role === 'owner' && $a->withProfessional) {
                            $displayName = $a->withProfessional->last_name . ' ' . $a->withProfessional->first_name;
                        }

                        $modalWith = $displayName;
                        if ($user_role === 'owner') {
                            if ($a->customer) $modalWith .= ' (Πελάτης)';
                            elseif ($a->withProfessional) $modalWith .= ' (Επαγγελματίας)';
                        }
                    @endphp

                    <tr class="js-appointment-row"
                        data-id="{{ $a->id }}"
                        data-with="{{ $modalWith }}"
                        data-datetime="{{ \Carbon\Carbon::parse($a->start_time)->format('d/m/Y H:i') }}"
                        data-notes="{{ $a->notes }}">
                        <td>{{ $a->id }}</td>

                        <td>
                            @if($a->customer)
                                @if($user_role === 'owner')
                                    <a href="{{ route('customers.show', $a->customer) }}">
                                        {{ $a->customer->last_name }} {{ $a->customer->first_name }}
                                    </a>
                                @else
                                    {{ $a->customer->last_name }} {{ $a->customer->first_name }}
                                @endif
                            @else
                                @if($user_role === 'owner' && $a->withProfessional)
                                    {{ $a->withProfessional->last_name }} {{ $a->withProfessional->first_name }}
                                @else
                                    -
                                @endif
                            @endif
                        </td>

                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($a->start_time)->format('d/m/Y H:i') }}</td>
                        <td title="{{ $a->notes }}">{{ $a->notes ? Str::limit($a->notes, 30) : '-' }}</td>

                        <td class="text-nowrap">
                            <a href="{{ route('therapist_appointments.edit', $a) }}"
                               class="btn btn-sm btn-secondary"
                               title="Επεξεργασία Ραντεβού">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <form method="POST"
                                  action="{{ route('therapist_appointments.destroy', $a) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε;');">
                                @csrf
                                @method('DELETE')

                                <button class="btn btn-sm btn-danger" title="Διαγραφή Ραντεβού">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Δεν υπάρχουν ραντεβού.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-center mt-3">
            {{ $appointments->links() }}
        </div>

    </div>

    <!-- Modal Λεπτομερειών Ραντεβού -->
    <div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Λεπτομέρειες Ραντεβού</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Κωδικός</dt>
                        <dd class="col-sm-9" id="modalAppointmentId"></dd>

                        <dt class="col-sm-3">{{ $user_role === 'owner' ? 'Με' : 'Πελάτης' }}</dt>
                        <dd class="col-sm-9" id="modalAppointmentWith"></dd>

                        <dt class="col-sm-3">Ημερομηνία & Ώρα</dt>
                        <dd class="col-sm-9" id="modalAppointmentDatetime"></dd>

                        <dt class="col-sm-3">Σημειώσεις</dt>
                        <dd class="col-sm-9">
                            <p id="modalAppointmentNotes" class="mb-0" style="white-space: pre-wrap;"></p>
                        </dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Φίλτρων (Mobile) -->
    <div class="modal fade" id="filtersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Φίλτρα Αναζήτησης</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
                </div>

                <div class="modal-body">

                    {{-- Quick buttons (mobile) --}}
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a class="btn btn-outline-primary btn-sm {{ ($quick ?? request('quick')) === 'today' ? 'active' : '' }}"
                           href="{{ route('therapist_appointments.index', array_merge(request()->except('page'), ['quick' => 'today'])) }}">
                            Σήμερα
                        </a>

                        <a class="btn btn-outline-primary btn-sm {{ ($quick ?? request('quick')) === 'tomorrow' ? 'active' : '' }}"
                           href="{{ route('therapist_appointments.index', array_merge(request()->except('page'), ['quick' => 'tomorrow'])) }}">
                            Αύριο
                        </a>

                        <a class="btn btn-outline-primary btn-sm {{ ($quick ?? request('quick')) === 'week' ? 'active' : '' }}"
                           href="{{ route('therapist_appointments.index', array_merge(request()->except('page'), ['quick' => 'week'])) }}">
                            Αυτή την εβδομάδα
                        </a>

                        <a class="btn btn-outline-primary btn-sm {{ ($quick ?? request('quick')) === 'month' ? 'active' : '' }}"
                           href="{{ route('therapist_appointments.index', array_merge(request()->except('page'), ['quick' => 'month'])) }}">
                            Αυτόν τον μήνα
                        </a>

                        <a class="btn btn-outline-secondary btn-sm"
                           href="{{ route('therapist_appointments.index') }}">
                            Καθαρισμός
                        </a>
                    </div>

                    <form method="GET" class="row g-3" id="filtersFormMobile">
                        <input type="hidden" name="quick" value="{{ $quick ?? request('quick') }}">

                        <div class="col-12">
                            <label class="form-label">Από</label>
                            <input type="date" name="from" value="{{ $from }}" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Έως</label>
                            <input type="date" name="to" value="{{ $to }}" class="form-control">
                        </div>

                        @if($user_role === 'owner')
                            <input type="hidden" name="party_type" id="party_type_mobile" value="{{ $partyType }}">

                            <div class="col-12">
                                <label class="form-label">Με (Πελάτης / Επαγγελματίας)</label>
                                <select name="party_id" id="party_select_mobile" class="form-select js-party-select-modal">
                                    <option value="">Όλοι</option>

                                    <optgroup label="Πελάτες">
                                        @foreach($customers as $c)
                                            <option value="{{ $c->id }}"
                                                data-type="customer"
                                                {{ ($partyType === 'customer' && (string)$partyId === (string)$c->id) ? 'selected' : '' }}>
                                                {{ $c->last_name }} {{ $c->first_name }}
                                            </option>
                                        @endforeach
                                    </optgroup>

                                    <optgroup label="Επαγγελματίες">
                                        @foreach($allProfessionalsForParties as $p)
                                            <option value="{{ $p->id }}"
                                                data-type="professional"
                                                {{ ($partyType === 'professional' && (string)$partyId === (string)$p->id) ? 'selected' : '' }}>
                                                {{ $p->last_name }} {{ $p->first_name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Επαγγελματίας</label>
                                <select name="professional_id" class="form-select js-professional-select-modal">
                                    <option value="">Όλοι οι επαγγελματίες</option>
                                    @foreach($professionals as $p)
                                        <option value="{{ $p->id }}"
                                            {{ (string)$p->id === (string)($professionalId ?? request('professional_id')) ? 'selected' : '' }}>
                                            {{ $p->last_name }} {{ $p->first_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="col-12">
                                <label class="form-label">Πελάτης</label>
                                <select name="customer_id" class="form-select js-customer-select-modal">
                                    <option value="">Όλοι οι πελάτες</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}"
                                            {{ (string)$c->id === (string)($customerId ?? request('customer_id')) ? 'selected' : '' }}>
                                            {{ $c->last_name }} {{ $c->first_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
                    <button type="button" class="btn btn-primary" id="applyMobileFiltersBtn">Εφαρμογή</button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(function () {

        const isOwner = @json($user_role === 'owner');

        function syncPartyType(selectEl, hiddenTypeEl) {
            const opt = $(selectEl).find('option:selected');
            const t = opt.data('type') || '';
            $(hiddenTypeEl).val(t);
        }

        if (isOwner) {
            // Desktop: party
            $('.js-party-select').select2({
                placeholder: 'Όλοι',
                allowClear: true,
                width: '100%',
                language: { noResults: function () { return 'Δεν βρέθηκαν αποτελέσματα'; } }
            }).on('change', function () {
                syncPartyType('#party_select_desktop', '#party_type_desktop');
            });

            // Owner professional filter
            $('.js-professional-select').select2({
                placeholder: 'Όλοι οι επαγγελματίες',
                allowClear: true,
                width: '100%',
                language: { noResults: function () { return 'Δεν βρέθηκαν αποτελέσματα'; } }
            });
        } else {
            // Non-owner: customer only
            $('.js-customer-select').select2({
                placeholder: 'Όλοι οι πελάτες',
                allowClear: true,
                width: '100%',
                language: { noResults: function () { return 'Δεν βρέθηκαν αποτελέσματα'; } }
            });
        }

        $('#filtersModal').on('shown.bs.modal', function () {
            if (isOwner) {
                $('.js-party-select-modal').select2({
                    placeholder: 'Όλοι',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#filtersModal'),
                    language: { noResults: function () { return 'Δεν βρέθηκαν αποτελέσματα'; } }
                }).on('change', function () {
                    syncPartyType('#party_select_mobile', '#party_type_mobile');
                });

                $('.js-professional-select-modal').select2({
                    placeholder: 'Όλοι οι επαγγελματίες',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#filtersModal'),
                    language: { noResults: function () { return 'Δεν βρέθηκαν αποτελέσματα'; } }
                });

            } else {
                $('.js-customer-select-modal').select2({
                    placeholder: 'Όλοι οι πελάτες',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#filtersModal'),
                    language: { noResults: function () { return 'Δεν βρέθηκαν αποτελέσματα'; } }
                });
            }
        });

        // Apply mobile filters
        $('#applyMobileFiltersBtn').on('click', function () {
            $('#filtersFormMobile').submit();
        });

        // Row click: details modal
        $('.js-appointment-row').on('click', function (e) {
            if ($(e.target).closest('a, button, i, form').length) {
                return;
            }

            const id       = $(this).data('id');
            const withName = $(this).data('with');
            const datetime = $(this).data('datetime');
            const notes    = $(this).data('notes') || '-';

            $('#modalAppointmentId').text(id);
            $('#modalAppointmentWith').text(withName);
            $('#modalAppointmentDatetime').text(datetime);
            $('#modalAppointmentNotes').text(notes);

            const modalEl = document.getElementById('appointmentModal');
            const modal   = new bootstrap.Modal(modalEl);
            modal.show();
        });

        // Initial sync on load
        if (isOwner) {
            syncPartyType('#party_select_desktop', '#party_type_desktop');
            syncPartyType('#party_select_mobile', '#party_type_mobile');
        }
    });
</script>
@endpush
