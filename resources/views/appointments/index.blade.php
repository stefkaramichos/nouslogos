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

        <div class="card-body">

            {{-- Φόρμα φίλτρων --}}
            {{-- Φόρμα φίλτρων --}}
<form method="GET" action="{{ route('appointments.index') }}">

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
            <select name="customer_id" class="form-select">
                <option value="">Όλοι</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}"
                        @selected(($filters['customer_id'] ?? '') == $customer->id)>
                        {{ $customer->last_name }} {{ $customer->first_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Επαγγελματίας</label>
            <select name="professional_id" class="form-select">
                <option value="">Όλοι</option>
                @foreach($professionals as $professional)
                    <option value="{{ $professional->id }}"
                        @selected(($filters['professional_id'] ?? '') == $professional->id)>
                        {{ $professional->last_name }} {{ $professional->first_name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row g-2 mt-2">
        <div class="col-md-3">
            <label class="form-label">Εταιρεία</label>
            <select name="company_id" class="form-select">
                <option value="">Όλες</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}"
                        @selected(($filters['company_id'] ?? '') == $company->id)>
                        {{ $company->name }}
                    </option>
                @endforeach
            </select>
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

        <div class="col-md-3">
            <label class="form-label">Τρόπος Πληρωμής</label>
            @php $pm = $filters['payment_method'] ?? 'all'; @endphp
            <select name="payment_method" class="form-select">
                <option value="all" @selected($pm === 'all')>Όλοι</option>
                <option value="cash" @selected($pm === 'cash')>Μετρητά</option>
                <option value="card" @selected($pm === 'card')>Κάρτα</option>
            </select>
        </div>
    </div>

    <div class="row g-2 mt-3">
        <div class="col-md-12 d-flex justify-content-end">
            <button class="btn btn-outline-primary me-2">
                Εφαρμογή Φίλτρων
            </button>
            <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary">
                Καθαρισμός
            </a>
        </div>
    </div>
</form>


            {{-- Πίνακας ραντεβών --}}
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Ημ/νία & Ώρα</th>
                        <th>Πελάτης</th>
                        <th>Επαγγελματίας</th>
                        <th>Εταιρεία</th>
                        <th>Υπηρεσία</th>
                        <th>Σύνολο (€)</th>
                        <th>Πληρωμή</th>
                        <th>Σημειώσεις</th>
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

                            {{-- Κατάσταση --}}
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

                            {{-- Σύνολο --}}
                            <td>{{ number_format($total, 2, ',', '.') }}</td>
                           

                            {{-- Πληρωμή --}}
                            <td>
                                @if(!$payment || $paid <= 0)
                                    <span class="badge bg-danger">Απλήρωτο</span>
                                @else
                                    @php
                                        $methodLabel = $payment->method === 'cash'
                                            ? 'Μετρητά'
                                            : ($payment->method === 'card' ? 'Κάρτα' : 'Άγνωστο');

                                        $taxLabel = $payment->tax === 'Y'
                                            ? 'Με απόδειξη'
                                            : 'Χωρίς απόδειξη';
                                    @endphp

                                    @if($paid < $total)
                                        <span class="badge bg-warning text-dark d-block mb-1">
                                            Μερική πληρωμή {{ number_format($paid, 2, ',', '.') }} €
                                        </span>
                                    @else
                                        <span class="badge bg-success d-block mb-1">
                                            Πλήρως πληρωμένο {{ number_format($paid, 2, ',', '.') }} €
                                        </span>
                                    @endif

                                    <small class="d-block text-muted">
                                        {{ $methodLabel }} &middot; {{ $taxLabel }}
                                    </small>
                                @endif
                            </td>

                             <td>
                                {{ $appointment->notes ? Str::limit($appointment->notes, 50) : '-' }}
                            </td>

                            {{-- Ενέργειες --}}
                            <td>
                                <!-- Edit -->
                                <a href="{{ route('appointments.edit', $appointment) }}"
                                class="btn btn-sm btn-secondary mb-1"
                                title="Επεξεργασία ραντεβού">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <!-- Edit Payment -->
                                <a href="{{ route('appointments.payment.edit', $appointment) }}"
                                class="btn btn-sm btn-outline-primary mb-1"
                                title="Επεξεργασία πληρωμής">
                                    <i class="bi bi-credit-card"></i>
                                </a>

                                <!-- Delete -->
                                <form action="{{ route('appointments.destroy', $appointment) }}"
                                    method="POST"
                                    class="d-inline"
                                    onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτό το ραντεβού;');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger"
                                            title="Διαγραφή ραντεβού">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>


                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
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
