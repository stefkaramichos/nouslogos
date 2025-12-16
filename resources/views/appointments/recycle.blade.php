@extends('layouts.app')

@section('title', 'Recycle (Ραντεβού)')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Recycle (Ραντεβού)</strong>

        <a href="{{ route('appointments.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-left"></i> Πίσω στα Ραντεβού
        </a>
    </div>

    <div class="card-body">

        {{-- Filters --}}
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Από (ημ/νία διαγραφής)</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Έως (ημ/νία διαγραφής)</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>

            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-outline-primary w-100">Φιλτράρισμα</button>
                <a href="{{ route('appointments.recycle') }}" class="btn btn-outline-secondary">Καθαρισμός</a>
            </div>
        </form>

        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            Εδώ βλέπεις μόνο <strong>διαγραμμένα ραντεβού</strong>. Μπορείς να κάνεις <strong>επαναφορά</strong> ή <strong>οριστική διαγραφή</strong>.
        </div>

        {{-- Results --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Πελάτης</th>
                    <th>Επαγγελματίας</th>
                    <th>Ημερομηνία Ραντεβού</th>
                    <th>Διαγράφηκε</th>
                    <th class="text-end">Ενέργειες</th>
                </tr>
                </thead>

                <tbody>
                @forelse($appointments as $a)
                    <tr>
                        <td>{{ $a->id }}</td>

                        <td>
                            @if($a->customer)
                                <a href="{{ route('customers.show', $a->customer) }}">
                                    {{ $a->customer->last_name }} {{ $a->customer->first_name }}
                                </a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>

                        <td>
                            @if($a->professional)
                                {{ $a->professional->last_name }} {{ $a->professional->first_name }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>

                        <td>{{ optional($a->start_time)->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>{{ optional($a->deleted_at)->format('d/m/Y H:i') ?? '-' }}</td>

                        <td class="text-end">
                            {{-- Restore --}}
                            <form method="POST" action="{{ route('appointments.restore', $a->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" title="Επαναφορά">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </form>

                            {{-- Force delete --}}
                            <form method="POST"
                                  action="{{ route('appointments.forceDelete', $a->id) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Οριστική διαγραφή; Δεν θα μπορεί να ανακτηθεί.');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="Οριστική Διαγραφή">
                                    <i class="bi bi-x-octagon"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Ο κάδος είναι άδειος.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $appointments->links() }}
        </div>

    </div>
</div>
@endsection
