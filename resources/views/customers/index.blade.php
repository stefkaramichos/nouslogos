{{-- resources/views/customers/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Περιστατικά')

@section('content')
    @php
        $search = $search ?? request('search');

        // session-aware
        $selectedCompany = $companyId ?? request('company_id');

        // ✅ active filter (1 / 0 / all)
        $activeFilter = $activeFilter ?? request('active', '1');
        if (!in_array((string)$activeFilter, ['1','0','all'], true)) {
            $activeFilter = '1';
        }
    @endphp

    <style>
            tr.row-flash > td {
                animation: flashRow 2.5s ease-in-out;
            }

            @keyframes flashRow {
                0%   { background-color: rgba(255, 230, 150, 0.95); }
                100% { background-color: transparent; }
            }
    </style>

    <div class="card">
        <div class="card-header">
            <div class="d-flex  align-items-center flex-wrap row">
                <div class="col-md-8">
                    <span>Λίστα Περιστατικών</span>
                </div>
            
                <div class="d-flex justify-content-end gap-2 col-md-4">
                    <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#printOptionsModal">
                            🖨 Εκτύπωση Λίστας
                    </button>
                    <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
                        + Προσθήκη Περιστατικού
                    </a>
                </div>
            </div>

            {{-- Search bar --}}
            <form method="GET" action="{{ route('customers.index') }}" class="mt-3">
                {{-- keep filters while searching --}}
                <input type="hidden" name="company_id" value="{{ $selectedCompany }}">
                <input type="hidden" name="active" value="{{ $activeFilter }}">

                <div class="input-group">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Αναζήτηση (όνομα, τηλέφωνο, email, εταιρεία)..."
                           value="{{ $search ?? '' }}">

                    <button class="btn btn-outline-primary">
                        Αναζήτηση
                    </button>

                    @if((isset($search) && $search !== '') || request('company_id') || request('active'))
                        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                            Καθαρισμός
                        </a>
                    @endif
                </div>
            </form>

            {{-- Quick filter buttons by company --}}
            <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('customers.index', [
                        'search' => request('search'),
                        'clear_company' => 1,
                        'active' => $activeFilter,
                    ]) }}"
                   class="btn btn-sm {{ empty($selectedCompany) ? 'btn-primary' : 'btn-outline-primary' }}">
                    Όλοι
                </a>

                @foreach(($companies ?? collect()) as $company)
                    <div class="btn-group" role="group">
                        <a href="{{ route('customers.index', [
                                'search' => request('search'),
                                'company_id' => $company->id,
                                'active' => $activeFilter,
                            ]) }}"
                        class="btn btn-sm {{ (string)$selectedCompany === (string)$company->id ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $company->name }}
                        </a>

                        {{-- delete button (opens modal) --}}
                        @if($company->can_delete)
                            <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                title="Διαγραφή εταιρείας"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteCompanyModal"
                                data-company-id="{{ $company->id }}"
                                data-company-name="{{ $company->name }}">
                                🗑
                            </button>
                        @endif
                    </div>
                @endforeach

                {{-- + Add Company button --}}
                <button type="button"
                        class="btn btn-sm btn-outline-success"
                        data-bs-toggle="modal"
                        data-bs-target="#createCompanyModal">
                    + Τοποθεσία/Σχολείο
                </button>

            </div>

            {{-- ✅ Active filter buttons (SAME COLORS as companies) --}}
            <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('customers.index', [
                        'search' => request('search'),
                        'company_id' => $selectedCompany,
                        'active' => 'all',
                    ]) }}"
                   class="btn btn-sm {{ (string)$activeFilter === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
                    ΟΛΑ
                </a>
                <a href="{{ route('customers.index', [
                        'search' => request('search'),
                        'company_id' => $selectedCompany,
                        'active' => '1',
                    ]) }}"
                   class="btn btn-sm {{ (string)$activeFilter === '1' ? 'btn-primary' : 'btn-outline-primary' }}">
                    ΕΝΕΡΓΟΙ
                </a>

                <a href="{{ route('customers.index', [
                        'search' => request('search'),
                        'company_id' => $selectedCompany,
                        'active' => '0',
                    ]) }}"
                   class="btn btn-sm {{ (string)$activeFilter === '0' ? 'btn-primary' : 'btn-outline-primary' }}">
                    ΑΝΕΝΕΡΓΟΙ
                </a>

            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th class="text-center">✓</th>
                        <th>Ονοματεπώνυμο</th>
                        <th>Τηλέφωνο</th>
                        {{-- <th>Θεραπευτές</th> --}}
                        <th>Πληροφορίες</th>
                        <th>Αποδείξεις (ΟΧΙ ΚΟΜΜΕΝΕΣ)</th>
                        <th class="text-center">Κατάσταση</th>
                        <th class="text-end">Ενέργειες</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($customers as $customer)
                        @php
                            $isActive = (int)($customer->is_active ?? 1) === 1;
                             $isCompleted = (int)($customer->completed ?? 0) === 1;
                        @endphp

                        <tr
                            class="{{ !$isActive ? 'text-muted' : '' }} {{ $isCompleted ? 'completed-row' : '' }}"
                            @if(!$isActive) style="opacity:0.65;" @endif id="customer_row_{{ $customer->id }}"
                        >
                                                    {{-- <td>{{ $customer->company->name ?? '-' }}</td> --}}

                            <td class="text-center">
                                <form method="POST" action="{{ route('customers.toggleCompleted', $customer) }}" class="d-inline">
                                    @csrf

                                    <div class="form-check m-0 d-inline-flex align-items-center justify-content-center">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            id="cust_completed_{{ $customer->id }}"
                                            {{ $isCompleted ? 'checked' : '' }}
                                            onchange="
                                                this.form.querySelector('input[name=completed]').value = this.checked ? 1 : 0;
                                                this.form.submit();
                                            ">
                                        <input type="hidden" name="completed" value="{{ $isCompleted ? 1 : 0 }}">
                                    </div>
                                </form>
                            </td>


                            <td>
                                <a href="{{ route('customers.show', $customer) }}"
                                   style="text-decoration: none; color: inherit;">
                                    {{ $customer->last_name }} {{ $customer->first_name }}
                                </a>
                                @if(!$isActive)
                                    <div class="small text-danger">Απενεργοποιημένος</div>
                                @endif
                            </td>

                            <td>{{ $customer->phone ?? '-' }}</td>
{{-- 
                            <td>
                                @php $pros = $customer->professionals ?? collect(); @endphp

                                @if($pros->isEmpty())
                                    <span class="text-muted">-</span>
                                @else
                                    {{ $pros->map(fn($p) => trim(($p->last_name ?? '').' '.($p->first_name ?? '')))->implode(', ') }}
                                @endif
                            </td> --}}

                            <td>
                                @if($customer->informations)
                                    <span title="{{ $customer->informations }}" style="cursor: help;">
                                        {{ Str::limit($customer->informations, 30) }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>
                                    @php
                                        $unissued = $customer->receipts ?? collect(); // ήδη φορτωμένες ΜΟΝΟ is_issued=0
                                        $unissuedCount = $unissued->count();
                                        $unissuedSum = (float)$unissued->sum('amount');
                                    @endphp

                                    @if($unissuedCount === 0)
                                        <span class="text-muted">-</span>
                                    @else
                                        <div class="d-flex flex-column" style="line-height:1.1;">
                                                 <small class="text-muted">
                                                {{-- δείξε μέχρι 2-3 σχόλια για “preview” --}}
                                                @foreach($unissued->take(2) as $r)
                                                    {{ \Illuminate\Support\Str::limit($r->comment ?? 'χωρίς σχόλιο', 100) }}@if(!$loop->last), @endif <br>
                                                @endforeach
                                                @if($unissuedCount > 2)
                                                    …
                                                @endif
                                            </small><br>
                                            {{-- <small class="text-muted">
                                                {{ number_format($unissuedSum, 2, ',', '.') }} €
                                            </small> --}}
                                            <span class="badge bg-warning text-dark align-self-start">
                                                {{ $unissuedCount }} ΟΧΙ
                                            </span>

                                       
                                        </div>
                                    @endif
                                </td>


                            {{-- ✅ SWITCH enable/disable --}}
                            <td class="text-center">
                                <form method="POST"
                                      action="{{ route('customers.toggleActive', $customer) }}"
                                      class="d-inline">
                                    @csrf

                                    <div class="form-check form-switch d-inline-flex align-items-center justify-content-center m-0">
                                        <input class="form-check-input customer-active-switch"
                                               type="checkbox"
                                               role="switch"
                                               id="cust_active_{{ $customer->id }}"
                                               {{ $isActive ? 'checked' : '' }}
                                               onchange="
                                                   if(!this.checked){
                                                       if(!confirm('Σίγουρα θέλετε να απενεργοποιήσετε το περιστατικό;')){
                                                           this.checked = true;
                                                           return;
                                                       }
                                                   }
                                                   this.form.querySelector('input[name=is_active]').value = this.checked ? 1 : 0;
                                                   this.form.submit();
                                               ">
                                        <input type="hidden" name="is_active" value="{{ $isActive ? 1 : 0 }}">
                                    </div>
                                </form>
                            </td>
                            @php
                                $baseRedirect = request()->fullUrl();
                                $sep = str_contains($baseRedirect, '?') ? '&' : '?';
                                $redirectWithFlash = $baseRedirect . $sep . 'flash_row=customer_row_' . $customer->id;
                            @endphp

                            <td class="text-end">
                                                            {{-- Add Appointment --}}
                                <a href="{{ route('appointments.create', [
                                    'customer_id' => $customer->id,
                                    'redirect' => $redirectWithFlash,
                                ]) }}" class="btn btn-sm btn-success text-white">+</a>

                                <a href="{{ route('customers.edit', [
                                    'customer' => $customer,
                                    'redirect' => $redirectWithFlash,
                                ]) }}" class="btn btn-sm btn-secondary">
                                    <i class="bi bi-pencil-square"></i>
                                </a>



                                {{-- Delete (kept disabled like before) --}}
                                
                                <form action="{{ route('customers.destroy', $customer) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτόν τον πελάτη;');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" title="Διαγραφή πελάτη">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                               
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Δεν υπάρχουν πελάτες για εμφάνιση.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>

                </table>
            </div>
        </div>

        {{-- Pagination (enable if you use paginate() in controller) --}}
        {{--
        <div class="card-footer">
            {{ $customers->appends(request()->query())->links() }}
        </div>
        --}}
    </div>
        {{-- Create Company Modal --}}
    <div class="modal fade" id="createCompanyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
            <form method="POST" action="{{ route('companies.store', request()->query()) }}">
                @csrf

                <div class="modal-header">
                <h5 class="modal-title">Νέα Εταιρεία</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Όνομα *</label>
                    <input type="text" name="name" class="form-control" required
                        value="{{ old('name') }}">
                    @error('name')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Πόλη</label>
                    <input type="text" name="city" class="form-control"
                        value="{{ old('city') }}">
                    @error('city')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                </div>

                <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Άκυρο</button>
                <button type="submit" class="btn btn-success">Αποθήκευση</button>
                </div>

            </form>
            </div>
        </div>
    </div>

    {{-- Delete Company Modal --}}
    <div class="modal fade" id="deleteCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <form id="deleteCompanyForm" method="POST" action="">
            @csrf
            @method('DELETE')

            <div class="modal-header">
            <h5 class="modal-title">Διαγραφή Εταιρείας</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
            <div class="alert alert-warning mb-0">
                Θέλετε σίγουρα να διαγράψετε την εταιρεία
                <strong id="deleteCompanyName">-</strong>;
                <div class="small mt-2">
                Η διαγραφή επιτρέπεται μόνο αν δεν υπάρχουν σχετικές εγγραφές (ραντεβού/πελάτες/έξοδα/εκκαθαρίσεις/θεραπευτές).
                </div>
            </div>
            </div>

            <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Άκυρο</button>
            <button type="submit" class="btn btn-danger">Διαγραφή</button>
            </div>
        </form>
        </div>
    </div>
    </div>

    {{-- Print Options Modal --}}
    <div class="modal fade" id="printOptionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Επιλογή Πεδίων Εκτύπωσης</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3">Επιλέξτε ποια πεδία θέλετε να εμφανιστούν στην εκτύπωση:</p>
                
                <div class="form-check">
                    <input class="form-check-input print-field" type="checkbox" id="field_name" value="name" checked>
                    <label class="form-check-label" for="field_name">
                        Ονοματεπώνυμο
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input print-field" type="checkbox" id="field_phone" value="phone" checked>
                    <label class="form-check-label" for="field_phone">
                        Τηλέφωνο
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input print-field" type="checkbox" id="field_company" value="company">
                    <label class="form-check-label" for="field_company">
                        Εταιρεία/Τοποθεσία
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input print-field" type="checkbox" id="field_informations" value="informations">
                    <label class="form-check-label" for="field_informations">
                        Πληροφορίες
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input print-field" type="checkbox" id="field_professionals" value="professionals">
                    <label class="form-check-label" for="field_professionals">
                        Θεραπευτές
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input print-field" type="checkbox" id="field_status" value="status">
                    <label class="form-check-label" for="field_status">
                        Κατάσταση (Ενεργός/Απενεργοποιημένος)
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input print-field" type="checkbox" id="field_unissued_receipts" value="unissued_receipts">
                    <label class="form-check-label" for="field_unissued_receipts">
                        Αποδείξεις (Όχι Κομμένες)
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Άκυρο</button>
                <button type="button" class="btn btn-primary" id="printBtn">🖨 Εκτύπωση</button>
            </div>
        </div>
    </div>
    </div>


