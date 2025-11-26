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
            <div class="col-md-4">
                <label class="form-label">Από</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label">Έως</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>

            <div class="col-md-4 d-flex align-items-end gap-2">
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
