@extends('layouts.app')

@section('title', 'Πελάτες')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Λίστα Πελατών</span>
            <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
                + Προσθήκη Πελάτη
            </a>
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
                        <th>Εταιρεία</th>
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td>{{ $customer->id }}</td>
                            <td>{{ $customer->last_name }} {{ $customer->first_name }}</td>
                            <td>{{ $customer->phone }}</td>
                            <td>{{ $customer->email ?? '-' }}</td>
                            <td>{{ $customer->company->name ?? '-' }}</td>
                            <td>
                                 <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-info">
                                    Προβολή
                                </a>
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-secondary">
                                    Επεξεργασία
                                </a>
                                <form action="{{ route('customers.destroy', $customer) }}" method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτόν τον πελάτη;');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">
                                        Διαγραφή
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Δεν υπάρχουν πελάτες ακόμα.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
