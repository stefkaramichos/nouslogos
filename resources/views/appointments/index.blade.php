{{-- resources/views/appointments/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Ραντεβού')

@section('content')
@php
    use Carbon\Carbon;

    // Controller filters
    $view = $filters['view'] ?? request('view', 'week'); // week|day|month|table
    if (!in_array($view, ['week','day','month','table'], true)) $view = 'week';

    $day   = $filters['day'] ?? request('day', now()->toDateString());
    $month = $filters['month'] ?? request('month', now()->format('Y-m'));

    // Προαιρετικά: κρατάμε το παλιό range UI (αν το χρησιμοποιείς ακόμα)
    $range = $filters['range'] ?? request('range', 'month');

    // appointments collection (paginator only in table view)
    $appts = $appointments instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? collect($appointments->items())
        : collect($appointments);

    // helper: status label to greek caps
    $statusMap = [
        'logotherapia' => 'ΛΟΓΟΘΕΡΑΠΕΙΑ',
        'psixotherapia'=> 'ΨΥΧΟΘΕΡΑΠΕΙΑ',
        'ergotherapia' => 'ΕΡΓΟΘΕΡΑΠΕΙΑ',
        'omadiki'      => 'ΟΜΑΔΙΚΗ',
        'eidikos'      => 'ΕΙΔΙΚΟΣ ΠΑΙΔΑΓΩΓΟΣ',
        'aksiologisi'  => 'ΑΞΙΟΛΟΓΗΣΗ',
    ];

    // weekDays MUST come from controller (for correct week header)
    $weekDays = isset($weekDays) && $weekDays
        ? collect($weekDays)
        : collect(); // fallback

    // group appointments by day for quick access (week grid)
    $apptsByDay = $appts->groupBy(function($a){
        return optional($a->start_time)->toDateString();
    });

    // day list
    $dayAppointments = $appts->sortBy('start_time')->values();

    // hours (07:00 - 22:00)
    $startHour = 11;
    $endHour   = 21;

    // keep query string (for view mode links)
    $qs = request()->query();

    // helper to build route with merged query
    $mk = function(string $v) use ($qs) {
        return array_merge($qs, ['view' => $v, 'nav' => null]); // nav removed
    };
@endphp

<style>
    /* Calendar grid (Google-ish) */
    .gc-toolbar { gap: .5rem; }
    .gc-grid { border: 1px solid rgba(0,0,0,.1); border-radius: .5rem; overflow: hidden; }
    .gc-head { background: #f8f9fa; border-bottom: 1px solid rgba(0,0,0,.08); }
    .gc-head .gc-day { padding: .5rem .5rem; border-left: 1px solid rgba(0,0,0,.06); }
    .gc-head .gc-day:first-child { border-left: 0; }
    .gc-body { display: grid; grid-template-columns: 70px 1fr; }
    .gc-hours { background: #fff; border-right: 1px solid rgba(0,0,0,.08); }
    .gc-hour { height: 136px; padding: .25rem .25rem; font-size: .75rem; color: #6c757d; border-bottom: 1px solid rgba(0,0,0,.06); }
    .gc-cells { display: grid; grid-template-columns: repeat(7, 1fr); }
    .gc-cell {
        height: 136px;
        border-bottom: 1px solid rgba(0,0,0,.06);
        border-left: 1px solid rgba(0,0,0,.06);
        position: relative;
        padding: 2px;
        background: #fff;
        overflow: auto;
    }
    .gc-cell:nth-child(7n+1){ border-left: 0; }
    .gc-event {
        font-size: .72rem;
        line-height: 1.1;
        border: 1px solid rgba(13,110,253,.25);
        background: rgba(13,110,253,.10);
        border-radius: .4rem;
        padding: .25rem .35rem;
        margin-bottom: 2px;
        cursor: pointer;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }
    .gc-event .t { font-weight: 600; }
    .gc-event .s { opacity: .8; }
    .gc-event.paid { border-color: rgba(25,135,84,.35); background: rgba(25,135,84,.10); }
    .gc-event.unpaid{ border-color: rgba(220,53,69,.35); background: rgba(220,53,69,.10); }
    .gc-event.partial{ border-color: rgba(255,193,7,.55); background: rgba(255,193,7,.12); }
    
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Ραντεβού</span>

        <div class="d-flex align-items-center flex-wrap gc-toolbar">
            {{-- View mode --}}
            <div class="btn-group btn-group-sm" role="group" aria-label="View mode">
                <a class="btn {{ $view==='week' ? 'btn-primary' : 'btn-outline-primary' }}"
                   href="{{ route('appointments.index', $mk('week')) }}">Εβδομάδα</a>
                <a class="btn {{ $view==='day' ? 'btn-primary' : 'btn-outline-primary' }}"
                   href="{{ route('appointments.index', $mk('day')) }}">Ημέρα</a>
                <a class="btn {{ $view==='table' ? 'btn-primary' : 'btn-outline-primary' }}"
                   href="{{ route('appointments.index', $mk('table')) }}">Πίνακας</a>
            </div>

            <a href="{{ route('appointments.create') }}" class="btn btn-primary btn-sm">
                + Προσθήκη Ραντεβού
            </a>
        </div>
    </div>

    <div class="card-body">

        {{-- ===================== FILTERS ===================== --}}
        <form method="GET" action="{{ route('appointments.index') }}">
            <input type="hidden" name="view" value="{{ $view }}">

            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Περιστατικό</label>
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
                        <option value="aksiologisi" @selected($st === 'aksiologisi')>Αξιολόγηση</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mt-2">
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

                <div class="col-md-6 d-flex justify-content-end align-items-end">
                    <button class="btn btn-outline-primary me-2">Εφαρμογή Φίλτρων</button>
                    <a href="{{ route('appointments.index', ['view' => $view]) }}" class="btn btn-outline-secondary">
                        Καθαρισμός
                    </a>
                </div>
            </div>
        </form>

        {{-- ===================== PERIOD BAR (uses controller URLs) ===================== --}}
        <div class="mb-2 mt-3">
            <hr>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Περίοδος</label>

                    {{-- Αν είσαι σε month view, δείξε month input. Αν είσαι day/week δείξε date --}}
                    @if($view === 'month')
                        <form method="GET" action="{{ route('appointments.index') }}">
                            @foreach(request()->except(['month','nav']) as $k=>$v)
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endforeach
                            <input type="hidden" name="view" value="month">
                            <input type="month" name="month" class="form-control"
                                   value="{{ $month }}"
                                   onchange="this.form.submit()">
                        </form>
                    @else
                        <form method="GET" action="{{ route('appointments.index') }}">
                            @foreach(request()->except(['day','nav']) as $k=>$v)
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endforeach
                            <input type="hidden" name="view" value="{{ $view }}">
                            <input type="date" name="day" class="form-control"
                                   value="{{ $day }}"
                                   onchange="this.form.submit()">
                        </form>
                    @endif
                </div>

                <div class="col-md-4 d-flex gap-2 justify-content-end">
                    @if(isset($prevUrl) && isset($nextUrl))
                        <a href="{{ $prevUrl }}" class="btn btn-outline-secondary btn-sm">← Προηγούμενο</a>
                        <a href="{{ $nextUrl }}" class="btn btn-outline-secondary btn-sm">Επόμενο →</a>
                    @endif
                </div>
            </div>

            <div class="mt-2">
                <span class="text-muted">Έχετε επιλέξει:</span>
                <span class="badge bg-dark">{{ $selectedLabel ?? 'Όλα' }}</span>
            </div>
        </div>

        {{-- ===================== VIEWS ===================== --}}
        @if($view === 'table')
            {{-- TABLE --}}
            <div class="table-responsive mt-3">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Ημ/νία & Ώρα</th>
                        <th>Περιστατικό</th>
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
                            $total     = (float) ($appointment->total_price ?? 0);
                            $paidTotal = (float) $appointment->payments->sum('amount');
                            $cashPaid  = (float) $appointment->payments->where('method','cash')->sum('amount');
                            $cardPaid  = (float) $appointment->payments->where('method','card')->sum('amount');

                            $statusTokens = collect(explode(',', (string)($appointment->status ?? '')))
                                ->map(fn($x)=>trim($x))
                                ->filter()
                                ->values();

                            $serviceLabel = $statusTokens->map(fn($t)=>$statusMap[$t] ?? mb_strtoupper($t))->implode(', ');
                        @endphp

                        <tr>
                            <td>{{ $appointment->start_time?->format('d/m/Y H:i') }}</td>

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

                            <td>
                                @if($appointment->professional)
                                    <a href="{{ route('professionals.show', $appointment->professional) }}">
                                        {{ $appointment->professional->last_name }} {{ $appointment->professional->first_name }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>{{ $appointment->company->name ?? '-' }}</td>

                            <td>
                                @if($serviceLabel)
                                    <span class="badge bg-dark">{{ $serviceLabel }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>{{ number_format($total, 2, ',', '.') }}</td>

                            <td>
                                @php $isZeroPrice = $total <= 0; @endphp

                                @if($isZeroPrice)
                                    <span class="badge bg-success">Πλήρως πληρωμένο</span>
                                    <small class="text-muted d-block">Μηδενική χρέωση</small>

                                @elseif($paidTotal <= 0)
                                    <span class="badge bg-danger">Απλήρωτο</span>

                                @elseif($paidTotal < $total)
                                    <span class="badge bg-warning text-dark d-block mb-1">
                                        Μερική πληρωμή {{ number_format($paidTotal, 2, ',', '.') }} €
                                    </span>

                                    <small class="text-muted d-block">
                                        @if($cashPaid > 0) Μετρητά: {{ number_format($cashPaid, 2, ',', '.') }} € @endif
                                        @if($cashPaid > 0 && $cardPaid > 0) · @endif
                                        @if($cardPaid > 0) Κάρτα: {{ number_format($cardPaid, 2, ',', '.') }} € @endif
                                    </small>

                                @else
                                    <span class="badge bg-success d-block mb-1">
                                        Πλήρως πληρωμένο {{ number_format($paidTotal, 2, ',', '.') }} €
                                    </span>

                                    <small class="text-muted d-block">
                                        @if($cashPaid > 0) Μετρητά: {{ number_format($cashPaid, 2, ',', '.') }} € @endif
                                        @if($cashPaid > 0 && $cardPaid > 0) · @endif
                                        @if($cardPaid > 0) Κάρτα: {{ number_format($cardPaid, 2, ',', '.') }} € @endif
                                    </small>
                                @endif
                            </td>

                            <td title="{{ $appointment->notes }}">
                                {{ $appointment->notes ? \Illuminate\Support\Str::limit($appointment->notes, 30) : '-' }}
                            </td>

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
            </div>

            <div class="mt-3 d-flex justify-content-center">
                {{ $appointments->withQueryString()->links() }}
            </div>

        @elseif($view === 'day')
            {{-- DAY (agenda-like) --}}
            <div class="mt-3">
                @if($dayAppointments->isEmpty())
                    <div class="text-center text-muted py-4">Δεν υπάρχουν ραντεβού.</div>
                @else
                    <div class="list-group">
                        @foreach($dayAppointments as $appointment)
                            @php
                                $total = (float)($appointment->total_price ?? 0);
                                $paid  = (float)$appointment->payments->sum('amount');

                                $statusTokens = collect(explode(',', (string)($appointment->status ?? '')))
                                    ->map(fn($x)=>trim($x))->filter();
                                $serviceLabel = $statusTokens->map(fn($t)=>$statusMap[$t] ?? mb_strtoupper($t))->implode(', ');
                            @endphp

                            <a href="{{ route('appointments.edit', $appointment) }}"
                               class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fw-semibold">
                                            {{ $appointment->start_time?->format('d/m/Y H:i') }}
                                            · {{ $appointment->customer?->last_name }} {{ $appointment->customer?->first_name }}
                                        </div>
                                        <div class="text-muted small">
                                            {{ $appointment->professional?->last_name }} {{ $appointment->professional?->first_name }}
                                            · {{ $appointment->company?->name ?? '-' }}
                                            @if($serviceLabel) · {{ $serviceLabel }} @endif
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-dark">{{ number_format($total,2,',','.') }} €</span>
                                        <div class="small text-muted">
                                            @if($total<=0) Μηδενική χρέωση
                                            @elseif($paid<=0) Απλήρωτο
                                            @elseif($paid<$total) Μερικό
                                            @else Πληρωμένο
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

        @else
            {{-- WEEK GRID --}}
            <div class="mt-3 gc-grid">
                <div class="gc-head">
                    <div class="d-grid" style="grid-template-columns: 70px repeat(7,1fr);">
                        <div class="p-2"></div>

                        {{-- ✅ IMPORTANT: use $weekDays FROM CONTROLLER --}}
                        @foreach($weekDays as $d)
                            @php $d = $d instanceof Carbon ? $d : Carbon::parse($d); @endphp
                            <div class="gc-day">
                                <div class="fw-semibold">{{ $d->locale('el')->translatedFormat('D') }}</div>
                                <div class="text-muted small">{{ $d->format('d/m') }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="gc-body">
                    <div class="gc-hours">
                        @for($h=$startHour; $h<=$endHour; $h++)
                            <div class="gc-hour">{{ str_pad($h,2,'0',STR_PAD_LEFT) }}:00</div>
                        @endfor
                    </div>

                    <div class="gc-cells">
                        @for($h=$startHour; $h<=$endHour; $h++)
                            @foreach($weekDays as $d)
                                @php
                                    $d = $d instanceof Carbon ? $d : Carbon::parse($d);
                                    $dateKey = $d->toDateString();

                                    $slotAppts = ($apptsByDay[$dateKey] ?? collect())
                                        ->filter(function($a) use ($h){
                                            if(!$a->start_time) return false;
                                            return (int)$a->start_time->format('H') === (int)$h;
                                        })
                                        ->sortBy('start_time')
                                        ->values();
                                @endphp

                                <div class="gc-cell">
                                    @foreach($slotAppts as $a)
                                        @php
                                            $total = (float)($a->total_price ?? 0);
                                            $paid  = (float)$a->payments->sum('amount');
                                            $cls = $total<=0 || $paid>=$total ? 'paid' : ($paid>0 ? 'partial' : 'unpaid');

                                            $statusTokens = collect(explode(',', (string)($a->status ?? '')))
                                                ->map(fn($x)=>trim($x))->filter();
                                            $serviceLabel = $statusTokens->map(fn($t)=>$statusMap[$t] ?? mb_strtoupper($t))->implode(', ');

                                            $title = trim(($a->customer?->last_name ?? '').' '.($a->customer?->first_name ?? ''));
                                            $sub   = trim(($a->professional?->last_name ?? '').' '.($a->professional?->first_name ?? ''));
                                        @endphp

                                        <div class="gc-event {{ $cls }}"
                                             data-bs-toggle="modal"
                                             data-bs-target="#apptModal"
                                             data-id="{{ $a->id }}"
                                             data-time="{{ $a->start_time?->format('d/m/Y H:i') }}"
                                             data-customer="{{ $title }}"
                                             data-professional="{{ $sub }}"
                                             data-company="{{ $a->company?->name ?? '-' }}"
                                             data-service="{{ $serviceLabel }}"
                                             data-total="{{ number_format($total,2,',','.') }} €"
                                             data-paid="{{ number_format($paid,2,',','.') }} €"
                                             data-notes="{{ $a->notes ?? '' }}"
                                             data-edit-url="{{ route('appointments.edit', $a) }}"
                                        >
                                            <span class="t">{{ $a->start_time?->format('H:i') }} {{ $title }}</span>
                                            <span class="s"> · {{ $sub }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        @endfor
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- ===================== MODAL (click event) ===================== --}}
<div class="modal fade" id="apptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Πληροφορίες Ραντεβού</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="font-size:.9rem;">
        <div class="mb-1"><strong>Ημ/νία & Ώρα:</strong> <span id="m_time"></span></div>
        <div class="mb-1"><strong>Περιστατικό:</strong> <span id="m_customer"></span></div>
        <div class="mb-1"><strong>Επαγγελματίας:</strong> <span id="m_professional"></span></div>
        <div class="mb-1"><strong>Εταιρεία:</strong> <span id="m_company"></span></div>
        <div class="mb-1"><strong>Υπηρεσία:</strong> <span id="m_service"></span></div>
        <div class="mb-1"><strong>Σύνολο:</strong> <span id="m_total"></span></div>
        <div class="mb-1"><strong>Πληρωμένο:</strong> <span id="m_paid"></span></div>
        <div class="mb-1"><strong>Σημειώσεις:</strong> <span id="m_notes"></span></div>
      </div>
      <div class="modal-footer">
        <a href="#" id="m_edit" class="btn btn-primary btn-sm">Άνοιγμα</a>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Κλείσιμο</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const modal = document.getElementById('apptModal');
    if(!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if(!btn) return;

        document.getElementById('m_time').textContent = btn.dataset.time || '-';
        document.getElementById('m_customer').textContent = btn.dataset.customer || '-';
        document.getElementById('m_professional').textContent = btn.dataset.professional || '-';
        document.getElementById('m_company').textContent = btn.dataset.company || '-';
        document.getElementById('m_service').textContent = btn.dataset.service || '-';
        document.getElementById('m_total').textContent = btn.dataset.total || '-';
        document.getElementById('m_paid').textContent = btn.dataset.paid || '-';
        document.getElementById('m_notes').textContent = btn.dataset.notes || '-';

        const editUrl = btn.dataset.editUrl || '#';
        document.getElementById('m_edit').setAttribute('href', editUrl);
    });
});
</script>
@endsection
