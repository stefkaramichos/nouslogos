@extends('layouts.app')

@section('title', 'Πελάτες')

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">

                <span>Λίστα Πελατών</span>

                <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm ms-3">
                    + Προσθήκη Πελάτη
                </a>
            </div>

            {{-- Search bar --}}
            <form method="GET" action="{{ route('customers.index') }}" class="mt-3">
                <div class="input-group">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Αναζήτηση (όνομα, τηλέφωνο, email, εταιρεία)..."
                           value="{{ $search ?? '' }}">

                    <button class="btn btn-outline-primary">
                        Αναζήτηση
                    </button>

                    @if(isset($search) && $search !== '')
                        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                            Καθαρισμός
                        </a>
                    @endif
                </div>
            </form>

        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Ονοματεπώνυμο</th>
                        <th>Τηλέφωνο</th>
                        <th>Email</th>
                        <th>ΑΦΜ</th>
                        <th>ΔΟΥ</th>
                        <th>Εταιρεία</th>
                        <th>Πληροφορίες</th>
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td>{{ $customer->id }}</td>

                            <td>
                                <a href="{{ route('customers.show', $customer) }}">
                                    {{ $customer->last_name }} {{ $customer->first_name }}
                                </a>
                            </td>

                            <td>{{ $customer->phone }}</td>

                            <td>{{ $customer->email ?? '-' }}</td>

                            <td>{{ $customer->vat_number ?? '-' }}</td>

                            <td>{{ $customer->tax_office ?? '-' }}</td>

                            <td>{{ $customer->company->name ?? '-' }}</td>

                            <td>{{ Str::limit($customer->informations, 20) ?? '-' }}</td>

                            <td>
                                {{-- View --}}
                                <a href="{{ route('appointments.create', ['customer_id' => $customer->id, 'redirect' => request()->fullUrl()]) }}"
                                    class="btn btn-sm btn-info text-white" τιτλε="Προσθήκη Ραντεβού">
                                        +
                                    </a>

                                {{-- Edit --}}
                                <a href="{{ route('customers.edit', $customer) }}"
                                   class="btn btn-sm btn-secondary"
                                   title="Επεξεργασία πελάτη">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                {{-- Delete --}}
                                <form action="{{ route('customers.destroy', $customer) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτόν τον πελάτη;');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger"
                                            title="Διαγραφή πελάτη">
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
    </div>
@endsection
