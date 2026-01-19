@extends('layouts.app')

@section('title', 'Περιστατικό: ' . $customer->last_name . ' ' . $customer->first_name)

@section('content')
    <div class="mb-3">
        <a href="{{ route('customers.index') }}" class="btn btn-secondary btn-sm">← Πίσω στη λίστα περιστατικών</a>
    </div>

    {{-- Στοιχεία Πελάτη + Οικονομική εικόνα --}}
    <div class="card mb-4">
        <div class="card-header">Στοιχεία Περιστατικού</div>

        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <p>
                        <strong>Επώνυμο:</strong>
                        <span class="inline-edit"
                            data-model="customer"
                            data-id="{{ $customer->id }}"
                            data-field="last_name"
                            data-type="text">
                            {{ $customer->last_name }}
                        </span>
                    </p>

                    <p>
                        <strong>Όνομα:</strong>
                        <span class="inline-edit"
                            data-model="customer"
                            data-id="{{ $customer->id }}"
                            data-field="first_name"
                            data-type="text">
                            {{ $customer->first_name }}
                        </span>
                    </p>

                    <p>
                        <strong>Τηλέφωνο:</strong>
                        <span class="inline-edit"
                            data-model="customer"
                            data-id="{{ $customer->id }}"
                            data-field="phone"
                            data-type="text">
                            {{ $customer->phone ?? '-' }}
                        </span>
                    </p>

                    <p>
                        <strong>Πληροφορίες:</strong><br>
                        <span class="inline-edit"
                            data-model="customer"
                            data-id="{{ $customer->id }}"
                            data-field="informations"
                            data-type="textarea"
                            style="white-space: pre-wrap; display:inline-block; width:100%;">
                            {{ $customer->informations ?? '-' }}
                        </span>
                    </p>
                </div>

                <div class="col-md-4">
                    <p>
                        <strong>Ραντεβού (επιλεγμένη περίοδος):</strong><br>
                        <span class="badge bg-dark fs-6">
                            {{ $globalAppointmentsCount ?? 0 }}
                        </span>
                    </p>
                    <p>
                        <strong>Συνολικό Ποσό Ραντεβού:</strong><br>
                        <span class="badge bg-primary fs-6">
                            {{ number_format($globalTotalAmount, 2, ',', '.') }} €
                        </span>
                    </p>
                    <p>
                        <strong>Συνολικό Ποσό που Έχει Πληρώσει:</strong><br>
                        <span class="badge bg-success fs-6">
                            {{ number_format($globalPaidTotal, 2, ',', '.') }} €
                        </span>
                    </p>
                    <p>
                        <strong>Υπόλοιπο (απλήρωτο):</strong><br>
                        <span class="badge {{ $globalOutstandingTotal > 0 ? 'bg-danger' : 'bg-secondary' }} fs-6">
                            {{ number_format($globalOutstandingTotal, 2, ',', '.') }} €
                        </span>
                    </p>
                    {{-- <small><i>Τα παραπάνω ποσά αναφέρονται στις επιλεγμένες ημερομηνίες</i></small> --}}
                </div>

                {{-- Ιστορικό πληρωμών ανά ημερομηνία --}}
                <div class="col-md-4">
                    <p><strong>Ιστορικό Πληρωμών:</strong></p>

                    <div class="border rounded p-2"
                         style="max-height: 180px; overflow-y: auto; font-size: 0.8rem; background-color: #f8f9fa;">
                        @forelse($paymentsByDate as $dateKey => $dayPayments)
                            @php
                                $dateLabel = $dateKey === 'Χωρίς ημερομηνία'
                                    ? 'Χωρίς ημερομηνία'
                                    : \Carbon\Carbon::parse($dateKey)->format('d/m/Y');

                                $dayTotal = $dayPayments->sum('amount');
                            @endphp

                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $dateLabel }}</strong>
                                        <span class="badge bg-primary ms-1">
                                            {{ number_format($dayTotal, 2, ',', '.') }} €
                                        </span>
                                    </div>

                                    <form method="POST"
                                        action="{{ route('customers.payments.destroyByDay', $customer) }}"
                                        class="m-0"
                                        onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε ΟΛΕΣ τις πληρωμές αυτής της ημέρας;');">
                                        @csrf
                                        @method('DELETE')

                                        {{-- περνάμε το group key --}}
                                        <input type="hidden"
                                            name="day_key"
                                            value="{{ $dateKey === 'Χωρίς ημερομηνία' ? 'no-date' : $dateKey }}">

                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                            Διαγραφή Πληρωμής
                                        </button>
                                    </form>
                                </div>


                                @foreach($dayPayments as $payment)
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <span class="{{ $payment->is_tax_fixed ? 'fw-bold text-warning' : '' }}">
                                            {{ number_format($payment->amount, 2, ',', '.') }} €
                                        </span>
                                        · {{ $payment->method === 'cash' ? 'Μετρητά' : ($payment->method === 'card' ? 'Κάρτα' : 'Άλλο') }}
                                        · {{ $payment->tax === 'Y' ? 'Με απόδειξη' : 'Χωρίς απόδειξη' }}
                                        @if($payment->is_tax_fixed)
                                            · <span class="badge bg-warning text-dark">Διορθώθηκε</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <hr class="my-1">
                        @empty
                            <span class="text-muted">Δεν έχουν γίνει πληρωμές για αυτόν το Περιστατικό.</span>
                        @endforelse
                    </div>
                </div>
            </div>

           

            <div class="d-flex justify-content-end mt-3">
                <a href="{{ route('customers.edit', ['customer' => $customer, 'redirect' => url()->full()]) }}"
                   title="Επεξεργασία περιστατικού"
                   class="btn btn-sm btn-secondary">
                    <i class="bi bi-pencil-square"></i>
                </a>
            </div>
        </div>
    </div>
 

    {{-- ===================== ΡΑΝΤΕΒΟΥ ===================== --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Ραντεβού Περιστατικού</span>

            <a href="{{ route('appointments.create', ['customer_id' => $customer->id, 'redirect' => request()->fullUrl()]) }}"
               class="btn btn-primary mb-0">
                + Προσθήκη Ραντεβού
            </a>
        </div>

        <div class="card-body">
            {{-- Filters --}}
            <form method="GET" action="{{ route('customers.show', $customer) }}" class="mb-2">
                @php
                    $range = $filters['range'] ?? 'month';
                    $day   = $filters['day'] ?? now()->format('Y-m-d');
                    $month = $filters['month'] ?? now()->format('Y-m');

                    $selectedProfessionalId = $filters['professional_id'] ?? 'all';
                @endphp

                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Περίοδος</label>
                        <select name="range" class="form-select" onchange="this.form.submit()">
                            <option value="month" @selected($range === 'month')>Μήνας</option>
                            <option value="day"   @selected($range === 'day')>Ημέρα</option>
                            <option value="all"   @selected($range === 'all')>Όλα</option>
                        </select>
                    </div>

                    {{-- ✅ ΝΕΟ: Φίλτρο Επαγγελματία --}}
                    <div class="col-md-3">
                        <label class="form-label">Επαγγελματίας</label>
                        <select name="professional_id" class="form-select" onchange="this.form.submit()">
                            <option value="all" @selected($selectedProfessionalId === 'all')>Όλοι</option>

                            @foreach(($appointmentProfessionals ?? []) as $pro)
                                <option value="{{ $pro->id }}" @selected((string)$selectedProfessionalId === (string)$pro->id)>
                                    {{ $pro->last_name }} {{ $pro->first_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        @if($range === 'day')
                            <input type="date" hidden name="day" class="form-control" value="{{ $day }}">
                        @elseif($range === 'month')
                            <input type="month" hidden name="month" class="form-control" value="{{ $month }}">
                        @else
                            <input type="text" hidden class="form-control" value="Όλα" disabled>
                        @endif
                    </div>

                    <div class="col-md-12 d-flex gap-2 justify-content-start">
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

           

            {{-- ===================== DELETE SELECTED ===================== --}}
            <form id="deleteAllForm"
                  method="POST"
                  action="{{ route('customers.deleteAppointments', $customer) }}"
                  onsubmit="return prepareDeleteAllForm();"
                  class="mt-3">
                @csrf
                @method('DELETE')

                <div id="appointmentsDeleteHiddenContainer"></div>

                <div class="d-flex justify-content-end">
                    <button type="submit"
                            id="delete-selected-btn"
                            class="btn btn-danger d-none shadow-sm"
                            onclick="return confirm('Σίγουρα θέλετε να διαγράψετε τα επιλεγμένα ραντεβού;');"
                            style="font-size: 0.9rem;">
                        🗑 Διαγραφή επιλεγμένων
                    </button>
                </div>
            </form>

            {{-- ===================== TABLE ===================== --}}
            <div class="table-responsive mb-3">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th class="text-center"><input type="checkbox" id="select_all"></th>
                        <th>Created</th>
                        <th>Ημ/νία & Ώρα</th>
                        <th>Επαγγελματίας</th>
                        {{-- <th>Εταιρεία</th> --}}
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
                            $hasFixed = $appointment->payments->contains(fn($p) => (int)($p->is_tax_fixed ?? 0) === 1);
                            $total     = (float) ($appointment->total_price ?? 0);
                            $paidTotal = (float) $appointment->payments->sum('amount');
                            $cashPaid  = (float) $appointment->payments->where('method','cash')->sum('amount');
                            $cardPaid  = (float) $appointment->payments->where('method','card')->sum('amount');
                        @endphp

                        <tr class="{{ $hasFixed ? 'table-warning' : '' }}">
                            <td class="text-center">
                                <input type="checkbox" class="appointment-checkbox" value="{{ $appointment->id }}">
                            </td>

                            <td>
                                @if($appointment->creator)
                                    <small class="text-muted">{{ $appointment->creator->first_name }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>
                                <span class="inline-edit"
                                    data-model="appointment"
                                    data-id="{{ $appointment->id }}"
                                    data-field="start_time"
                                    data-type="datetime">
                                    {{ $appointment->start_time?->format('d/m/Y H:i') ?? '-' }}
                                </span>
                            </td>


                            <td>
                                @if($appointment->professional)
                                    <a href="{{ route('professionals.show', $appointment->professional) }}">
                                        {{ $appointment->professional->last_name }} {{ $appointment->professional->first_name }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            {{-- <td>{{ $appointment->company->name ?? '-' }}</td> --}}

                            <td>
                                @php
                                    $serviceGreek = match($appointment->status) {
                                        'logotherapia'  => 'ΛΟΓΟΘΕΡΑΠΕΙΑ',
                                        'psixotherapia' => 'ΨΥΧΟΘΕΡΑΠΕΙΑ',
                                        'ergotherapia'  => 'ΕΡΓΟΘΕΡΑΠΕΙΑ',
                                        'omadiki'       => 'ΟΜΑΔΙΚΗ',
                                        'eidikos'       => 'ΕΙΔΙΚΟΣ ΠΑΙΔΑΓΩΓΟΣ',
                                        'aksiologisi'   => 'ΑΞΙΟΛΟΓΗΣΗ',
                                        default         => mb_strtoupper($appointment->status ?? '-', 'UTF-8'),
                                    };
                                @endphp

                                <span class="badge bg-info">
                                    {{ $serviceGreek }}
                                </span>
                            </td>


                            <td class="editable-price"
                                data-id="{{ $appointment->id }}"
                                style="cursor:pointer;">
                                {{ number_format($total, 2, ',', '.') }}
                            </td>

                            {{-- Πληρωμή (ΣΩΣΤΟ για split) --}}
                           {{-- Πληρωμή (με κανόνα: total_price <= 0 => ΠΛΗΡΩΜΕΝΟ) --}}
                            <td>
                                @php
                                    $isZeroPrice = $total <= 0; // $total ήδη είναι (float)($appointment->total_price ?? 0)
                                @endphp

                                @if($isZeroPrice)
                                    <span class="badge bg-success">Πλήρως πληρωμένο</span>
                                    <small class="text-muted d-block">Μηδενική χρέωση</small>
                                @else
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
                                @endif
                            </td>


                            <td style="white-space: pre-wrap;">
                                <span class="inline-edit"
                                    data-model="appointment"
                                    data-id="{{ $appointment->id }}"
                                    data-field="notes"
                                    data-type="textarea">
                                    {{ $appointment->notes ?? '-' }}
                                </span>
                            </td>


                            <td>
                                <a href="{{ route('appointments.edit', ['appointment' => $appointment, 'redirect' => request()->fullUrl()]) }}"
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
                                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                                    <button class="btn btn-sm btn-danger" title="Διαγραφή ραντεβού">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                Δεν υπάρχουν ραντεβού για αυτόν τον περιστατικό.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ===================== OUTSTANDING SPLIT PAYMENT (NO DATES) ===================== --}}
            <div class="border rounded p-3 mb-3" style="background:#f8f9fa">
                <h6 class="mb-2">💶 Πληρωμή όλων των χρωστούμενων ραντεβού</h6>

                {{-- Preview box (server-side) --}}
                <div class="border rounded p-3 mb-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Χρωστούμενα ραντεβού: <strong>{{ $outstandingCount ?? 0 }}</strong>
                        </div>
                        <div class="fs-5 fw-bold">
                            Υπόλοιπο: <span>{{ number_format($outstandingAmount ?? 0, 2, ',', '.') }} €</span>
                        </div>
                    </div>
                    @if(($outstandingAmount ?? 0) <= 0)
                        <div class="text-muted small mt-2">
                            Δεν υπάρχουν χρωστούμενα.
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('customers.payOutstandingSplit', $customer) }}">
                    @csrf

                    <div class="row g-2 align-items-end">
                        {{-- CASH WITH RECEIPT --}}
                        <div class="col-md-2">
                            <label class="form-label">Μετρητά (ΜΑ) €</label>
                            <input type="number" step="0.01" min="0" name="cash_y_amount"
                                class="form-control" placeholder="0.00">
                        </div>

                        {{-- CASH WITHOUT RECEIPT --}}
                        <div class="col-md-2">
                            <label class="form-label">Μετρητά (ΧΑ) €</label>
                            <input type="number" step="0.01" min="0" name="cash_n_amount"
                                class="form-control" placeholder="0.00">
                        </div>

                        {{-- CARD --}}
                        <div class="col-md-2">
                            <label class="form-label">Κάρτα €</label>
                            <input type="number" step="0.01" min="0" name="card_amount"
                                class="form-control" placeholder="0.00">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Τράπεζα (Κάρτα)</label>
                            <input type="text" name="card_bank" class="form-control" maxlength="255"
                                placeholder="π.χ. Alpha">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Ημερομηνία Πληρωμής</label>
                            <input
                                type="date"
                                name="paid_at"
                                class="form-control"
                                value="{{ now()->toDateString() }}"
                                required
                            >
                        </div>


                        {{-- <div class="col-md-9 mt-2">
                            <label class="form-label">Σημείωση (προαιρετικό)</label>
                            <input type="text" name="notes" class="form-control" maxlength="1000"
                                placeholder="π.χ. Πληρωμή χρωστούμενων (split).">
                        </div> --}}

                        <div class="col-md-2 mt-2 text-end">
                            <button type="submit"
                                    class="btn btn-success w-100"
                                    onclick="return confirm('Θέλετε να καταχωρήσετε αυτή την πληρωμή σε ΟΛΑ τα χρωστούμενα ραντεβού;');">
                                💶 Καταχώρηση Πληρωμής
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            {{-- ===================== /OUTSTANDING SPLIT PAYMENT ===================== --}}



            <div class="border rounded p-3 mb-3" style="background:#fff3cd">
                <h6 class="mb-2">🧾 Διόρθωση παλαιότερων πληρωμών (Μετρητά Χωρίς Απόδειξη ➜ Με Απόδειξη)</h6>

                <form method="POST" action="{{ route('customers.payments.taxFixOldest', $customer) }}"
                        onsubmit="return confirm('Σίγουρα; Θα αλλάξουν οι X παλαιότερες πληρωμές cash χωρίς απόδειξη σε 35€ με απόδειξη.');">
                    @csrf
                    <div class="row g-2 align-items-end">
                    <div class="col-md-3">

                        <input type="number" name="fix_amount" min="5" step="5" class="form-control" placeholder="π.χ. 5,10,15..." required>
                       
                        @error('fix_amount')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <button class="btn btn-warning w-100" type="submit">Εκτέλεση Διόρθωσης</button>
                    </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== ΑΡΧΕΙΑ ΠΕΛΑΤΗ ===================== --}}
    <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Αρχεία Περιστατικού</h6>
                </div>
                <div class="card-body">
                    <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="border rounded p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                

                                                <button type="button" class="btn btn-sm btn-primary"
                                                        onclick="document.getElementById('customerFileInput').click();">
                                                    + Προσθήκη / Ανέβασμα αρχείου
                                                </button>
                                            </div>

                                            <form method="POST"
                                                action="{{ route('customers.files.store', $customer) }}"
                                                enctype="multipart/form-data"
                                                class="row g-2 align-items-end">
                                                @csrf

                                                <div class="col-md-4">
                                                    <input id="customerFileInput" type="file" name="file" class="form-control d-none"
                                                        onchange="document.getElementById('customerFileName').value = this.files?.[0]?.name ?? '';">
                                                    <input id="customerFileName" type="text" class="form-control" placeholder="Δεν επιλέχθηκε αρχείο" readonly>
                                                    @error('file')
                                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label small text-muted mb-1">Σημείωση (προαιρετικό)</label>
                                                    <input type="text" name="notes" class="form-control" maxlength="1000" placeholder="π.χ. γνωμάτευση, παραστατικό...">
                                                    @error('notes')
                                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="col-md-2 text-end">
                                                    <button type="submit" class="btn btn-success w-100">Ανέβασμα</button>
                                                </div>
                                            </form>

                                            <hr class="my-3">

                                            @php
                                                $files = $customer->files?->sortByDesc('id') ?? collect();
                                            @endphp

                                            @if($files->count() === 0)
                                                <div class="text-muted">Δεν υπάρχουν αρχεία για αυτόν το περιστατικό.</div>
                                            @else
                                                <div class="table-responsive" style="max-height: 110px; overflow-y:auto;">
                                                    <table class="table table-sm table-striped align-middle mb-0">
                                                        <thead>
                                                        <tr>
                                                            <th>Αρχείο</th>
                                                            <th>Μέγεθος</th>
                                                            <th>Ημ/νία</th>
                                                            <th>Ανέβηκε από</th>
                                                            <th>Σημείωση</th>
                                                            <th class="text-end">Ενέργειες</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        @foreach($files as $f)
                                                            <tr>
                                                                <td>
                                                                    <strong>{{ $f->original_name }}</strong>
                                                                    @if($f->mime_type)
                                                                        <div class="text-muted small">{{ $f->mime_type }}</div>
                                                                    @endif
                                                                </td>
                                                                <td>{{ number_format(($f->size ?? 0) / 1024, 1, ',', '.') }} KB</td>
                                                                <td>{{ $f->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                                                <td>
                                                                    @if($f->uploader)
                                                                        {{ $f->uploader->last_name }} {{ $f->uploader->first_name }}
                                                                    @else
                                                                        <span class="text-muted">-</span>
                                                                    @endif
                                                                </td>
                                                                <td>{{ $f->notes ?? '-' }}</td>
                                                                <td class="text-end">
                                                                    @php
                                                                        $canPreview = \Illuminate\Support\Str::startsWith($f->mime_type, [
                                                                            'image/', 'application/pdf', 'text/'
                                                                        ]);
                                                                    @endphp

                                                                    @if($canPreview)
                                                                        <a class="btn btn-sm btn-outline-secondary"
                                                                        target="_blank"
                                                                        href="{{ route('customers.files.view', ['customer' => $customer->id, 'file' => $f->id]) }}">
                                                                            Άνοιγμα
                                                                        </a>
                                                                    @endif

                                                                    <a class="btn btn-sm btn-outline-primary"
                                                                    href="{{ route('customers.files.download', ['customer' => $customer->id, 'file' => $f->id]) }}">
                                                                        Download
                                                                    </a>

                                                                    <form method="POST"
                                                                        action="{{ route('customers.files.destroy', ['customer' => $customer->id, 'file' => $f->id]) }}"
                                                                        class="d-inline"
                                                                        onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτό το αρχείο;');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button class="btn btn-sm btn-danger">Διαγραφή</button>
                                                                    </form>
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
                </div>
    </div>
            {{-- ===================== /ΑΡΧΕΙΑ ΠΕΛΑΤΗ ===================== --}}


    <script>
        // Select all + delete button visibility
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll  = document.getElementById('select_all');
            const checkboxes = document.querySelectorAll('.appointment-checkbox');
            const deleteBtn  = document.getElementById('delete-selected-btn');

            function updateDeleteButtonVisibility() {
                const anySelected = Array.from(checkboxes).some(cb => cb.checked);
                deleteBtn.classList.toggle('d-none', !anySelected);
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(cb => cb.checked = selectAll.checked);
                    updateDeleteButtonVisibility();
                });
            }

            checkboxes.forEach(cb => cb.addEventListener('change', updateDeleteButtonVisibility));
        });

        function collectSelectedAppointments() {
            return Array.from(document.querySelectorAll('.appointment-checkbox:checked'))
                .map(cb => cb.value);
        }

        function prepareDeleteAllForm() {
            const container = document.getElementById('appointmentsDeleteHiddenContainer');
            const ids = collectSelectedAppointments();

            if (!ids.length) {
                alert('Παρακαλώ επιλέξτε τουλάχιστον ένα ραντεβού για διαγραφή.');
                return false;
            }

            container.innerHTML = '';

            ids.forEach(id => {
                const hidden = document.createElement('input');
                hidden.type  = 'hidden';
                hidden.name  = 'appointments[]';
                hidden.value = id;
                container.appendChild(hidden);
            });

            return true;
        }

        // Inline edit price (κρατάς το δικό σου endpoint)
        document.addEventListener('DOMContentLoaded', function () {
            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : null;

            if (!csrfToken) {
                console.warn('CSRF token meta tag is missing. Inline price editing will not work.');
                return;
            }

            let activeInput = null;

            document.querySelectorAll('.editable-price').forEach(td => {
                td.addEventListener('dblclick', function () {
                    if (activeInput) return;

                    const tdElem = this;
                    const originalDisplay = tdElem.innerText.trim();
                    const numericRaw = originalDisplay
                        .replace(/[^\d,.-]/g, '')
                        .replace('.', '')
                        .replace(',', '.');

                    const appointmentId = tdElem.dataset.id;

                    const input = document.createElement('input');
                    input.type = 'number';
                    input.step = '0.01';
                    input.min = '0';
                    input.className = 'form-control form-control-sm';
                    input.style.width = '100px';
                    input.value = numericRaw ? parseFloat(numericRaw) : 0;

                    tdElem.innerHTML = '';
                    tdElem.appendChild(input);
                    input.focus();
                    activeInput = input;

                    const restoreOriginal = () => {
                        tdElem.innerText = originalDisplay;
                        activeInput = null;
                    };

                    const saveValue = () => {
                        const newValue = input.value.trim();
                        if (newValue === '') {
                            restoreOriginal();
                            return;
                        }

                        tdElem.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

                        fetch("{{ url('/appointments') }}/" + appointmentId + "/update-price", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": csrfToken,
                                "Accept": "application/json",
                            },
                            body: JSON.stringify({ total_price: newValue })
                        })
                        .then(res => {
                            if (!res.ok) throw new Error('HTTP error ' + res.status);
                            return res.json();
                        })
                        .then(data => {
                            if (data.success) {
                                tdElem.innerText = data.new_price + ' €';
                            } else {
                                alert('Σφάλμα αποθήκευσης.');
                                restoreOriginal();
                            }
                            activeInput = null;
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Σφάλμα σύνδεσης ή CSRF.');
                            restoreOriginal();
                        });
                    };

                    input.addEventListener('blur', saveValue);

                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
                        if (e.key === 'Escape') { e.preventDefault(); restoreOriginal(); }
                    });
                });
            });
        });

        // ✅ Preview για split dates
        document.addEventListener('DOMContentLoaded', function () {
            const fromInput = document.getElementById('split_from');
            const toInput   = document.getElementById('split_to');

            const hintEl    = document.getElementById('previewHint');
            const amountEl  = document.getElementById('previewAmount');
            const countEl   = document.getElementById('previewCount');

            function resetPreview() {
                hintEl.classList.remove('d-none');
                amountEl.textContent = '0,00 €';
                countEl.textContent  = '0';
            }

            function updatePreview() {
                const from = fromInput.value;
                const to   = toInput.value;

                if (!from || !to) {
                    resetPreview();
                    return;
                }

                fetch(`{{ route('customers.paymentPreview', $customer) }}?from=${from}&to=${to}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    hintEl.classList.add('d-none');
                    amountEl.textContent = data.formatted;
                    countEl.textContent  = data.count;
                })
                .catch(() => resetPreview());
            }

            resetPreview();
            fromInput.addEventListener('change', updatePreview);
            toInput.addEventListener('change', updatePreview);
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : null;

            if (!csrfToken) {
                console.warn('CSRF token meta tag is missing. Inline editing will not work.');
                return;
            }

            let activeInput = null;

            function startEdit(el) {
                if (activeInput) return;

                const model = el.dataset.model;
                const id    = el.dataset.id;
                const field = el.dataset.field;
                const type  = el.dataset.type || 'text';

                const originalText = (el.textContent || '').trim();
                const originalValue = (originalText === '-' ? '' : originalText);

                let input;
                if (type === 'textarea') {
                    input = document.createElement('textarea');
                    input.rows = 4;
                    input.className = 'form-control form-control-sm';
                    input.value = originalValue;
                } else if (type === 'datetime') {
                    input = document.createElement('input');
                    input.type = 'datetime-local';
                    input.className = 'form-control form-control-sm';
                    input.style.width = '200px';

                    // convert displayed "d/m/Y H:i" -> "Y-m-d\TH:i" if possible
                    // if value is "-" keep empty
                    const v = originalValue.trim();
                    if (v && v !== '-') {
                        const m = v.match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/);
                        if (m) {
                            const [_, dd, mm, yyyy, HH, ii] = m;
                            input.value = `${yyyy}-${mm}-${dd}T${HH}:${ii}`;
                        } else {
                            input.value = '';
                        }
                    } else {
                        input.value = '';
                    }
                } else if (type === 'number') {
                    input = document.createElement('input');
                    input.type = 'number';
                    input.step = '0.01';
                    input.min = '0';
                    input.className = 'form-control form-control-sm';
                    input.style.width = '120px';
                    input.value = originalValue.replace(/[^\d,.-]/g,'').replace('.', '').replace(',', '.');
                } else {
                    input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control form-control-sm';
                    input.value = originalValue;
                }

                el.dataset._original = originalText;
                el.innerHTML = '';
                el.appendChild(input);
                input.focus();
                activeInput = input;

                const restore = () => {
                    el.textContent = el.dataset._original || '-';
                    activeInput = null;
                };

                const save = () => {
                    const newValue = input.value;

                    el.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

                    fetch("{{ route('inline.update') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                            "Accept": "application/json",
                        },
                        body: JSON.stringify({ model, id, field, value: newValue })
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('HTTP error ' + res.status);
                        return res.json();
                    })
                    .then(data => {
                        if (!data.success) throw new Error(data.message || 'Save failed');

                        // αν υπάρχει formatted (π.χ. total_price) χρησιμοποίησέ το
                        const text = (data.formatted ?? data.value ?? '').toString().trim();
                        el.textContent = text !== '' ? text : '-';
                        activeInput = null;
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Σφάλμα αποθήκευσης.');
                        restore();
                    });
                };

                input.addEventListener('blur', save);

                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' && type !== 'textarea') { e.preventDefault(); input.blur(); }
                    if (e.key === 'Escape') { e.preventDefault(); restore(); }
                });
            }

            // delegate dblclick
            document.addEventListener('dblclick', function (e) {
                const el = e.target.closest('.inline-edit');
                if (!el) return;
                startEdit(el);
            });
        });
        </script>

@endsection
