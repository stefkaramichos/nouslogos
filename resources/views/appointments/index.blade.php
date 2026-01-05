{{-- resources/views/appointments/index.blade.php --}}

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

        {{-- ===================== FILTERS ===================== --}}
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

        {{-- ===================== TABLE ===================== --}}
        <div class="table-responsive mt-3">
            {{-- Αν έχεις include για επιλεγμένες ημερομηνίες, κράτα το --}}
            @includeIf('../includes/selected_dates')

            <table class="table table-striped mb-0 align-middle">
                <thead>
                <tr>
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
                        // ✅ Split-safe totals
                        $total     = (float) ($appointment->total_price ?? 0);
                        $paidTotal = (float) $appointment->payments->sum('amount');
                        $cashPaid  = (float) $appointment->payments->where('method','cash')->sum('amount');
                        $cardPaid  = (float) $appointment->payments->where('method','card')->sum('amount');
                    @endphp

                    <tr>
                        <td>{{ $appointment->start_time?->format('d/m/Y H:i') }}</td>

                        {{-- Customer --}}
                        <td>
                            @if($appointment->customer)
                                <a href="{{ route('customers.show', $appointment->customer) }}">
                                    {{ $appointment->customer->last_name }} {{ $appointment->customer->first_name }}
                                </a>
                                <br>
                                <small class="text-muted">{{ $appointment->customer->phone }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>

                        {{-- Professional --}}
                        <td>
                            @if($appointment->professional)
                                <a href="{{ route('professionals.show', $appointment->professional) }}">
                                    {{ $appointment->professional->last_name }} {{ $appointment->professional->first_name }}
                                </a>
                                <br>
                                <small class="text-muted">{{ $appointment->professional->phone }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>

                        {{-- Company --}}
                        <td>{{ $appointment->company->name ?? '-' }}</td>

                        {{-- Status / service --}}
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

                        {{-- Total --}}
                        <td>{{ number_format($total, 2, ',', '.') }}</td>

                        {{-- Payment (split friendly) --}}
                        <td>
                            @if($paidTotal <= 0)
                                <span class="badge bg-danger">Απλήρωτο</span>
                            @else
                                @if($paidTotal < $total)
                                    <span class="badge bg-warning text-dark d-block mb-1">
                                        Μερική πληρωμή {{ number_format($paidTotal, 2, ',', '.') }} €
                                    </span>
                                @else
                                    <span class="badge bg-success d-block mb-1">
                                        Πλήρως πληρωμένο {{ number_format($paidTotal, 2, ',', '.') }} €
                                    </span>
                                @endif

                                <small class="text-muted d-block">
                                    @if($cashPaid > 0) Μετρητά: {{ number_format($cashPaid, 2, ',', '.') }} € @endif
                                    @if($cashPaid > 0 && $cardPaid > 0) · @endif
                                    @if($cardPaid > 0) Κάρτα: {{ number_format($cardPaid, 2, ',', '.') }} € @endif
                                </small>
                            @endif
                        </td>

                        {{-- Notes --}}
                        <td title="{{ $appointment->notes }}">
                            {{ $appointment->notes ? \Illuminate\Support\Str::limit($appointment->notes, 30) : '-' }}
                        </td>

                        {{-- Actions --}}
                        <td>
                            <a href="{{ route('appointments.edit', $appointment) }}"
                               class="btn btn-sm btn-secondary mb-1"
                               title="Επεξεργασία ραντεβού">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <form action="{{ route('appointments.destroy', $appointment) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτό το ραντεβού;');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="Διαγραφή ραντεβού">
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

    <div class="card-footer d-flex justify-content-center">
        {{ $appointments->withQueryString()->links() }}
    </div>
</div>
@endsection
