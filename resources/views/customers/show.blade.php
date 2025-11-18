@extends('layouts.app')

@section('title', 'Πελάτης: ' . $customer->last_name . ' ' . $customer->first_name)

@section('content')
    <div class="mb-3">
        <a href="{{ route('customers.index') }}" class="btn btn-secondary btn-sm">← Πίσω στη λίστα πελατών</a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Στοιχεία Πελάτη
        </div>
        <div class="card-body">
            <p><strong>Ονοματεπώνυμο:</strong> {{ $customer->last_name }} {{ $customer->first_name }}</p>
            <p><strong>Τηλέφωνο:</strong> {{ $customer->phone }}</p>
            <p><strong>Email:</strong> {{ $customer->email ?? '-' }}</p>
            <p><strong>Εταιρεία:</strong> {{ $customer->company->name ?? '-' }}</p>
            <p><strong>Σύνολο Ραντεβού:</strong> {{ $appointmentsCount }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Ραντεβού Πελάτη
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Ημ/νία & Ώρα</th>
                        <th>Επαγγελματίας</th>
                        <th>Εταιρεία</th>
                        <th>Κατάσταση</th>
                        <th>Σύνολο (€)</th>
                        <th>Πληρωμή</th>
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($customer->appointments as $appointment)
                        @php
                            $payment = $appointment->payment;
                            $total = $appointment->total_price ?? 0;
                            $paid  = $payment->amount ?? 0;
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
                                        @if($payment->method === 'cash')
                                            (Μετρητά)
                                        @elseif($payment->method === 'card')
                                            (Κάρτα)
                                        @endif
                                    </span>
                                @else
                                    <span class="badge bg-success">
                                        Πλήρως πληρωμένο {{ number_format($paid, 2, ',', '.') }} €
                                        @if($payment->method === 'cash')
                                            (Μετρητά)
                                        @elseif($payment->method === 'card')
                                            (Κάρτα)
                                        @endif
                                    </span>
                                @endif
                            </td>

                            {{-- Ενέργειες --}}
                            <td>
                                <a href="{{ route('appointments.payment.edit', $appointment) }}" class="btn btn-sm btn-outline-primary">
                                    Επεξεργασία Πληρωμής
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
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
