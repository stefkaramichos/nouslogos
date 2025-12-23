{{-- resources/views/customers/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Πελάτες')

@section('content')
    @php
        $search = $search ?? request('search');
        $selectedCompany = $companyId ?? request('company_id');  // ✅ session-aware
    @endphp

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Λίστα Πελατών</span>

                <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
                    + Προσθήκη Πελάτη
                </a>
            </div>

            {{-- Search bar --}}
            <form method="GET" action="{{ route('customers.index') }}" class="mt-3">
                {{-- keep company filter while searching --}}
                <input type="hidden" name="company_id" value="{{ $selectedCompany }}">


                <div class="input-group">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Αναζήτηση (όνομα, τηλέφωνο, email, εταιρεία)..."
                           value="{{ $search ?? '' }}">

                    <button class="btn btn-outline-primary">
                        Αναζήτηση
                    </button>

                    @if((isset($search) && $search !== '') || request('company_id'))
                        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                            Καθαρισμός
                        </a>
                    @endif
                </div>
            </form>

            {{-- Quick search buttons by company --}}
            <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
             
                <a href="{{ route('customers.index', [
                        'search' => request('search'),
                        'clear_company' => 1,
                    ]) }}"
                class="btn btn-sm {{ empty($selectedCompany) ? 'btn-primary' : 'btn-outline-primary' }}">
                    Όλοι
                </a>


                {{-- Companies --}}
                @foreach(($companies ?? collect()) as $company)
                    <a href="{{ route('customers.index', array_filter([
                            'search' => request('search'),
                            'company_id' => $company->id,
                        ])) }}"
                       class="btn btn-sm {{ (string)$selectedCompany === (string)$company->id ? 'btn-primary' : 'btn-outline-primary' }}">
                        {{ $company->name }}
                    </a>
                @endforeach
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
                        <th class="text-end">Ενέργειες</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td>{{ $customer->company->name ?? '-' }}</td>

                            <td>
                                <a href="{{ route('customers.show', $customer) }}"
                                   style="text-decoration: none; color: inherit;">
                                    {{ $customer->last_name }} {{ $customer->first_name }}
                                </a>
                            </td>

                            <td>{{ $customer->phone ?? '-' }}</td>

                            <td>
                                @php
                                    $pros = $customer->professionals ?? collect();
                                @endphp

                                @if($pros->isEmpty())
                                    <span class="text-muted">-</span>
                                @else
                                    {{-- show as comma separated --}}
                                    {{ $pros->map(fn($p) => trim(($p->last_name ?? '').' '.($p->first_name ?? '')))->implode(', ') }}
                                @endif
                            </td>

                            <td>
                                @if($customer->informations)
                                    <span
                                        title="{{ $customer->informations }}"
                                        style="cursor: help;"
                                    >
                                        {{ Str::limit($customer->informations, 30) }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                {{-- Add Appointment --}}
                                <a href="{{ route('appointments.create', ['customer_id' => $customer->id, 'redirect' => request()->fullUrl()]) }}"
                                   class="btn btn-sm btn-success text-white"
                                   title="Προσθήκη Ραντεβού">
                                    +
                                </a>

                                {{-- Edit --}}
                                <a href="{{ route('customers.edit', $customer) }}"
                                   class="btn btn-sm btn-secondary"
                                   title="Επεξεργασία πελάτη">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                {{-- Delete (disabled as in your original) --}}
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
                            <td colspan="6" class="text-center text-muted py-4">
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
