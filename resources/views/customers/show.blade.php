@extends('layouts.app')

@section('title', 'Περιστατικό: ' . $customer->last_name . ' ' . $customer->first_name)

@section('content')
    <style>
        tr.tax-fix-colored-row td {
            background-color: var(--tax-fix-color) !important;
        }
    </style>

    <div class="mb-3">
        <a href="{{ route('customers.index') }}#customer_row_{{ $customer->id }}" class="btn btn-secondary btn-sm">← Πίσω στη λίστα περιστατικών</a>
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
                            style="white-space: pre-wrap; display:inline-block; width:100%; max-height:150px; overflow-y:auto; border:1px solid #ced4da; border-radius:0.25rem; padding:0.375rem 0.75rem;">{{ $customer->informations ?? '-' }}</span>
                    </p>
                </div>

                <div class="col-md-4">
                    <p>
                        <strong>Ραντεβού (επιλεγμένη περίοδος):</strong><br>
                        <span class="badge bg-dark fs-6">
                            {{ $nonZeroAppointmentsCount ?? 0 }}
                            @if(($zeroAppointmentsCount ?? 0) > 0)
                                ( + {{ $zeroAppointmentsCount }} μηδενικά )
                            @endif
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
                        <div class="small text-muted mt-1">
                            <div>
                                Μετρητά (ΜΑ): {{ number_format($paidBreakdown['cash_y']['amount'] ?? 0, 2, ',', '.') }} €
                                · Ραντεβού: {{ str_replace('.', ',', (string)($paidBreakdown['cash_y']['appt_count'] ?? 0)) }}
                            </div>
                            <div>
                                Μετρητά (ΧΑ): {{ number_format($paidBreakdown['cash_n']['amount'] ?? 0, 2, ',', '.') }} €
                                · Ραντεβού: {{ str_replace('.', ',', (string)($paidBreakdown['cash_n']['appt_count'] ?? 0)) }}
                            </div>
                            <div>
                                Κάρτα: {{ number_format($paidBreakdown['card']['amount'] ?? 0, 2, ',', '.') }} €
                                · Ραντεβού: {{ str_replace('.', ',', (string)($paidBreakdown['card']['appt_count'] ?? 0)) }}
                            </div>
                        </div>
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

                    @php
                    $dayColorPalette = [
                        '#ffe8a3', '#bfefff', '#c8f2d7', '#f8c9cf',
                        '#dccbff', '#ffd9b3', '#bff3ea', '#d8dde3',
                        '#fce4ec', '#e8f5e9', '#e3f2fd', '#fff9c4',
                    ];
                    $paymentDateColors = [];
                    $pdIdx = 0;
                    foreach (($paymentsByDate ?? collect())->keys() as $pdKey) {
                        $paymentDateColors[$pdKey] = $dayColorPalette[$pdIdx % count($dayColorPalette)];
                        $pdIdx++;
                    }
                    // appointment_id => color (from most recent payment date, which comes first in desc order)
                    $appointmentPaymentColors = [];
                    // dateKey => [appointment_ids]
                    $dateAppointmentIds = [];
                    foreach (($paymentsByDate ?? collect()) as $pdKey => $pdPayments) {
                        foreach ($pdPayments as $pdPayment) {
                            $pdAid = (int)($pdPayment->appointment_id ?? 0);
                            if ($pdAid > 0 && !isset($appointmentPaymentColors[$pdAid])) {
                                $appointmentPaymentColors[$pdAid] = $paymentDateColors[$pdKey] ?? null;
                            }
                            if ($pdAid > 0) {
                                $dateAppointmentIds[$pdKey][] = $pdAid;
                            }
                        }
                    }
                    // deduplicate
                    foreach ($dateAppointmentIds as $k => $v) {
                        $dateAppointmentIds[$k] = array_values(array_unique($v));
                    }
                @endphp
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
                                        @php $dotApptIds = implode(',', $dateAppointmentIds[$dateKey] ?? []); @endphp
                                        <span class="payment-dot-link"
                                              data-appt-ids="{{ $dotApptIds }}"
                                              data-color="{{ $paymentDateColors[$dateKey] ?? '#ccc' }}"
                                              style="display:inline-block;width:13px;height:13px;border-radius:50%;background:{{ $paymentDateColors[$dateKey] ?? '#ccc' }};border:1px solid rgba(0,0,0,0.25);margin-right:5px;vertical-align:middle;flex-shrink:0;cursor:pointer;"
                                              title="Κλικ για εμφάνιση σχετικών ραντεβού"></span><strong
                                            class="payment-day-date-edit"
                                            data-day-key="{{ $dateKey === 'Χωρίς ημερομηνία' ? 'no-date' : $dateKey }}"
                                            data-customer-id="{{ $customer->id }}"
                                            style="cursor:pointer;"
                                            title="Διπλό κλικ για αλλαγή ημερομηνίας"
                                            >
                                            {{ $dateLabel }}
                                            </strong>

                                            <span
                                            class="badge bg-primary ms-1 payment-day-total-edit"
                                            data-day-key="{{ $dateKey === 'Χωρίς ημερομηνία' ? 'no-date' : $dateKey }}"
                                            data-customer-id="{{ $customer->id }}"
                                            style="cursor:pointer;"
                                            title="Διπλό κλικ για αλλαγή ημερήσιου συνόλου"
                                            >
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

                    {{-- ✅ Προπληρωμές: ξεχωριστές εγγραφές με ημερομηνία --}}
                    <div class="mt-2">
                    @php
                        $prepayList = ($prepayments ?? collect())->filter(function ($p) {
                            return ((float)($p->cash_y_balance ?? 0)
                                + (float)($p->cash_n_balance ?? 0)
                                + (float)($p->card_balance ?? 0)) > 0;
                        });
                    @endphp

                    @if($prepayList->isNotEmpty())
                        <div class="border rounded p-2 mb-2"
                            style="font-size:0.8rem; background:#f8f9fa;"
                            id="prepayment">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Προπληρωμές</strong>

                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary">
                                        {{ number_format((float)($prepaymentTotal ?? 0), 2, ',', '.') }} €
                                    </span>

                                    <form method="POST"
                                        action="{{ route('customers.prepayment.destroy', $customer) }}"
                                        class="m-0"
                                        onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε ΟΛΕΣ τις εγγραφές προπληρωμής;');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="_anchor" value="prepayment">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                            Διαγραφή όλων
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="border rounded bg-white p-2" style="max-height: 130px; overflow-y:auto;">
                                @foreach($prepayList as $pp)
                                    @php
                                        $rowTotal = (float)($pp->cash_y_balance ?? 0)
                                            + (float)($pp->cash_n_balance ?? 0)
                                            + (float)($pp->card_balance ?? 0);
                                        $dateLabel = $pp->last_paid_at
                                            ? \Carbon\Carbon::parse($pp->last_paid_at)->format('d/m/Y')
                                            : optional($pp->created_at)->format('d/m/Y');
                                    @endphp
                                    <div class="mb-2 pb-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted prepayment-date-edit"
                                                  style="cursor:pointer"
                                                  title="Διπλό κλικ για αλλαγή ημερομηνίας"
                                                  data-customer-id="{{ $customer->id }}"
                                                  data-prepayment-id="{{ $pp->id }}"
                                                  data-original="{{ $pp->last_paid_at ? \Carbon\Carbon::parse($pp->last_paid_at)->toDateString() : optional($pp->created_at)->toDateString() }}">{{ $dateLabel ?: '-' }}</span>
                                            <span class="badge bg-secondary prepayment-amount-edit"
                                                  style="cursor:pointer"
                                                  title="Διπλό κλικ για αλλαγή ποσού"
                                                  data-customer-id="{{ $customer->id }}"
                                                  data-prepayment-id="{{ $pp->id }}"
                                                  data-original="{{ number_format($rowTotal, 2, '.', '') }}">{{ number_format($rowTotal, 2, ',', '.') }} €</span>
                                        </div>
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            @if((float)($pp->cash_y_balance ?? 0) > 0)
                                                Μετρητά (με απόδειξη): {{ number_format((float)$pp->cash_y_balance, 2, ',', '.') }} €
                                                <br>
                                            @endif
                                            @if((float)($pp->cash_n_balance ?? 0) > 0)
                                                Μετρητά (χωρίς απόδειξη): {{ number_format((float)$pp->cash_n_balance, 2, ',', '.') }} €
                                                <br>
                                            @endif
                                            @if((float)($pp->card_balance ?? 0) > 0)
                                                Κάρτα{{ $pp->card_bank ? ' · '.$pp->card_bank : '' }}:
                                                {{ number_format((float)$pp->card_balance, 2, ',', '.') }} €
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    </div>
                </div>
            </div>

            <hr>

            {{-- ===================== OUTSTANDING SPLIT PAYMENT (NO DATES) ===================== --}}
            <div id="pay-outstanding" class="border rounded p-3 mb-3" style="background:#f8f9fa">
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
                            <label class="form-label">Κάρτα/Τράπεζα €</label>
                            <input type="number" step="0.01" min="0" name="card_amount"
                                class="form-control" placeholder="0.00">
                        </div>

                        {{-- <div class="col-md-2">
                            <label class="form-label">Τράπεζα (Κάρτα)</label>
                            <input type="text" name="card_bank" class="form-control" maxlength="255"
                                placeholder="π.χ. Alpha">
                        </div> --}}

                        <div class="col-md-4">
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

            <hr>

            <div class="row mt-2 mb-3">
                {{-- ✅ Logs Διόρθωσης (customer_tax_fix_logs) --}}
                @php
                    $logs = $taxFixLogs ?? collect();
                @endphp

               @if($logs->count() > 0)
                    <div class="border rounded col-md-4 p-2"
                        style="max-height: 240px; overflow-y:auto; font-size:0.8rem; background:#f8f9fa;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong>Διορθώσεις</strong>
                            <span class="badge bg-dark">{{ $logs->count() }}</span>
                        </div>

                        @foreach($logs as $log)
                            @php
                                $dateLabel = $log->run_at
                                    ? \Carbon\Carbon::parse($log->run_at)->format('d/m/Y')
                                    : '-';

                                $amount = (float)($log->fix_amount ?? 0);
                                $comment = $log->comment ?? null;

                                // για το date input
                                $dateOriginal = $log->run_at
                                    ? \Carbon\Carbon::parse($log->run_at)->format('Y-m-d')
                                    : '';
                            @endphp

                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge me-1"
                                              title="Χρώμα αυτής της διόρθωσης"
                                              style="background: {{ $taxFixLogColors[(int)$log->id] ?? 'rgba(255,193,7,.28)' }}; color:#212529; border:1px solid rgba(0,0,0,.15);">
                                            ●
                                        </span>

                                        {{-- ✅ ΔΙΠΛΟ ΚΛΙΚ: ΗΜΕΡΟΜΗΝΙΑ --}}
                                        <strong class="tax-fix-log-date-edit"
                                            data-log-id="{{ $log->id }}"
                                            data-original="{{ $dateOriginal }}"
                                            style="cursor:pointer;"
                                            title="Διπλό κλικ για αλλαγή ημερομηνίας">
                                            {{ $dateLabel }}
                                        </strong>

                                        {{-- ✅ ΔΙΠΛΟ ΚΛΙΚ: ΠΟΣΟ (όπως το έχεις ήδη) --}}
                                        <span class="badge bg-primary ms-1 tax-fix-log-edit"
                                            data-log-id="{{ $log->id }}"
                                            data-original="{{ number_format((float)$amount, 2, ',', '.') }}"
                                            style="cursor:pointer;"
                                            title="Διπλό κλικ για αλλαγή ποσού διόρθωσης">
                                            {{ number_format($amount, 2, ',', '.') }} €
                                        </span>
                                    </div>
                                </div>

                                {{-- ✅ ΔΙΠΛΟ ΚΛΙΚ: ΣΧΟΛΙΟ --}}
                                <div class="text-muted tax-fix-log-comment-edit"
                                    data-log-id="{{ $log->id }}"
                                    data-original="{{ $comment ?? '' }}"
                                    style="font-size:0.75rem; cursor:pointer;"
                                    title="Διπλό κλικ για αλλαγή σχολίου">
                                    {{ $comment ? $comment : '-' }}
                                </div>
                            </div>

                            <hr class="my-1">
                        @endforeach
                    </div>
                @endif


                <div class="col-md-8">
                    {{-- ===================== ΑΠΟΔΕΙΞΕΙΣ (ΝΕΟ BOX) ===================== --}}
                    <div class="border rounded p-2" style="background:#f8f9fa;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>Αποδείξεις</strong>

                                <div class="mt-1" style="font-size:0.85rem;">
                                    <span class="badge bg-success">
                                        Κομμένες: {{ $issuedReceiptsCount ?? 0 }}
                                    </span>

                                    <span class="badge bg-primary">
                                        Σύνολο κομμένων: {{ number_format((float)($issuedReceiptsTotal ?? 0), 2, ',', '.') }} €
                                    </span>
                                </div>
                            </div>

                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#receiptCreateModal">
                                + Νέα Απόδειξη
                            </button>
                        </div>


                        <div class="table-responsive" style="max-height: 180px; overflow-y:auto; font-size:0.85rem;">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Σχόλιο</th>
                                    <th>Ποσό</th>
                                    <th>Κόπηκε;</th>
                                    <th>Ημ/νία</th>
                                    <th class="text-end">Ενέργειες</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse(($receipts ?? collect()) as $r) 
                                    <tr id="receipt_row_{{ $r->id }}">
                                        
                                        <td class="receipt-inline-edit"
                                        data-id="{{ $r->id }}"
                                        data-field="comment"
                                        data-type="text"
                                        data-original="{{ $r->comment ?? '' }}"
                                        style="cursor:pointer; max-width:240px;">
                                        {{ $r->comment ? \Illuminate\Support\Str::limit($r->comment, 140) : '-' }}
                                        <small class="text-muted ms-1"> </small>
                                    </td>
                                    <td class="receipt-inline-edit"
                                        data-id="{{ $r->id }}"
                                        data-field="amount"
                                        data-type="number"
                                        data-original="{{ number_format((float)$r->amount, 2, ',', '.') }}"
                                        style="cursor:pointer; white-space:nowrap;">
                                        <span class="badge bg-primary">
                                            {{ number_format((float)$r->amount, 2, ',', '.') }} €
                                        </span>
                                        <small class="text-muted ms-1"> </small>
                                    </td>

                                        <td class="receipt-inline-edit"
                                            data-id="{{ $r->id }}"
                                            data-field="is_issued"
                                            data-type="bool"
                                            data-original="{{ (int)($r->is_issued ?? 0) }}"
                                            style="cursor:pointer; white-space:nowrap;">
                                            @if((int)($r->is_issued ?? 0) === 1)
                                                <span class="badge bg-success">ΝΑΙ</span>
                                            @else
                                                <span class="badge bg-secondary">ΟΧΙ</span>
                                            @endif
                                            <small class="text-muted ms-1"> </small>
                                        </td>


                                        <td class="receipt-inline-edit"
                                            data-id="{{ $r->id }}"
                                            data-field="receipt_date"
                                            data-type="date"
                                            data-original="{{ $r->receipt_date ? \Carbon\Carbon::parse($r->receipt_date)->format('Y-m-d') : '' }}"
                                            style="cursor:pointer; white-space:nowrap;">
                                            {{ $r->receipt_date ? \Carbon\Carbon::parse($r->receipt_date)->format('d/m/Y') : '-' }}
                                            <small class="text-muted ms-1"> </small>
                                        </td>

                                        <td class="text-end">
                                            <form method="POST"
                                                action="{{ route('customers.receipts.destroy', ['customer' => $customer->id, 'receipt' => $r->id]) }}"
                                                class="d-inline"
                                                onsubmit="return confirm('Σίγουρα διαγραφή απόδειξης;');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2">Διαγραφή</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            Δεν υπάρχουν αποδείξεις.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    {{-- ===================== /ΑΠΟΔΕΙΞΕΙΣ ===================== --}}

                    {{-- ===================== MODAL CREATE ===================== --}}
                    <div class="modal fade" id="receiptCreateModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content"
                            method="POST"
                            action="{{ route('customers.receipts.store', $customer) }}">
                        @csrf

                        <div class="modal-header">
                            <h5 class="modal-title">Νέα Απόδειξη</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="row g-2">
                                <div class="col-9">
                                    <label class="form-label">Σχόλιο</label>
                                    <input type="text" name="comment" maxlength="1000" class="form-control" placeholder="προαιρετικό...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Ποσό (€)</label>
                                    <input type="number" step="0.01" min="0" name="amount" class="form-control" >
                                </div>
                                
                                <div class="col-md-9">
                                    <label class="form-label">Ημερομηνία</label>
                                    <input type="date" name="receipt_date" class="form-control" >
                                </div>
                                
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="is_issued_create" name="is_issued">
                                        <label class="form-check-label" for="is_issued_create">
                                            Έχει κοπεί
                                        </label>
                                    </div>
                                </div>
                                
                                
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Άκυρο</button>
                            <button type="submit" class="btn btn-success">Αποθήκευση</button>
                        </div>
                        </form>
                    </div>
                    </div>
                    {{-- ===================== /MODAL CREATE ===================== --}}

                    @push('scripts')
                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        if (!csrfToken) return;

                        let activeInput = null;

                        function toGreekDate(d) {
                            if (!d) return '-';
                            const m = d.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                            if (!m) return d;
                            return `${m[3]}/${m[2]}/${m[1]}`;
                        }

                        function startEdit(cell) {
                            if (activeInput) return;

                            const id    = cell.dataset.id;
                            const field = cell.dataset.field;
                            const type  = cell.dataset.type || 'text';
                            const originalHTML = cell.innerHTML;
                            const originalVal  = cell.dataset.original ?? '';

                            let input;

                            if (type === 'number') {
                                input = document.createElement('input');
                                input.type = 'number';
                                input.step = '0.01';
                                input.min  = '0';
                                input.className = 'form-control form-control-sm';
                                input.style.width = '110px';

                                const raw = (originalVal || '0')
                                    .replace(/[^\d,.-]/g,'')
                                    .replace('.', '')
                                    .replace(',', '.');
                                input.value = raw ? parseFloat(raw) : 0;
                            }
                            else if (type === 'date') {
                                input = document.createElement('input');
                                input.type = 'date';
                                input.className = 'form-control form-control-sm';
                                input.style.width = '150px';
                                input.value = originalVal || '';
                            }
                            else if (type === 'bool') {
                                input = document.createElement('select');
                                input.className = 'form-select form-select-sm';
                                input.style.width = '90px';
                                input.innerHTML = `
                                    <option value="0">ΟΧΙ</option>
                                    <option value="1">ΝΑΙ</option>
                                `;
                                input.value = String(parseInt(originalVal || '0', 10));
                            }
                            else {
                                input = document.createElement('input');
                                input.type = 'text';
                                input.className = 'form-control form-control-sm';
                                input.style.width = '220px';
                                input.value = originalVal || '';
                            }

                            cell.innerHTML = '';
                            cell.appendChild(input);
                            input.focus();
                            activeInput = input;

                            const restore = () => {
                                cell.innerHTML = originalHTML;
                                activeInput = null;
                            };

                            const save = () => {
                                const newVal = (input.value ?? '').toString();

                                cell.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

                                fetch(`{{ url('/customers/' . $customer->id) }}/receipts/${id}/inline-update`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken,
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({ field: field, value: newVal })
                                })
                                .then(async res => {
                                    const data = await res.json().catch(() => ({}));
                                    if (!res.ok || !data.success) throw data;
                                    return data;
                                })
                                .then((data) => {
                                    // πιο safe: reload για να ξαναβγάλει σωστά badges/format/limit
                                    window.location.reload();
                                })
                                .catch(err => {
                                    console.error(err);
                                    alert(err?.message || 'Σφάλμα αποθήκευσης.');
                                    restore();
                                });
                            };

                            input.addEventListener('blur', save);
                            input.addEventListener('keydown', function (ev) {
                                if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
                                if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
                            });
                        }

                        document.addEventListener('dblclick', function (e) {
                            const cell = e.target.closest('.receipt-inline-edit');
                            if (!cell) return;
                            e.preventDefault();
                            startEdit(cell);
                        });
                    });
                    </script>
                    @endpush

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
     <div class="card" id="appointments-section">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Ραντεβού Περιστατικού</span>

            <a href="{{ route('appointments.create', ['customer_id' => $customer->id, 'redirect' => request()->fullUrl()]) }}"
               class="btn btn-primary mb-0">
                + Προσθήκη Ραντεβού
            </a>
        </div>

        <div class="card-body">
            {{-- Filters --}}
             <form method="GET" action="{{ route('customers.show', $customer) }}#appointments-section" class="mb-2">
                @php
                    $range = $filters['range'] ?? 'month';
                    $day   = $filters['day'] ?? now()->format('Y-m-d');
                    $month = $filters['month'] ?? now()->format('Y-m');

                    $selectedProfessionalId = $filters['professional_id'] ?? 'all';
                @endphp

                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Περίοδος</label>
                        <select name="range" class="form-select" onchange="this.form.submit()">
                            <option value="month" @selected($range === 'month')>Μήνας</option>
                            <option value="day"   @selected($range === 'day')>Ημέρα</option>
                            <option value="all"   @selected($range === 'all')>Όλα</option>
                        </select>
                    </div>

                    {{-- ✅ ΝΕΟ: Φίλτρο Επαγγελματία --}}
                    @php
                        $range = $filters['range'] ?? 'month';
                        $day   = $filters['day'] ?? now()->format('Y-m-d');
                        $month = $filters['month'] ?? now()->format('Y-m');

                        // ✅ MULTI selected ids
                        $selectedProfessionalIds = $filters['professional_ids'] ?? [];
                        if (!is_array($selectedProfessionalIds)) $selectedProfessionalIds = [];
                    @endphp

                    <div class="col-md-3">
                        <label class="form-label">Επαγγελματίες</label>

                        <select name="professional_ids[]" class="form-select" multiple size="3" onchange="this.form.submit()">
                            @foreach(($appointmentProfessionals ?? []) as $pro)
                                <option value="{{ $pro->id }}" @selected(in_array((string)$pro->id, array_map('strval', $selectedProfessionalIds), true))>
                                    {{ $pro->last_name }} {{ $pro->first_name }}
                                </option>
                            @endforeach
                        </select>

                        <div class="form-text">
                            Ctrl (Windows) / Cmd (Mac) για πολλαπλή επιλογή.
                           <a href="{{ route('customers.show', $customer, array_merge(request()->query(), ['professional_ids' => []])) }}#appointments-section"
                            class="ms-2">Καθαρισμός</a>
                        </div>
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
                            <a href="{{ $prevUrl }}#appointments-section" class="btn btn-outline-secondary">← Προηγούμενο</a>
                            <a href="{{ $nextUrl }}#appointments-section" class="btn btn-outline-secondary">Επόμενο →</a>
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
                              $hasAddon = $appointment->payments->contains(function($p){
                                    return is_string($p->notes ?? null) && str_contains($p->notes, '[TAX_FIX_ADDON]');
                                });
                            $fixColor = $taxFixAppointmentColors[(int)$appointment->id] ?? null;
                            $total     = (float) ($appointment->total_price ?? 0);
                            $paidTotal = (float) $appointment->payments->sum('amount');

                            // old totals
                            $cashPaid  = (float) $appointment->payments->where('method','cash')->sum('amount');
                            $cardPaid  = (float) $appointment->payments->where('method','card')->sum('amount');

                            // ✅ NEW: split cash by tax
                            $cashPaidY = (float) $appointment->payments
                                ->where('method','cash')
                                ->where('tax','Y')
                                ->sum('amount');

                            $cashPaidN = (float) $appointment->payments
                                ->where('method','cash')
                                ->where('tax','N')
                                ->sum('amount');
                        @endphp


                        <tr class="{{ $fixColor ? 'tax-fix-colored-row' : ($hasAddon ? 'table-warning' : '') }}"
                            @if($fixColor) style="--tax-fix-color: {{ $fixColor }};" @endif>
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
                                        'aksiologisi'   => 'ΑΞΙΟΛΟΓΗΣΗ / ΤΗΛ. ΕΠΙΚΟΙΝΩΝΙΑ / ΕΝΗΜΕΡΩΤΙΚΟ',
                                        default         => mb_strtoupper($appointment->status ?? '-', 'UTF-8'),
                                    };
                                @endphp

                                <span class="badge bg-info">
                                    {{ $serviceGreek }}
                                </span>
                            </td>


                            @php $apptPriceColor = $appointmentPaymentColors[(int)$appointment->id] ?? null; @endphp
                            <td class="editable-price"
                                data-id="{{ $appointment->id }}"
                                style="cursor:pointer;">
                                {{ number_format($total, 2, ',', '.') }}
                            </td>

                            @php
                            $lastPay = $appointment->payments->sortByDesc('paid_at')->sortByDesc('id')->first();
                            @endphp
                            <td class="appointment-paid-edit"
                                data-appointment-id="{{ $appointment->id }}"
                                data-original="{{ number_format($paidTotal, 2, ',', '.') }}"
                                data-default-method="{{ $lastPay->method ?? 'cash' }}"
                                data-default-tax="{{ $lastPay->tax ?? 'Y' }}"
                                style="cursor:pointer;{{ $apptPriceColor ? 'background-color:' . $apptPriceColor . ';' : '' }}">

                                {{-- το υπάρχον σου UI (badges/labels) ΜΕΣΑ εδώ όπως είναι --}}
                                @php $isZeroPrice = $total <= 0; @endphp

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
                                            @if($cashPaid > 0)
                                                Μετρητά:
                                                @if($cashPaidY > 0)
                                                    <span>ΜΑ</span> {{ number_format($cashPaidY, 2, ',', '.') }} €
                                                @endif

                                                @if($cashPaidY > 0 && $cashPaidN > 0) · @endif

                                                @if($cashPaidN > 0)
                                                    <span>ΧΑ</span> {{ number_format($cashPaidN, 2, ',', '.') }} €
                                                @endif
                                            @endif

                                            @if($cashPaid > 0 && $cardPaid > 0) · @endif

                                            @if($cardPaid > 0)
                                                Κάρτα: {{ number_format($cardPaid, 2, ',', '.') }} €
                                            @endif
                                        </small>

                                    @endif
                                @endif
                            </td>



                            <td >
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

            



            <div id="tax-fix-oldest" class="border rounded p-3 mb-3" style="background:#fff3cd">
                <h6 class="mb-2">🧾 Διόρθωση παλαιότερων πληρωμών (Μετρητά Χωρίς Απόδειξη ➜ Με Απόδειξη)</h6>

                <form method="POST" action="{{ route('customers.payments.taxFixOldest', $customer) }}"
                    onsubmit="return confirm('Σίγουρα; Θα γίνει διόρθωση και θα προστεθούν νέα payments των 5€ ανά εγγραφή.');">
                @csrf

                <div class="row g-2 mt-3 align-items-end">

                    <div class="col-md-2">
                    <label class="form-label">Ποσό</label>
                    <input type="number" name="fix_amount" min="5" step="5" class="form-control"
                            placeholder="π.χ. 5,10,15..." required>
                    @error('fix_amount')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    </div>

                    {{-- ✅ run_at date only --}}
                    <div class="col-md-2">
                    <label class="form-label">Ημερομηνία Εκτέλεσης</label>
                    <input type="date" name="run_at" class="form-control" required
                            value="{{ now()->toDateString() }}">
                    @error('run_at')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    </div>

                    {{-- ✅ method --}}
                    <div class="col-md-2">
                    <label class="form-label">Τρόπος</label>
                    <select name="method" class="form-select" required>
                        <option value="cash">Μετρητά</option>
                        <option value="card">Κάρτα</option>
                    </select>
                    @error('method')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    </div>

                    <div class="col-md-4">
                    <label class="form-label">Σχόλιο</label>
                    <input type="text" name="comment" class="form-control" maxlength="1000"
                            placeholder="προαιρετικό σχόλιο...">
                    @error('comment')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    </div>

                    <div class="col-md-2">
                    <button class="btn btn-warning w-100" type="submit">Εκτέλεση Διόρθωσης</button>
                    </div>

                </div>
                </form>


            </div>
        </div>
    </div>

    {{-- ===================== ΑΡΧΕΙΑ ΠΕΛΑΤΗ ===================== --}}
    <div class="card mt-3" id="files-section">
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
                                                action="{{ route('customers.files.store', $customer) }}#files-section"
                                                enctype="multipart/form-data"
                                                class="row g-2 align-items-end">
                                                @csrf

                                                <div class="col-md-4">
                                                    <input id="customerFileInput" type="file" name="file[]" multiple class="form-control d-none"
                                                        onchange="(function(el){ const files = el.files || []; const text = files.length === 0 ? '' : (files.length === 1 ? files[0].name : 'Επιλέχθηκαν ' + files.length + ' αρχεία'); document.getElementById('customerFileName').value = text; })(this);">
                                                    <input id="customerFileName" type="text" class="form-control" placeholder="Δεν επιλέχθηκε αρχείο" readonly>
                                                    @error('file')
                                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                                    @enderror
                                                    @error('file.*')
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
                                                                        href="{{ route('customers.files.view', [
                                                                            'customer' => $customer->id,
                                                                            'file' => $f->id,
                                                                            'customerName' => trim(mb_strtoupper(trim(($customer->last_name ?? '') . ' ' . ($customer->first_name ?? '')), 'UTF-8')) ?: ('ΠΕΛΑΤΗΣ ' . $customer->id),
                                                                        ]) }}">
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
        // ✅ Payment dot click → highlight related appointment rows
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (e) {
                const dot = e.target.closest('.payment-dot-link');
                if (!dot) return;

                const ids = (dot.dataset.apptIds || '').split(',').map(s => s.trim()).filter(Boolean);
                const color = dot.dataset.color || '#ffe8a3';

                if (!ids.length) return;

                // smooth scroll στο πρώτο σχετικό ραντεβού
                const firstCell = document.querySelector(`.appointment-paid-edit[data-appointment-id="${ids[0]}"]`);
                const firstRow = firstCell ? firstCell.closest('tr') : null;

                if (firstRow) {
                    firstRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    // fallback
                    const section = document.getElementById('appointments-section');
                    if (section) {
                        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }

                // flash-highlight the matching rows
                ids.forEach(id => {
                    // find the price cell (appointment-paid-edit) for this appointment
                    const cell = document.querySelector(`.appointment-paid-edit[data-appointment-id="${id}"]`);
                    if (!cell) return;
                    const row = cell.closest('tr');
                    if (!row) return;

                    row.style.transition = 'outline 0.1s';
                    row.style.outline = `3px solid ${color}`;
                    row.style.outlineOffset = '-2px';

                    setTimeout(() => {
                        row.style.outline = 'none';
                    }, 2200);
                });
            });
        });

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
                // Μετακίνηση κέρσορα στην αρχή για textarea
                if (type === 'textarea') {
                    input.setSelectionRange(0, 0);
                    input.scrollTop = 0;
                    input.scrollLeft = 0;
                    requestAnimationFrame(() => {
                        input.scrollTop = 0;
                        input.scrollLeft = 0;
                    });
                }
                activeInput = input;

                const restore = () => {
                    el.textContent = el.dataset._original || '-';
                    activeInput = null;
                };

                const save = () => {
                    const newValue = input.value;

                    // Για textarea: ο περιορισμός "χωρίς διαγραφή" ισχύει ΜΟΝΟ για customer.informations
                    if (type === 'textarea' && model === 'customer' && field === 'informations') {
                        const baseValue = (originalValue ?? '').toString();
                        const candidate = (newValue ?? '').toString();

                        // Το αρχικό κείμενο πρέπει να παραμένει αυτούσιο μέσα στο νέο
                        if (baseValue !== '' && !candidate.includes(baseValue)) {
                            alert('Δεν επιτρέπεται διαγραφή του υπάρχοντος κειμένου. Μπορείτε να προσθέσετε μόνο στην αρχή ή/και στο τέλος.');
                            restore();
                            return;
                        }
                    }

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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) return;

    let active = null;
    let saving = false; // ✅ για να μη γίνει διπλό save

    document.addEventListener('dblclick', function (e) {
        const el = e.target.closest('.payment-day-total-edit');
        if (!el) return;

        e.preventDefault();
        e.stopPropagation();

        if (active || saving) return;

        const customerId   = el.dataset.customerId;
        const dayKey       = el.dataset.dayKey; // "2026-01-19" or "no-date"
        const originalText = (el.textContent || '').trim();

        const numericRaw = originalText
            .replace(/[^\d,.-]/g, '')
            .replace('.', '')
            .replace(',', '.');

        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.01';
        input.min = '0';
        input.className = 'form-control form-control-sm d-inline-block';
        input.style.width = '90px';
        input.value = numericRaw ? parseFloat(numericRaw) : 0;

        el.innerHTML = '';
        el.appendChild(input);
        input.focus();
        active = input;

        const restore = () => {
            el.textContent = originalText;
            active = null;
            saving = false;
        };

        const endpoint = "{{ route('customers.payments.updateDayTotal', ['customer' => '__ID__']) }}"
            .replace('__ID__', customerId);

        const save = () => {
            if (saving) return;          // ✅ guard
            saving = true;

            const newVal = input.value;

            el.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    day_key: dayKey,
                    total: newVal
                })
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw data;
                return data;
            })
            .then(() => {
                // ✅ ΜΕΤΑ ΤΗΝ ΕΠΙΤΥΧΙΑ: full reload για να ενημερωθούν totals/lines
                window.location.reload();
            })
            .catch(err => {
                console.error(err);
                alert(err?.message || 'Σφάλμα αποθήκευσης.');
                restore();
            });
        };

        input.addEventListener('blur', save);

        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
            if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
        });
    });
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) return;

    let active = null;

    document.addEventListener('dblclick', function (e) {
        const cell = e.target.closest('.appointment-paid-edit');
        if (!cell) return;
        if (active) return;

        e.preventDefault();

        const appointmentId = cell.dataset.appointmentId;
        const originalHTML  = cell.innerHTML;

        const raw = (cell.dataset.original || '0')
            .replace(/[^\d,.-]/g,'')
            .replace('.', '')
            .replace(',', '.');

        // --- UI elements
        const wrap = document.createElement('div');
        wrap.className = 'd-flex gap-2 align-items-center';

        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.01';
        input.min = '0';
        input.className = 'form-control form-control-sm';
        input.style.width = '120px';
        input.value = raw ? parseFloat(raw) : 0;

        const select = document.createElement('select');
        select.className = 'form-select form-select-sm';
        select.style.width = '180px';
        select.innerHTML = `
            <option value="cash|Y">Μετρητά (ΜΑ)</option>
            <option value="cash|N">Μετρητά (ΧΑ)</option>
            <option value="card|Y">Κάρτα</option>
        `;

        // προεπιλογή: αν έχει data-default-method/tax (αν θες να τα βάλεις από blade), αλλιώς cash|Y
        const defaultMethod = cell.dataset.defaultMethod || 'cash';
        const defaultTax    = cell.dataset.defaultTax || 'Y';
        select.value = `${defaultMethod}|${defaultTax}`;

        const btnSave = document.createElement('button');
        btnSave.type = 'button';
        btnSave.className = 'btn btn-sm btn-success';
        btnSave.textContent = 'OK';

        const btnCancel = document.createElement('button');
        btnCancel.type = 'button';
        btnCancel.className = 'btn btn-sm btn-outline-secondary';
        btnCancel.textContent = '✕';

        wrap.appendChild(input);
        wrap.appendChild(select);
        wrap.appendChild(btnSave);
        wrap.appendChild(btnCancel);

        cell.innerHTML = '';
        cell.appendChild(wrap);

        active = { cell, input, select };

        const restore = () => {
            cell.innerHTML = originalHTML;
            active = null;
        };

        const save = () => {
            const newVal = (input.value ?? '').toString().trim();
            if (newVal === '') return restore();

            const [method, tax] = (select.value || 'cash|Y').split('|');

            cell.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

            fetch(`{{ url('/appointments') }}/${appointmentId}/update-paid-total`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    paid_total: newVal,
                    method: method,
                    tax: tax
                })
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw data;
                return data;
            })
            .then(() => window.location.reload())
            .catch(err => {
                console.error(err);
                alert(err?.message || 'Σφάλμα αποθήκευσης.');
                restore();
            });
        };

        btnCancel.addEventListener('click', restore);
        btnSave.addEventListener('click', save);

        input.focus();
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); save(); }
            if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
        });

        select.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
        });
    });
});
</script>



