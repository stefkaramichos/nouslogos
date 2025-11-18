@extends('layouts.app')

@section('title', 'Επαγγελματίες')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Λίστα Επαγγελματιών</span>
            <a href="{{ route('professionals.create') }}" class="btn btn-primary btn-sm">
                + Προσθήκη Επαγγελματία
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
                        <th>Χρέωση Υπηρεσίας (€)</th>
                        <th>% Ποσοστό</th>
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($professionals as $professional)
                        <tr>
                            <td>{{ $professional->id }}</td>
                            <td>
                                <a href="{{ route('professionals.show', $professional) }}">
                                    {{ $professional->last_name }} {{ $professional->first_name }}
                                </a>
                            </td>

                            <td>{{ $professional->phone }}</td>
                            <td>{{ $professional->email ?? '-' }}</td>
                            <td>{{ $professional->company->name ?? '-' }}</td>
                            <td>{{ number_format($professional->service_fee, 2, ',', '.') }}</td>
                            <td>{{ number_format($professional->percentage_cut, 2, ',', '.') }}%</td>
                            <td>
                                <a href="{{ route('professionals.edit', $professional) }}" class="btn btn-sm btn-secondary">
                                    Επεξεργασία
                                </a>
                                <form action="{{ route('professionals.destroy', $professional) }}" method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτόν τον επαγγελματία;');">
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
                            <td colspan="8" class="text-center text-muted py-4">
                                Δεν υπάρχουν επαγγελματίες ακόμα.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
