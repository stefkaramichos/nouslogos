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

            <p><strong>Ποσοστό Επαγγελματία:</strong>
                {{ number_format($professional->percentage_cut, 2, ',', '.') }}%
            </p>

        </div>
    </div>

    {{-- Οικονομικά στοιχεία --}}
    <div class="card mb-4">
        <div class="card-header">
            Οικονομική Εικόνα Επαγγελματία
        </div>

        <div class="card-body">
            <p>
                <strong>Σύνολο Ραντεβού:</strong>
                <span class="badge bg-primary fs-6">{{ $appointmentsCount }}</span>
            </p>

            <p>
                <strong>Συνολικό ποσό ραντεβών:</strong><br>
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
                <strong>Πόσα έχει πληρωθεί έως τώρα:</strong><br>
                <span class="badge bg-primary fs-6">
                    {{ number_format($professionalPaid, 2, ',', '.') }} €
                </span>
            </p>

            <p>
                <strong>Υπόλοιπο που οφείλεται:</strong><br>
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

        <div class="card-body p-0">
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
                        <th>Ποσό Επαγγελματία (€)</th>
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($appointments as $appointment)

                        @php
                            $payment = $appointment->payment;
                            $paid = $payment->amount ?? 0;
                            $total = $appointment->total_price ?? 0;
                        @endphp

                        <tr>
                            <td>{{ $appointment->id }}</td>

                            <td>{{ $appointment->start_time?->format('d/m/Y H:i') }}</td>

                            <td>
                                {{ $appointment->customer->last_name }}
                                {{ $appointment->customer->first_name }}
                            </td>

                            <td>{{ $appointment->company->name }}</td>

                            <td>{{ number_format($total, 2, ',', '.') }}</td>

                            <td>
                                @if(!$payment || $paid <= 0)
                                    <span class="badge bg-danger">Απλήρωτο</span>
                                @elseif($paid < $total)
                                    <span class="badge bg-warning text-dark">
                                        Μερική πληρωμή {{ number_format($paid, 2, ',', '.') }} €
                                        <br>
                                        <small class="text-muted">
                                            {{ $payment->method === 'cash' ? 'Μετρητά' : 'Κάρτα' }}
                                        </small>
                                    </span>
                                @else
                                    <span class="badge bg-success">
                                        Πλήρως πληρωμένο {{ number_format($paid, 2, ',', '.') }} €
                                        <br>
                                        <small class="text-light">
                                            {{ $payment->method === 'cash' ? 'Μετρητά' : 'Κάρτα' }}
                                        </small>
                                    </span>
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-secondary">
                                    {{ number_format($appointment->professional_amount, 2, ',', '.') }} €
                                </span>
                            </td>

                            <td>
                                <a href="{{ route('appointments.edit', $appointment) }}" class="btn btn-sm btn-secondary">Επεξεργασία</a>

                                <a href="{{ route('appointments.payment.edit', $appointment) }}" class="btn btn-sm btn-outline-primary">Πληρωμή</a>

                                <form action="{{ route('appointments.destroy', $appointment) }}"
                                      class="d-inline"
                                      method="POST"
                                      onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε;');">
                                    @csrf
                                    @method('DELETE')
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