<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) return;

    let active = null;

    document.addEventListener('dblclick', function (e) {
        const el = e.target.closest('.tax-fix-log-edit');
        if (!el) return;
        if (active) return;

        e.preventDefault();
        e.stopPropagation();

        const logId = el.dataset.logId;
        const originalText = (el.textContent || '').trim();

        const raw = (el.dataset.original || '0')
            .replace(/[^\d,.-]/g,'')
            .replace('.', '')
            .replace(',', '.');

        const input = document.createElement('input');
        input.type = 'number';
        input.step = '5';
        input.min = '0';
        input.className = 'form-control form-control-sm d-inline-block';
        input.style.width = '110px';
        input.value = raw ? parseFloat(raw) : 0;

        const wrap = document.createElement('span');
        wrap.className = 'd-inline-flex gap-1 align-items-center';

        const ok = document.createElement('button');
        ok.type = 'button';
        ok.className = 'btn btn-sm btn-success';
        ok.textContent = 'OK';

        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'btn btn-sm btn-outline-secondary';
        cancel.textContent = '✕';

        wrap.appendChild(input);
        wrap.appendChild(ok);
        wrap.appendChild(cancel);

        el.innerHTML = '';
        el.appendChild(wrap);
        active = { el, input };

        const restore = () => {
            el.textContent = originalText;
            active = null;
        };

        const save = () => {
            const v = (input.value ?? '').toString().trim();
            if (v === '') return restore();

            el.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

            fetch(`{{ route('customers.taxFixLogs.updateAmount', ['customer' => $customer->id, 'log' => '__LOG__']) }}`.replace('__LOG__', logId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ fix_amount: v })
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw data;
                return data;
            })
            .then(() => window.location.reload())
            .catch(err => {
                console.error(err);
                alert(err?.message || 'Σφάλμα αποθήκευσης.');
                restore();
            });
        };

        cancel.addEventListener('click', restore);
        ok.addEventListener('click', save);

        input.focus();
        input.addEventListener('keydown', function(ev){
            if (ev.key === 'Enter') { ev.preventDefault(); save(); }
            if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) return;

    let active = null;
    let saving = false;

    document.addEventListener('dblclick', function (e) {
        const el = e.target.closest('.payment-day-date-edit');
        if (!el) return;

        e.preventDefault();
        e.stopPropagation();

        if (active || saving) return;

        const customerId   = el.dataset.customerId;
        const dayKey       = el.dataset.dayKey; // "YYYY-MM-DD" ή "no-date"
        const originalText = (el.textContent || '').trim();

        // default value for input
        const originalDate = (dayKey === 'no-date') ? '' : dayKey;

        const input = document.createElement('input');
        input.type = 'date';
        input.className = 'form-control form-control-sm d-inline-block';
        input.style.width = '150px';
        input.value = originalDate;

        el.innerHTML = '';
        el.appendChild(input);
        input.focus();
        active = input;

        const restore = () => {
            el.textContent = originalText;
            active = null;
            saving = false;
        };

        const endpoint = "{{ route('customers.payments.updateDayDate', ['customer' => '__ID__']) }}"
            .replace('__ID__', customerId);

        const save = () => {
            if (saving) return;
            saving = true;

            const newDate = (input.value ?? '').toString().trim(); // "" ή "YYYY-MM-DD"

            el.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    day_key: dayKey,
                    new_date: newDate // "" => χωρίς ημερομηνία (NULL)
                })
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw data;
                return data;
            })
            .then(() => window.location.reload())
            .catch(err => {
                console.error(err);
                alert(err?.message || 'Σφάλμα αποθήκευσης.');
                restore();
            });
        };

        input.addEventListener('blur', save);
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
            if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
        });
    });
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) return;

    let active = null;

    // -----------------------------
    // ✅ EDIT RUN_AT (DATE)
    // -----------------------------
    document.addEventListener('dblclick', function (e) {
        const el = e.target.closest('.tax-fix-log-date-edit');
        if (!el) return;
        if (active) return;

        e.preventDefault();
        e.stopPropagation();

        const logId = el.dataset.logId;
        const originalText = (el.textContent || '').trim();
        const originalVal  = el.dataset.original || '';

        const input = document.createElement('input');
        input.type = 'date';
        input.className = 'form-control form-control-sm d-inline-block';
        input.style.width = '150px';
        input.value = originalVal;

        el.innerHTML = '';
        el.appendChild(input);
        input.focus();
        active = { el, input };

        const restore = () => {
            el.textContent = originalText || '-';
            active = null;
        };

        const save = () => {
            const v = (input.value ?? '').toString().trim();
            if (!v) return restore();

            el.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

            fetch(`{{ route('customers.taxFixLogs.updateRunAt', ['customer' => $customer->id, 'log' => '__LOG__']) }}`.replace('__LOG__', logId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ run_at: v })
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw data;
                return data;
            })
            .then((data) => {
                // ενημέρωση label χωρίς reload
                el.textContent = data.label || originalText;
                el.dataset.original = data.value || v;
                active = null;
            })
            .catch(err => {
                console.error(err);
                alert(err?.message || 'Σφάλμα αποθήκευσης.');
                restore();
            });
        };

        input.addEventListener('blur', save);
        input.addEventListener('keydown', function(ev){
            if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
            if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
        });
    });

    // -----------------------------
    // ✅ EDIT COMMENT (TEXT)
    // -----------------------------
    document.addEventListener('dblclick', function (e) {
        const el = e.target.closest('.tax-fix-log-comment-edit');
        if (!el) return;
        if (active) return;

        e.preventDefault();
        e.stopPropagation();

        const logId = el.dataset.logId;
        const originalText = (el.textContent || '').trim();
        const originalVal  = el.dataset.original || '';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm';
        input.style.width = '100%';
        input.value = originalVal;

        el.innerHTML = '';
        el.appendChild(input);
        input.focus();
        active = { el, input };

        const restore = () => {
            el.textContent = originalText || '-';
            active = null;
        };

        const save = () => {
            const v = (input.value ?? '').toString();

            el.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

            fetch(`{{ route('customers.taxFixLogs.updateComment', ['customer' => $customer->id, 'log' => '__LOG__']) }}`.replace('__LOG__', logId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ comment: v })
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw data;
                return data;
            })
            .then((data) => {
                el.textContent = data.label ?? '-';
                el.dataset.original = (data.value ?? '');
                active = null;
            })
            .catch(err => {
                console.error(err);
                alert(err?.message || 'Σφάλμα αποθήκευσης.');
                restore();
            });
        };

        input.addEventListener('blur', save);
        input.addEventListener('keydown', function(ev){
            if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
            if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
        });
    });

});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) return;

    let active = null;

    document.addEventListener('dblclick', function (e) {
        const el = e.target.closest('.prepayment-amount-edit');
        if (!el || active) return;

        e.preventDefault();
        e.stopPropagation();

        const customerId = el.dataset.customerId;
        const prepaymentId = el.dataset.prepaymentId;
        const originalText = (el.textContent || '').trim();
        const originalVal = el.dataset.original || '0';

        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.01';
        input.min = '0';
        input.className = 'form-control form-control-sm d-inline-block';
        input.style.width = '110px';
        input.value = parseFloat(originalVal) || 0;

        el.innerHTML = '';
        el.appendChild(input);
        input.focus();
        active = { el, input };

        const restore = () => {
            el.textContent = originalText;
            active = null;
        };

        const save = () => {
            const v = (input.value ?? '').toString().trim();
            if (v === '') return restore();

            el.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

            fetch(`{{ route('customers.prepayments.updateAmount', ['customer' => $customer->id, 'prepayment' => '__PREPAY__']) }}`.replace('__PREPAY__', prepaymentId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ amount: v })
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw data;
                return data;
            })
            .then(() => window.location.reload())
            .catch(err => {
                console.error(err);
                alert(err?.message || 'Σφάλμα αποθήκευσης.');
                restore();
            });
        };

        input.addEventListener('blur', save);
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
            if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
        });
    });

    document.addEventListener('dblclick', function (e) {
        const el = e.target.closest('.prepayment-date-edit');
        if (!el || active) return;

        e.preventDefault();
        e.stopPropagation();

        const prepaymentId = el.dataset.prepaymentId;
        const originalText = (el.textContent || '').trim();
        const originalVal = el.dataset.original || '';

        const input = document.createElement('input');
        input.type = 'date';
        input.className = 'form-control form-control-sm d-inline-block';
        input.style.width = '145px';
        input.value = originalVal;

        el.innerHTML = '';
        el.appendChild(input);
        input.focus();
        active = { el, input };

        const restore = () => {
            el.textContent = originalText || '-';
            active = null;
        };

        const save = () => {
            const v = (input.value ?? '').toString().trim();
            if (!v) return restore();

            el.innerHTML = '<span class="text-muted">Αποθήκευση…</span>';

            fetch(`{{ route('customers.prepayments.updateDate', ['customer' => $customer->id, 'prepayment' => '__PREPAY__']) }}`.replace('__PREPAY__', prepaymentId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ paid_at: v })
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw data;
                return data;
            })
            .then((data) => {
                el.textContent = data.label || originalText;
                el.dataset.original = data.value || v;
                active = null;
            })
            .catch(err => {
                console.error(err);
                alert(err?.message || 'Σφάλμα αποθήκευσης.');
                restore();
            });
        };

        input.addEventListener('blur', save);
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
            if (ev.key === 'Escape') { ev.preventDefault(); restore(); }
        });
    });
});
</script>


@endsection