@endsection
@push('scripts')
<script>
window.addEventListener("load", function () {
    const url = new URL(window.location.href);
    const rowId = url.searchParams.get("flash_row");
    if (!rowId) return;

    const row = document.getElementById(rowId);
    if (!row) return;

    row.scrollIntoView({ behavior: "smooth", block: "center" });
    row.classList.add("row-flash");

    setTimeout(() => row.classList.remove("row-flash"), 2500);

    url.searchParams.delete("flash_row");
    window.history.replaceState({}, document.title, url.toString());
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('deleteCompanyModal');
    if (!modalEl) return;

    modalEl.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const id = btn.getAttribute('data-company-id');
        const name = btn.getAttribute('data-company-name');

        document.getElementById('deleteCompanyName').textContent = name || '-';

        // build action url from route template
        const action = "{{ route('companies.destroy', ['company' => '__ID__']) }}".replace('__ID__', id);
        document.getElementById('deleteCompanyForm').action = action;
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const printBtn = document.getElementById('printBtn');
    
    if (!printBtn) return;

    printBtn.addEventListener('click', function () {
        // Συλλέγουμε τα επιλεγμένα πεδία
        const checkedFields = Array.from(document.querySelectorAll('.print-field:checked'))
            .map(checkbox => checkbox.value)
            .join(',');

        // Παίρνουμε τα τρέχοντα query parameters
        const url = new URL('{{ route("customers.print") }}', window.location.origin);
        
        // Διατηρούμε τα υπάρχοντα filters
        const currentParams = new URLSearchParams(window.location.search);
        for (const [key, value] of currentParams) {
            url.searchParams.append(key, value);
        }

        // Προσθέτουμε τα επιλεγμένα πεδία
        url.searchParams.set('print_fields', checkedFields);

        // Ανοίγουμε το url σε νέο παράθυρο
        window.open(url.toString(), '_blank');

        // Κλείνουμε το modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('printOptionsModal'));
        if (modal) {
            modal.hide();
        }
    });
});
</script>

@endpush
