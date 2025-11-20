@extends('layouts.app')

@section('title', 'Πελάτης: ' . $customer->last_name . ' ' . $customer->first_name)

@section('content')
    <div class="mb-3">
        <a href="{{ route('customers.index') }}" class="btn btn-secondary btn-sm">← Πίσω στη λίστα πελατών</a>
    </div>

    {{-- Στοιχεία Πελάτη + Οικονομική εικόνα --}}
    <div class="card mb-4">
        <div class="card-header">
            Στοιχεία Πελάτη
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p><strong>Ονοματεπώνυμο:</strong> {{ $customer->last_name }} {{ $customer->first_name }}</p>
                    <p><strong>Τηλέφωνο:</strong> {{ $customer->phone }}</p>
                    <p><strong>Email:</strong> {{ $customer->email ?? '-' }}</p>
                    <p><strong>Εταιρεία:</strong> {{ $customer->company->name ?? '-' }}</p>
                    <p><strong>Σύνολο Ραντεβού (με βάση τα φίλτρα):</strong> {{ $appointmentsCount }}</p>
                </div>
                <div class="col-md-6">
                    <p>
                        <strong>Συνολικό Ποσό Ραντεβού:</strong><br>
                        <span class="badge bg-primary fs-6">
                            {{ number_format($totalAmount, 2, ',', '.') }} €
                        </span>
                    </p>
                    <p>
                        <strong>Συνολικό Ποσό που Έχει Πληρώσει:</strong><br>
                        <span class="badge bg-success fs-6">
                            {{ number_format($paidTotal, 2, ',', '.') }} €
                        </span>
                    </p>
                    <p>
                        <strong>Υπόλοιπο (απλήρωτο):</strong><br>
                        <span class="badge {{ $outstandingTotal > 0 ? 'bg-danger' : 'bg-secondary' }} fs-6">
                            {{ number_format($outstandingTotal, 2, ',', '.') }} €
                        </span>
                    </p>
                    <p class="mb-0">
                        <small class="text-muted">
                            Μετρητά: {{ number_format($cashTotal, 2, ',', '.') }} € &nbsp;|&nbsp;
                            Κάρτα: {{ number_format($cardTotal, 2, ',', '.') }} €
                        </small>
                    </p>
                </div>
            </div>
        </div>
        {{-- <form method="POST" action="{{ route('customers.payAll', $customer) }}"
            onsubmit="return confirm('Να πληρωθούν όλα τα φιλτραρισμένα ραντεβού;');">

            @csrf

             Στέλνουμε τα ίδια φίλτρα 
            <input type="hidden" name="from" value="{{ $filters['from'] ?? '' }}">
            <input type="hidden" name="to" value="{{ $filters['to'] ?? '' }}">
            <input type="hidden" name="status" value="{{ $filters['status'] ?? 'all' }}">
            <input type="hidden" name="payment_status" value="{{ $filters['payment_status'] ?? 'all' }}">
            <input type="hidden" name="payment_method" value="{{ $filters['payment_method'] ?? 'all' }}">

            <button class="btn btn-success mt-3">
                💶 Πληρωμή Όλων (Φιλτραρισμένων)
            </button>
        </form> --}}

    </div>

    {{-- Ραντεβού Πελάτη --}}
    <div class="card">
        <div class="card-header">
            Ραντεβού Πελάτη
        </div>

        <div class="card-body">

            {{-- Φίλτρα --}}
            <form method="GET" action="{{ route('customers.show', $customer) }}" class="mb-3">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Από Ημερομηνία</label>
                        <input type="date" name="from" class="form-control"
                               value="{{ $filters['from'] ?? '' }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Έως Ημερομηνία</label>
                        <input type="date" name="to" class="form-control"
                               value="{{ $filters['to'] ?? '' }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Υπηρεσία Ραντεβού</label>
                        @php $st = $filters['status'] ?? 'all'; @endphp
                        <select name="status" class="form-select">
                            <option value="all" @selected($st === 'all')>Όλα</option>
                            <option value="logotherapia" @selected($st === 'logotherapia')>Λογοθεραπεία</option>
                            <option value="psixotherapia" @selected($st === 'psixotherapia')>Ψυχοθεραπεία</option>
                            <option value="ergotherapia" @selected($st === 'ergotherapia')>Εργοθεραπεία</option>
                            <option value="omadiki" @selected($st === 'omadiki')>Ομαδική</option>
                            <option value="eidikos" @selected($st === 'eidikos')>Ειδικός παιδαγωγός</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Κατάσταση Πληρωμής</label>
                        @php $ps = $filters['payment_status'] ?? 'all'; @endphp
                        <select name="payment_status" class="form-select">
                            <option value="all" @selected($ps === 'all')>Όλα</option>
                            <option value="unpaid" @selected($ps === 'unpaid')>Απλήρωτα</option>
                            <option value="partial" @selected($ps === 'partial')>Μερικώς πληρωμένα</option>
                            <option value="full" @selected($ps === 'full')>Πλήρως πληρωμένα</option>
                        </select>
                    </div>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-md-3">
                        <label class="form-label">Τρόπος Πληρωμής</label>
                        @php $pm = $filters['payment_method'] ?? 'all'; @endphp
                        <select name="payment_method" class="form-select">
                            <option value="all" @selected($pm === 'all')>Όλοι</option>
                            <option value="cash" @selected($pm === 'cash')>Μετρητά</option>
                            <option value="card" @selected($pm === 'card')>Κάρτα</option>
                        </select>
                    </div>

                    <div class="col-md-9 d-flex align-items-end justify-content-end">
                        <button class="btn btn-outline-primary me-2">
                            Εφαρμογή Φίλτρων
                        </button>
                        <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary">
                            Καθαρισμός
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Ημ/νία & Ώρα</th>
                        <th>Επαγγελματίας</th>
                        <th>Εταιρεία</th>
                        <th>Υπηρεσία </th>
                        <th>Σύνολο (€)</th>
                        <th>Πληρωμή</th>
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($appointments as $appointment)
                        @php
                            $payment = $appointment->payment;
                            $total   = $appointment->total_price ?? 0;
                            $paid    = $payment->amount ?? 0;
                        @endphp
                        <tr>
                            <td>{{ $appointment->id }}</td>
                            <td>{{ $appointment->start_time?->format('d/m/Y H:i') }}</td>

                            <td>
                                @if($appointment->professional)
                                    {{ $appointment->professional->last_name }} {{ $appointment->professional->first_name }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>{{ $appointment->company->name ?? '-' }}</td>

                            <td>
                                <span class="badge
                                    @if($appointment->status === 'completed') bg-success
                                    @elseif($appointment->status === 'cancelled') bg-danger
                                    @elseif($appointment->status === 'no_show') bg-warning text-dark
                                    @else bg-secondary
                                    @endif">
                                    {{ $appointment->status }}
                                </span>
                            </td>

                            <td>{{ number_format($total, 2, ',', '.') }}</td>

                            {{-- Πληρωμή --}}
                            <td>
                                @if(!$payment || $paid <= 0)
                                    <span class="badge bg-danger">Απλήρωτο</span>
                                @elseif($paid < $total)
                                    <span class="badge bg-warning text-dark">
                                        Μερική πληρωμή {{ number_format($paid, 2, ',', '.') }} €
                                        <br>
                                        <small class="text-muted">
                                            @if($payment->method === 'cash')
                                                Μετρητά
                                            @elseif($payment->method === 'card')
                                                Κάρτα
                                            @else
                                                Μέθοδος άγνωστη
                                            @endif
                                        </small>
                                    </span>
                                @else
                                    <span class="badge bg-success">
                                        Πλήρως πληρωμένο {{ number_format($paid, 2, ',', '.') }} €
                                        <br>
                                        <small class="text-light">
                                            @if($payment->method === 'cash')
                                                Μετρητά
                                            @elseif($payment->method === 'card')
                                                Κάρτα
                                            @else
                                                Μέθοδος άγνωστη
                                            @endif
                                        </small>
                                    </span>
                                @endif
                            </td>

                            {{-- Ενέργειες --}}
                            <td>
                                {{-- Επεξεργασία Ραντεβού --}}
                                <a href="{{ route('appointments.edit', ['appointment' => $appointment, 'redirect' => request()->fullUrl()]) }}"
                                class="btn btn-sm btn-secondary mb-1"
                                title="Επεξεργασία ραντεβού">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                {{-- Επεξεργασία Πληρωμής --}}
                                <a href="{{ route('appointments.payment.edit', ['appointment' => $appointment, 'redirect' => request()->fullUrl()]) }}"
                                class="btn btn-sm btn-outline-primary mb-1"
                                title="Επεξεργασία πληρωμής">
                                    <i class="bi bi-credit-card"></i>
                                </a>

                                {{-- Διαγραφή Ραντεβού --}}
                                <form action="{{ route('appointments.destroy', $appointment) }}"
                                    method="POST"
                                    class="d-inline"
                                    onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτό το ραντεβού;');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                    <button class="btn btn-sm btn-danger"
                                            title="Διαγραφή ραντεβού">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>


                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Δεν υπάρχουν ραντεβού για αυτόν τον πελάτη.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
@endsection
