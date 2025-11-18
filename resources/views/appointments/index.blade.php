@extends('layouts.app')

@section('title', 'Ραντεβού')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Λίστα Ραντεβού</span>
            <a href="{{ route('appointments.create') }}" class="btn btn-primary btn-sm">
                + Προσθήκη Ραντεβού
            </a>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Ημ/νία & Ώρα</th>
                        <th>Πελάτης</th>
                        <th>Επαγγελματίας</th>
                        <th>Εταιρεία</th>
                        <th>Κατάσταση</th>
                        <th>Σύνολο (€)</th>
                        <th>Πληρωμή</th>
                        <th>Ενέργειες</th>  

                    </tr>
                    </thead>
                    <tbody>
                   @forelse($appointments as $appointment)
                    @php
                        $payment = $appointment->payment;
                        $total = $appointment->total_price ?? 0;
                        $paid  = $payment->amount ?? 0;
                    @endphp
                    <tr>
                        <td>{{ $appointment->id }}</td>
                        <td>{{ $appointment->start_time?->format('d/m/Y H:i') }}</td>
                         {{-- Πελάτης --}}
                        <td>
                            @if($appointment->customer)
                                {{ $appointment->customer->last_name }} {{ $appointment->customer->first_name }}<br>
                                <small class="text-muted">{{ $appointment->customer->phone }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>

                        {{-- Επαγγελματίας --}}
                        <td>
                            @if($appointment->professional)
                                {{ $appointment->professional->last_name }} {{ $appointment->professional->first_name }}<br>
                                <small class="text-muted">{{ $appointment->professional->phone }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>

                        {{-- Εταιρεία --}}
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
                            <td colspan="7" class="text-center text-muted py-4">
                                Δεν υπάρχουν ραντεβού ακόμα.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
