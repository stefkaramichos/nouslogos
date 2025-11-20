@extends('layouts.app')

@section('title', 'Επαγγελματίας: '.$professional->last_name.' '.$professional->first_name)

@section('content')

    <div class="mb-3">
        <a href="{{ route('professionals.index') }}" class="btn btn-secondary btn-sm">← Πίσω στη λίστα</a>
    </div>

    {{-- Στοιχεία επαγγελματία --}}
    <div class="card mb-4">
        <div class="card-header">
            Στοιχεία Επαγγελματία
        </div>
        <div class="card-body">

            <p><strong>Ονοματεπώνυμο:</strong> {{ $professional->last_name }} {{ $professional->first_name }}</p>
            <p><strong>Τηλέφωνο:</strong> {{ $professional->phone }}</p>
            <p><strong>Email:</strong> {{ $professional->email ?? '-' }}</p>
            <p><strong>Εταιρεία:</strong> {{ $professional->company->name ?? '-' }}</p>

            <p><strong>Χρέωση Υπηρεσίας:</strong>
                {{ number_format($professional->service_fee, 2, ',', '.') }} €
            </p>

            <p><strong>Ποσό που λαμβάνει ο επαγγελματίας ανά ραντεβού:</strong>
    {{ number_format($professional->percentage_cut, 2, ',', '.') }} €
</p>

        </div>
    </div>

    {{-- Οικονομικά στοιχεία --}}
    <div class="card mb-4">
        <div class="card-header">
            Οικονομική Εικόνα Επαγγελματία (με βάση τα φίλτρα)
        </div>

        <div class="card-body">
            <p>
                <strong>Σύνολο Ραντεβού:</strong>
                <span class="badge bg-primary fs-6">{{ $appointmentsCount }}</span>
            </p>

            <p>
                <strong>Συνολικό ποσό ραντεβού:</strong><br>
                <span class="badge bg-info fs-6">
                    {{ number_format($totalAmount, 2, ',', '.') }} €
                </span>
            </p>

            <p>
                <strong>Ποσό που δικαιούται ο επαγγελματίας:</strong><br>
                <span class="badge bg-success fs-6">
                    {{ number_format($professionalTotalCut, 2, ',', '.') }} €
                </span>
            </p>

            {{-- <p>
                <strong>Πόσα έχουν πληρωθεί από πελάτες:</strong><br>
                <span class="badge bg-primary fs-6">
                    {{ number_format($paidTotal, 2, ',', '.') }} €
                </span>
            </p>

            <p>
                <strong>Απλήρωτο ποσό ραντεβών (συνολικά):</strong><br>
                <span class="badge {{ $outstandingTotal > 0 ? 'bg-danger' : 'bg-secondary' }} fs-6">
                    {{ number_format($outstandingTotal, 2, ',', '.') }} €
                </span>
            </p>

            <hr>

            <p>
                <strong>Πόσα (εκτιμώμενα) έχει λάβει ο επαγγελματίας:</strong><br>
                <span class="badge bg-success fs-6">
                    {{ number_format($professionalPaid, 2, ',', '.') }} €
                </span>
            </p>

            <p>
                <strong>Υπόλοιπο που (εκτιμώμενα) οφείλεται στον επαγγελματία:</strong><br>
                <span class="badge {{ $professionalOutstanding > 0 ? 'bg-danger' : 'bg-secondary' }} fs-6">
                    {{ number_format($professionalOutstanding, 2, ',', '.') }} €
                </span>
            </p> --}}
        </div>
    </div>


    {{-- Αναλυτικός πίνακας ραντεβών --}}
    <div class="card">
        <div class="card-header">
            Ραντεβού Επαγγελματία
        </div>

        <div class="card-body">

            {{-- Φίλτρα --}}
            <form method="GET" action="{{ route('professionals.show', $professional) }}" class="mb-3">
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
                        <label class="form-label">Πελάτης</label>
                        <input type="text" name="customer" class="form-control"
                               placeholder="Όνομα ή επώνυμο..."
                               value="{{ $filters['customer'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Κατάσταση Πληρωμής</label>
                        <select name="payment_status" class="form-select">
                            @php $st = $filters['payment_status'] ?? 'all'; @endphp
                            <option value="all" @selected($st === 'all')>Όλα</option>
                            <option value="unpaid" @selected($st === 'unpaid')>Απλήρωτα</option>
                            <option value="partial" @selected($st === 'partial')>Μερικώς πληρωμένα</option>
                            <option value="full" @selected($st === 'full')>Πλήρως πληρωμένα</option>
                        </select>
                    </div>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-md-3">
                        <label class="form-label">Τρόπος Πληρωμής</label>
                        <select name="payment_method" class="form-select">
                            @php $pm = $filters['payment_method'] ?? 'all'; @endphp
                            <option value="all" @selected($pm === 'all')>Όλοι</option>
                            <option value="cash" @selected($pm === 'cash')>Μετρητά</option>
                            <option value="card" @selected($pm === 'card')>Κάρτα</option>
                        </select>
                    </div>
                    <div class="col-md-9 d-flex align-items-end justify-content-end">
                        <button class="btn btn-outline-primary me-2">
                            Εφαρμογή Φίλτρων
                        </button>
                        <a href="{{ route('professionals.show', $professional) }}" class="btn btn-outline-secondary">
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
                        <th>Ημερομηνία & Ώρα</th>
                        <th>Πελάτης</th>
                        <th>Εταιρεία</th>
                        <th>Σύνολο (€)</th>
                        <th>Πληρωμή Πελάτη</th>
                        <th>Σημειώσεις</th>
                        <th>Ποσό Επαγγελματία (€)</th>
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($appointments as $appointment)

                        @php
                            $payment = $appointment->payment;
                            $paid    = $payment->amount ?? 0;
                            $total   = $appointment->total_price ?? 0;
                        @endphp

                        <tr>
                            <td>{{ $appointment->id }}</td>

                            <td>{{ $appointment->start_time?->format('d/m/Y H:i') }}</td>

                            <td>
                                @if($appointment->customer)
                                    {{ $appointment->customer->last_name }}
                                    {{ $appointment->customer->first_name }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>{{ $appointment->company->name ?? '-' }}</td>
                            
                            <td>{{ number_format($total, 2, ',', '.') }}</td>

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
                            <td>
                                {{ $appointment->notes ? Str::limit($appointment->notes, 50) : '-' }}
                            </td>

                            <td>
                                <span class="badge bg-secondary">
                                    {{ number_format($appointment->professional_amount, 2, ',', '.') }} €
                                </span>
                            </td>

                            <td>
                                <a href="{{ route('appointments.edit', ['appointment' => $appointment, 'redirect' => request()->fullUrl()]) }}"
                                class="btn btn-sm btn-secondary">
                                    Επεξεργασία
                                </a>

                                <a href="{{ route('appointments.payment.edit', ['appointment' => $appointment, 'redirect' => request()->fullUrl()]) }}"
                                class="btn btn-sm btn-outline-primary">
                                    Επεξεργασία Πληρωμή
                                </a>

                                <form action="{{ route('appointments.destroy', $appointment) }}"
                                    class="d-inline"
                                    method="POST"
                                    onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε;');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                    <button class="btn btn-sm btn-danger">Διαγραφή</button>
                                </form>
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Δεν υπάρχουν ραντεβού.</td>
                        </tr>
                    @endforelse
                    </tbody>

                </table>
            </div>
        </div>
    </div>

@endsection
