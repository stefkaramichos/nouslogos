{{-- resources/views/professionals/show.blade.php --}}

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

        <div class="card-body row">
            <div class="col-md-4 text-center mb-3">
                @if($professional->profile_image)
                    <img src="{{ asset('storage/'.$professional->profile_image) }}"
                         alt="Profile image"
                         class="img-thumbnail mb-2"
                         style="max-width: 200px;">
                @else
                    <div class="border rounded-circle d-inline-flex justify-content-center align-items-center mb-2"
                         style="width: 120px; height: 120px; font-size: 48px;">
                        {{ mb_substr($professional->first_name, 0, 1) }}
                    </div>
                @endif
            </div>

            <div class="col-md-4">
                <p><strong>Ονοματεπώνυμο:</strong> {{ $professional->last_name }} {{ $professional->first_name }}</p>
                <p><strong>Τηλέφωνο:</strong> {{ $professional->phone }}</p>
                <p><strong>Email:</strong> {{ $professional->email ?? '-' }}</p>
                <p><strong>Εταιρεία:</strong> {{ $professional->company->name ?? '-' }}</p>
            </div>

            <div class="col-md-4">
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

                {{-- (optional) έξτρα --}}
                {{--
                <p class="mb-0">
                    <strong>Εισπράξεις:</strong>
                    <span class="badge bg-dark fs-6">{{ number_format($paidTotal, 2, ',', '.') }} €</span>
                </p>
                <p class="mb-0">
                    <strong>Υπόλοιπο:</strong>
                    <span class="badge bg-warning text-dark fs-6">{{ number_format($outstandingTotal, 2, ',', '.') }} €</span>
                </p>
                --}}
            </div>
        </div>
    </div>

    {{-- Αναλυτικός πίνακας ραντεβών --}}
    <div class="card">
        <div class="card-header">
            Ραντεβού Επαγγελματία
        </div>

        <div class="card-body">

            @php
                $range = $filters['range'] ?? 'month';
                $day   = $filters['day'] ?? now()->format('Y-m-d');
                $month = $filters['month'] ?? now()->format('Y-m');
            @endphp

            {{-- ===================== PERIOD FILTER (month/day/all + prev/next) ===================== --}}
            <form method="GET" action="{{ route('professionals.show', $professional) }}" class="mb-2">

                {{-- keep customer search --}}
                <input type="hidden" name="customer" value="{{ $filters['customer'] ?? '' }}">

                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Περίοδος</label>
                        <select name="range" class="form-select" onchange="this.form.submit()">
                            <option value="month" @selected($range === 'month')>Μήνας</option>
                            <option value="day"   @selected($range === 'day')>Ημέρα</option>
                            <option value="all"   @selected($range === 'all')>Όλα</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        @if($range === 'day')
                            <input type="date" name="day" hidden class="form-control" value="{{ $day }}">
                        @elseif($range === 'month')
                            <input type="month" name="month" hidden class="form-control" value="{{ $month }}">
                        @else
                            <input type="text" hidden class="form-control" value="Όλα" disabled>
                        @endif
                    </div>

                    <div class="col-md-9 d-flex gap-2 justify-content-start">
                        @if($range !== 'all')
                            <a href="{{ $prevUrl }}" class="btn btn-outline-secondary">← Προηγούμενο</a>
                            <a href="{{ $nextUrl }}" class="btn btn-outline-secondary">Επόμενο →</a>
                        @endif
                    </div>
                </div>
            </form>

            <div class="mb-3">
                <span class="text-muted">Έχετε επιλέξει:</span>
                <span class="badge bg-dark">{{ $selectedLabel ?? 'Όλα' }}</span>
            </div>

            {{-- ===================== CUSTOMER FILTER (text) ===================== --}}
            <form method="GET" action="{{ route('professionals.show', $professional) }}" class="mb-3">
                {{-- keep range fields --}}
                <input type="hidden" name="range" value="{{ $range }}">
                @if($range === 'day')
                    <input type="hidden" name="day" value="{{ $day }}">
                @elseif($range === 'month')
                    <input type="hidden" name="month" value="{{ $month }}">
                @endif

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Πελάτης</label>
                        <input type="text" name="customer" class="form-control"
                               placeholder="Όνομα ή επώνυμο..."
                               value="{{ $filters['customer'] ?? '' }}">
                    </div>

                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                        <button class="btn btn-outline-primary me-2">
                            Εφαρμογή
                        </button>

                        {{-- reset to current month (not all) --}}
                        <a href="{{ route('professionals.show', $professional, [
                            'range' => 'month',
                            'month' => now()->format('Y-m'),
                        ]) }}" class="btn btn-outline-secondary">
                            Καθαρισμός
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                {{-- @include('../includes/selected_dates') --}}
                {{-- το αντικαταστήσαμε με month/day/all επάνω --}}

                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Θερ.</th>
                        <th>Ημερομηνία & Ώρα</th>
                        <th>Πελάτης</th>
                        <th>Εταιρεία</th>
                        <th>Σύνολο (€)</th>
                        <th>Ποσό Επαγγελματία (€)</th>
                        <th>Πληρωμή Πελάτη</th>
                        <th>Σημειώσεις</th>
                        <th>Ενέργειες</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($appointments as $appointment)
                        @php
                            $total     = (float)($appointment->total_price ?? 0);
                            $paidTotal = (float)$appointment->payments->sum('amount');
                            $cashPaid  = (float)$appointment->payments->where('method','cash')->sum('amount');
                            $cardPaid  = (float)$appointment->payments->where('method','card')->sum('amount');

                            $therapistMatches = $therapistMatches ?? [];
                            $matchKey = ($appointment->customer_id ?? 0)
                                . '|'
                                . ($appointment->start_time ? $appointment->start_time->toDateString() : '');
                        @endphp

                        <tr>
                            {{-- Σημάδι therapist_appointments --}}
                            <td class="text-center">
                                @if(isset($therapistMatches[$matchKey]))
                                    <span class="badge bg-info" title="Υπάρχει αντίστοιχο ραντεβού από τον θεραπευτή">
                                        ✅
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>{{ $appointment->start_time?->format('d/m/Y H:i') }}</td>

                            <td>
                                @if($appointment->customer)
                                    <a href="{{ route('customers.show', $appointment->customer) }}">
                                        {{ $appointment->customer->last_name }} {{ $appointment->customer->first_name }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>{{ $appointment->company->name ?? '-' }}</td>

                            <td>{{ number_format($total, 2, ',', '.') }}</td>

                            <td>
                                <span class="badge bg-secondary">
                                    {{ number_format((float)($appointment->professional_amount ?? 0), 2, ',', '.') }} €
                                </span>
                            </td>

                           {{-- ✅ Πληρωμή (κανόνας: total_price <= 0 => ΠΛΗΡΩΜΕΝΟ) --}}
                            <td>
                                @php
                                    $isZeroPrice = $total <= 0;
                                @endphp

                                @if($isZeroPrice)
                                    <span class="badge bg-success">Πλήρως πληρωμένο</span>
                                    <small class="text-muted d-block">Μηδενική χρέωση</small>

                                @elseif($paidTotal <= 0)
                                    <span class="badge bg-danger">Απλήρωτο</span>

                                @elseif($paidTotal < $total)
                                    <span class="badge bg-warning text-dark">
                                        Μερική πληρωμή {{ number_format($paidTotal, 2, ',', '.') }} €
                                        <br>
                                        <small class="text-muted">
                                            @if($cashPaid > 0) Μετρητά: {{ number_format($cashPaid, 2, ',', '.') }} € @endif
                                            @if($cashPaid > 0 && $cardPaid > 0) · @endif
                                            @if($cardPaid > 0) Κάρτα: {{ number_format($cardPaid, 2, ',', '.') }} € @endif
                                        </small>
                                    </span>

                                @else
                                    <span class="badge bg-success">
                                        Πλήρως πληρωμένο {{ number_format($paidTotal, 2, ',', '.') }} €
                                        <br>
                                        <small class="text-light">
                                            @if($cashPaid > 0) Μετρητά: {{ number_format($cashPaid, 2, ',', '.') }} € @endif
                                            @if($cashPaid > 0 && $cardPaid > 0) · @endif
                                            @if($cardPaid > 0) Κάρτα: {{ number_format($cardPaid, 2, ',', '.') }} € @endif
                                        </small>
                                    </span>
                                @endif
                            </td>


                            <td>
                                {{ $appointment->notes ? Str::limit($appointment->notes, 50) : '-' }}
                            </td>

                            <td>
                                <a href="{{ route('appointments.edit', ['appointment' => $appointment, 'redirect' => request()->fullUrl()]) }}"
                                   class="btn btn-sm btn-secondary"
                                   title="Επεξεργασία ραντεβού">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <form action="{{ route('appointments.destroy', $appointment) }}"
                                      class="d-inline"
                                      method="POST"
                                      onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε;');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                    <button class="btn btn-sm btn-danger" title="Διαγραφή ραντεβού">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Δεν υπάρχουν ραντεβού.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                <div class="d-flex justify-content-center mt-3">
                    {{ $appointments->withQueryString()->links() }}
                </div>

                {{-- Ραντεβού που υπάρχουν ΜΟΝΟ στον πίνακα therapist_appointments --}}
                @if(!empty($therapistMissing) && count($therapistMissing) > 0)
                    <hr>

                    <h5 class="mt-3">
                        Συνεδρίες που έχουν καταχωρηθεί ΜΟΝΟ στο προσωπικό ημερολόγιο θεραπευτή
                    </h5>

                    <div class="table-responsive mt-2">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>Ημερομηνία</th>
                                <th>Πελάτης</th>
                                <th>Σημειώσεις θεραπευτή</th>
                                <th>Κατάσταση</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($therapistMissing as $ta)
                                <tr class="text-muted" style="opacity: 0.6;">
                                    <td>
                                        {{ $ta->start_time ? $ta->start_time->format('d/m/Y') : '-' }}
                                    </td>
                                    <td>
                                        @if($ta->customer)
                                            {{ $ta->customer->last_name }} {{ $ta->customer->first_name }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $ta->notes ? Str::limit($ta->notes, 30) : '-' }}</td>
                                    <td>
                                        <span class="badge bg-warning text-dark" title="Δεν υπάρχει στο κεντρικό σύστημα ραντεβού">
                                            ⚠ Δεν έχει καταχωρηθεί στο κύριο σύστημα
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            </div>
        </div>
    </div>
@endsection
