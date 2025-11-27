@extends('layouts.app')

@section('title', 'Τα ραντεβού μου')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Τα ραντεβού μου</strong>

        <a href="{{ route('therapist_appointments.create') }}" class="btn btn-primary btn-sm">
            + Νέο Ραντεβού
        </a>
    </div>

    <div class="card-body">

        {{-- Filters --}}
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Από</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Έως</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Πελάτης</label>
                <select name="customer_id"
                        class="form-select js-customer-select">
                    <option value="">Όλοι οι πελάτες</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}"
                            {{ (string)$c->id === (string)($customerId ?? request('customer_id')) ? 'selected' : '' }}>
                            {{ $c->last_name }} {{ $c->first_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if($user->role === 'owner')
                <div class="col-md-3">
                    <label class="form-label">Επαγγελματίας</label>
                    <select name="professional_id"
                            class="form-select js-professional-select">
                        <option value="">Όλοι οι επαγγελματίες</option>
                        @foreach($professionals as $p)
                            <option value="{{ $p->id }}"
                                {{ (string)$p->id === (string)($professionalId ?? request('professional_id')) ? 'selected' : '' }}>
                                {{ $p->last_name }} {{ $p->first_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-outline-primary w-100">Φιλτράρισμα</button>
                <a href="{{ route('therapist_appointments.index') }}"
                class="btn btn-outline-secondary">Καθαρισμός</a>
            </div>
        </form>



        {{-- Results --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Πελάτης</th>
                    <th>Ημερομηνία & Ώρα</th>
                    <th>Σημειώσεις</th>
                    <th>Ενέργειες</th>
                </tr>
                </thead>

                <tbody>
                @forelse($appointments as $a)
                    <tr>
                        <td>{{ $a->id }}</td>
                        <td>{{ $a->customer->last_name }} {{ $a->customer->first_name }}</td>
                        <td>{{ \Carbon\Carbon::parse($a->start_time)->format('d/m/Y H:i') }}</td>
                        <td>{{ $a->notes ?: '-' }}</td>

                        <td>
                            <a href="{{ route('therapist_appointments.edit', $a) }}"
                               class="btn btn-sm btn-secondary">
                                ✏️ Επεξεργασία
                            </a>

                            <form method="POST"
                                  action="{{ route('therapist_appointments.destroy', $a) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε;');">
                                @csrf
                                @method('DELETE')

                                <button class="btn btn-sm btn-danger">
                                    🗑 Διαγραφή
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

    </div>
</div>
@endsection

@push('scripts')
    <!-- jQuery (απαραίτητο για Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(function () {
            $('.js-customer-select').select2({
                placeholder: 'Όλοι οι πελάτες',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function () {
                        return 'Δεν βρέθηκαν αποτελέσματα';
                    }
                }
            });

            $('.js-professional-select').select2({
                placeholder: 'Όλοι οι επαγγελματίες',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function () {
                        return 'Δεν βρέθηκαν αποτελέσματα';
                    }
                }
            });
        });
    </script>
@endpush
