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

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Λίστα Περιστατικών</span>

                <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
                    + Προσθήκη Περιστατικού
                </a>
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
                    <a href="{{ route('customers.index', [
                            'search' => request('search'),
                            'company_id' => $company->id,
                            'active' => $activeFilter,
                        ]) }}"
                       class="btn btn-sm {{ (string)$selectedCompany === (string)$company->id ? 'btn-primary' : 'btn-outline-primary' }}">
                        {{ $company->name }}
                    </a>
                @endforeach
            </div>

            {{-- ✅ Active filter buttons (SAME COLORS as companies) --}}
            <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
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

                {{-- <a href="{{ route('customers.index', [
                        'search' => request('search'),
                        'company_id' => $selectedCompany,
                        'active' => 'all',
                    ]) }}"
                   class="btn btn-sm {{ (string)$activeFilter === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
                    ΟΛΑ
                </a> --}}
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Γραφείο</th>
                        <th>Ονοματεπώνυμο</th>
                        <th>Τηλέφωνο</th>
                        <th>Θεραπευτές</th>
                        <th>Πληροφορίες</th>
                        <th class="text-center">Κατάσταση</th>
                        <th class="text-end">Ενέργειες</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($customers as $customer)
                        @php
                            $isActive = (int)($customer->is_active ?? 1) === 1;
                        @endphp

                        <tr @if(!$isActive) class="text-muted" style="opacity:0.65;" @endif>
                            <td>{{ $customer->company->name ?? '-' }}</td>

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

                            <td>
                                @php $pros = $customer->professionals ?? collect(); @endphp

                                @if($pros->isEmpty())
                                    <span class="text-muted">-</span>
                                @else
                                    {{ $pros->map(fn($p) => trim(($p->last_name ?? '').' '.($p->first_name ?? '')))->implode(', ') }}
                                @endif
                            </td>

                            <td>
                                @if($customer->informations)
                                    <span title="{{ $customer->informations }}" style="cursor: help;">
                                        {{ Str::limit($customer->informations, 30) }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
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

                            <td class="text-end">
                                {{-- Add Appointment --}}
                                <a href="{{ route('appointments.create', ['customer_id' => $customer->id, 'redirect' => request()->fullUrl()]) }}"
                                   class="btn btn-sm btn-success text-white"
                                   title="Προσθήκη Ραντεβού">
                                    +
                                </a>

                                {{-- Edit --}}
                                <a href="{{ route('customers.edit', ['customer' => $customer, 'redirect' => request()->fullUrl()]) }}"
                                   class="btn btn-sm btn-secondary"
                                   title="Επεξεργασία περιστατικού">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                {{-- Delete (kept disabled like before) --}}
                                {{--
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
                                --}}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
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
@endsection
