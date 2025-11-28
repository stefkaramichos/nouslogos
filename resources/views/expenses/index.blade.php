@extends('layouts.app')

@section('title', 'Έξοδα')

@section('content')
    @php use Illuminate\Support\Str; @endphp

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <span>Λίστα Εξόδων</span>

                <a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm ms-3">
                    + Προσθήκη Εξόδου
                </a>
            </div>

            {{-- Φίλτρο εταιρείας --}}
            <form method="GET" action="{{ route('expenses.index') }}" class="mt-3">
                <div class="input-group">
                    <select name="company_id" class="form-select">
                        <option value="">Όλες οι εταιρείες</option>
                        @foreach($companies as $company)
                            <option
                                value="{{ $company->id }}"
                                @selected(($selectedCompanyId ?? '') == $company->id)
                            >
                                {{ $company->name }} ({{ $company->city }})
                            </option>
                        @endforeach
                    </select>

                    <button class="btn btn-outline-primary">
                        Φιλτράρισμα
                    </button>

                    @if(!empty($selectedCompanyId))
                        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
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
                        <th>Εταιρεία</th>
                        <th>Ποσό</th>
                        <th>Περιγραφή</th>
                        <th>Ημερομηνία</th>
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($expenses as $expense)
                        <tr>
                            <td>{{ $expense->id }}</td>
                            <td>{{ $expense->company->name ?? '-' }}</td>
                            <td>{{ number_format($expense->amount, 2) }} €</td>
                            <td>{{ $expense->description ? Str::limit($expense->description, 40) : '-' }}</td>
                            <td>{{ optional($expense->created_at)->format('d/m/Y H:i') }}</td>

                            <td>
                                {{-- Edit --}}
                                <a href="{{ route('expenses.edit', $expense) }}"
                                   class="btn btn-sm btn-secondary"
                                   title="Επεξεργασία εξόδου">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                {{-- Delete --}}
                                <form action="{{ route('expenses.destroy', $expense) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτό το έξοδο;');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger"
                                            title="Διαγραφή εξόδου">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Δεν υπάρχουν έξοδα για εμφάνιση.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>

                </table>
            </div>
        </div>

        <div class="card-footer">
            {{ $expenses->links() }}
        </div>
    </div>
@endsection
