@extends('layouts.app')

@section('title', 'Επαγγελματίες')

@section('content')
    <div class="card">
        <div class="card-header">

            <div class="d-flex justify-content-between align-items-center">
                <span>Λίστα Επαγγελματιών</span>

                <a href="{{ route('professionals.create') }}" class="btn btn-primary btn-sm ms-3">
                    + Προσθήκη Επαγγελματία
                </a>
            </div>

            {{-- Search bar --}}
            <form method="GET" action="{{ route('professionals.index') }}" class="mt-3">
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
                        <a href="{{ route('professionals.index') }}" class="btn btn-outline-secondary">
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
                        <th>Μισθός</th>
                        <th>Εταιρεία</th>
                        {{-- <th>Χρέωση (€)</th>
                        <th>Ποσό Επαγγελματία</th> --}}
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($professionals as $professional)
                        <tr>
                            <td>{{ $professional->id }}</td>
                            <td>
                                @if($professional->role != 'grammatia') <a href="{{ route('professionals.show', $professional) }}"> @endif
                                    {{ $professional->last_name }} {{ $professional->first_name }}
                                @if($professional->role != 'grammatia')</a> @endif
                            </td>

                            <td>{{ $professional->phone }}</td>
                            <td>{{ $professional->email ?? '-' }}</td>
                            <td>
                                @if(!is_null($professional->salary))
                                    {{ number_format($professional->salary, 2, ',', '.') }} €
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @forelse($professional->companies as $company)
                                    <span class="badge bg-primary">{{ $company->name }}</span>
                                @empty
                                    -
                                @endforelse
                            </td>

                            {{-- <td>{{ number_format($professional->service_fee, 2, ',', '.') }}</td>
                            <td>{{ number_format($professional->percentage_cut, 2, ',', '.') }}</td> --}}
                            <td>
                                <!-- Edit -->
                                <a href="{{ route('professionals.edit', $professional) }}"
                                class="btn btn-sm btn-secondary"
                                title="Επεξεργασία επαγγελματία">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                {{-- Παλιά διαγραφή – κρατάω σχολιασμένη --}}
{{--                                 
                                <form action="{{ route('professionals.destroy', $professional) }}"
                                    method="POST"
                                    class="d-inline"
                                    onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτόν τον επαγγελματία;');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger"
                                            title="Διαγραφή επαγγελματία">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                --}}

                              
                          @if(auth()->user()->role === 'owner' && $professional->role != 'owner')
                            <!-- Toggle ενεργός / ανενεργός -->
                            <form action="{{ route('professionals.toggle-active', $professional) }}"
                                method="POST"
                                class="d-inline">
                                @csrf
                                @method('PATCH')

                                @if($professional->is_active)
                                    <button class="btn btn-sm btn-success"
                                            title="Απενεργοποίηση επαγγελματία">
                                        <i class="bi bi-toggle-on"></i>
                                    </button>
                                @else
                                    <button class="btn btn-sm btn-outline-secondary"
                                            title="Ενεργοποίηση επαγγελματία">
                                        <i class="bi bi-toggle-off"></i>
                                    </button>
                                @endif
                            </form>
                        @endif

                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Δεν υπάρχουν επαγγελματίες για εμφάνιση.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
