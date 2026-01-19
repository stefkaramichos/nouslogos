@extends('layouts.app')

@section('title', 'Εκκαθάριση Συνεταίρων')

@section('content')

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Εκκαθάριση Συνεταίρων</span>
        </div>

        <div class="card-body">

            @if(session('success'))
                <div class="alert alert-success mb-3">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Φίλτρα ΜΗΝΩΝ --}}
            <form method="GET" action="{{ route('settlements.index') }}" class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Από Μήνα</label>
                    <input type="month"
                           name="from_month"
                           class="form-control"
                           value="{{ $filters['from_month'] ?? '' }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Έως Μήνα</label>
                    <input type="month"
                           name="to_month"
                           class="form-control"
                           value="{{ $filters['to_month'] ?? '' }}">
                </div>

                <div class="col-md-4 d-flex align-items-end justify-content-end">
                    <button class="btn btn-outline-primary me-2">Εφαρμογή</button>
                    <a href="{{ route('settlements.index') }}" class="btn btn-outline-secondary">Τρέχων Μήνας</a>
                </div>
            </form>

            <form method="POST" action="{{ route('settlements.store') }}" class="mb-3">
                @csrf
                <input type="hidden" name="from_month" value="{{ $filters['from_month'] ?? '' }}">
                <input type="hidden" name="to_month" value="{{ $filters['to_month'] ?? '' }}">

                <button type="submit"
                        class="btn btn-success"
                        @disabled($isSettled)>
                    Εκκαθάριση
                </button>

                @if($isSettled)
                    <span class="badge bg-success ms-2">Τα ποσά έχουν εκκαθαριστεί</span>
                @else
                    <span class="badge bg-warning text-dark ms-2">Τα ποσά ΔΕΝ έχουν εκκαθαριστεί</span>
                @endif
            </form>


            {{-- Συνοπτικά --}}
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <h6 class="text-muted mb-1">Συνολικό ποσό εισπράξεων</h6>
                        <strong>{{ number_format($totalAmount, 2, ',', '.') }} €</strong>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <h6 class="text-muted mb-1">Ποσό επιχείρησης στην Τράπεζα</h6>
                        <strong>{{ number_format($companyBankTotal, 2, ',', '.') }} €</strong><br>
                        <small class="text-muted ">
                            Μετρητά προς κατάθεση:
                            <span class="badge bg-success fs-6">
                                {{ number_format($cashToBank, 2, ',', '.') }} €
                            </span><br>
                            Πληρωμές με κάρτα (ήδη στην τράπεζα):
                            {{ number_format($cardTotal, 2, ',', '.') }} €
                        </small>
                    </div>
                </div>

                
                {{-- Κρατάμε το κουτί "Προσωπικά ραντεβού" όπως είναι (αν θες το αφαιρούμε) --}}
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <h6 class="text-muted mb-1">Προσωπικά ραντεβού</h6>
                        <small class="text-muted d-block">Γιάννης #1:</small>
                        <strong>{{ number_format($partner1Personal, 2, ',', '.') }} €</strong><br>
                        <small class="text-muted d-block mt-2">Ελένη #2:</small>
                        <strong>{{ number_format($partner2Personal, 2, ',', '.') }} €</strong>
                    </div>
                </div>
                {{-- ΕΔΩ ΑΝΤΙ ΓΙΑ "Μετρητά χωρίς απόδειξη" -> ΕΚΚΑΘΑΡΙΣΕΙΣ --}}
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <h6 class="text-muted mb-2">Εκκαθαρίσεις (επιλεγμένοι μήνες)</h6>
    
                        @if(isset($settlements) && $settlements->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 align-middle">
                                    <thead>
                                    <tr>
                                        <th>Μήνας</th>
                                        <th class="text-end">Σύνολο</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($settlements as $s)
                                        <tr>
                                            <td>{{ $s->month->format('m/Y') }}</td>
                                            <td class="text-end">
                                                <strong>{{ number_format($s->total_amount, 2, ',', '.') }} €</strong>
                                            </td>
                                        </tr>
                                        <tr class="text-muted">
                                            <td colspan="2">
                                                Μετρητά προς κατάθεση: <strong>{{ number_format($s->cash_to_bank, 2, ',', '.') }} €</strong><br>
                                                Γιάννης: <strong>{{ number_format($s->partner1_total, 2, ',', '.') }} €</strong> —
                                                Ελένη: <strong>{{ number_format($s->partner2_total, 2, ',', '.') }} €</strong>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-muted">
                                Δεν υπάρχουν αποθηκευμένες εκκαθαρίσεις για το διάστημα.<br>
                                Πάτα <strong>«Εκκαθάριση»</strong> για να αποθηκευτούν.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <hr>

            {{-- Τελική εκκαθάριση ανά συνεταίρο --}}
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <h5>Γιάννης #1</h5>
                        <p class="mb-1">
                            <strong>Προσωπικά ραντεβού:</strong>
                            {{ number_format($partner1Personal, 2, ',', '.') }} €
                        </p>
                        <p class="mb-1">
                            <strong>Μοίρασμα από κοινό ταμείο:</strong>
                            {{ number_format($sharedPool / 2, 2, ',', '.') }} €
                        </p>
                        <p class="mb-0">
                            <strong>Σύνολο:</strong>
                            <span class="badge bg-success fs-6">
                                {{ number_format($partner1Total, 2, ',', '.') }} €
                            </span>
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <h5>Ελένη #2</h5>
                        <p class="mb-1">
                            <strong>Προσωπικά ραντεβού:</strong>
                            {{ number_format($partner2Personal, 2, ',', '.') }} €
                        </p>
                        <p class="mb-1">
                            <strong>Μοίρασμα από κοινό ταμείο:</strong>
                            {{ number_format($sharedPool / 2, 2, ',', '.') }} €
                        </p>
                        <p class="mb-0">
                            <strong>Σύνολο:</strong>
                            <span class="badge bg-success fs-6">
                                {{ number_format($partner2Total, 2, ',', '.') }} €
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            {{-- Έξοδα & Μισθοί --}}
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="text-muted mb-1">Συνολικά καταγεγραμμένα έξοδα περιόδου</h6>
                        <strong>{{ number_format($expensesTotal, 2, ',', '.') }} €</strong>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="text-muted mb-1">Μισθοί υπαλλήλων για το διάστημα</h6>
                        <p class="mb-1">
                            <small class="text-muted d-block">Αριθμός μηνών στο διάστημα:</small>
                            <strong>{{ $monthsCount }}</strong>
                        </p>
                        <p class="mb-0">
                            <strong>Σύνολο μισθών (όλοι οι υπάλληλοι):</strong><br>
                            <span class="badge bg-secondary fs-6">
                                {{ number_format($employeesTotalSalary, 2, ',', '.') }} €
                            </span>
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="text-muted mb-1">Σύνολο εξόδων & Net εταιρείας</h6>
                        <p class="mb-1">
                            <strong>Σύνολο εξόδων (έξοδα + μισθοί):</strong><br>
                            <span class="badge bg-danger fs-6">
                                {{ number_format($totalOutflow, 2, ',', '.') }} €
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            {{-- Γραφήματα --}}
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Κατανομή Ποσών (Μετρητά & Συνεταίροι)</div>
                        <div class="card-body">
                            <canvas id="distributionChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Ημερήσια Προσωπικά Έσοδα (Γιάννης & Ελένη)</div>
                        <div class="card-body">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Αναλυτικός πίνακας πληρωμών --}}
            <div class="card mb-4">
                <div class="card-header">Αναλυτικές Πληρωμές Περιόδου</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Ημ/νία Ραντεβού</th>
                                <th>Περιστατικό</th>
                                <th>Επαγγελματίας</th>
                                <th>Ποσό (€)</th>
                                <th>Μέθοδος</th>
                                <th>ΦΠΑ</th>
                                <th>Ποσό Επαγγελματία (€)</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($payments as $pay)
                                @php
                                    $appt = $pay->appointment;
                                    $cust = $pay->customer;
                                @endphp
                                <tr>
                                    <td>{{ $pay->id }}</td>
                                    <td>{{ $appt?->start_time?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>
                                        @if($cust)
                                            {{ $cust->last_name }} {{ $cust->first_name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($appt?->professional)
                                            {{ $appt->professional->last_name }} {{ $appt->professional->first_name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ number_format($pay->amount, 2, ',', '.') }}</td>
                                    <td>
                                        @if($pay->method === 'cash')
                                            Μετρητά
                                        @elseif($pay->method === 'card')
                                            Κάρτα
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($pay->tax === 'Y')
                                            Με απόδειξη
                                        @else
                                            Χωρίς απόδειξη
                                        @endif
                                    </td>
                                    <td>{{ number_format($appt->professional_amount ?? 0, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        Δεν βρέθηκαν πληρωμές για το επιλεγμένο διάστημα.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Αναλυτικοί μισθοί υπαλλήλων --}}
            <div class="card mb-4">
                <div class="card-header">
                    Μισθοί Υπαλλήλων για το διάστημα ({{ $monthsCount }} μήνες)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Ονοματεπώνυμο</th>
                                <th>Ρόλος</th>
                                <th>Μηνιαίος μισθός (€)</th>
                                <th>Μήνες</th>
                                <th>Σύνολο περιόδου (€)</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($employeesSalaryRows as $row)
                                @php $p = $row['professional']; @endphp
                                <tr>
                                    <td>{{ $p->id }}</td>
                                    <td>{{ $p->last_name }} {{ $p->first_name }}</td>
                                    <td>{{ $p->role }}</td>
                                    <td>{{ number_format($row['monthly_salary'], 2, ',', '.') }}</td>
                                    <td>{{ $row['months'] }}</td>
                                    <td>{{ number_format($row['period_salary'], 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Δεν υπάρχουν υπάλληλοι με καταχωρημένο μισθό.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                            @if(count($employeesSalaryRows) > 0)
                                <tfoot>
                                <tr>
                                    <th colspan="5" class="text-end">Σύνολο μισθών περιόδου:</th>
                                    <th>{{ number_format($employeesTotalSalary, 2, ',', '.') }} €</th>
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            {{-- Αναλυτικά Έξοδα --}}
            <div class="card">
                <div class="card-header">Αναλυτικά Έξοδα Περιόδου</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Ημ/νία</th>
                                <th>Περιγραφή</th>
                                <th>Ποσό (€)</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($expensesList as $exp)
                                <tr>
                                    <td>{{ $exp->id }}</td>
                                    <td>{{ $exp->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ $exp->description ?? '-' }}</td>
                                    <td>{{ number_format($exp->amount, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        Δεν υπάρχουν καταγεγραμμένα έξοδα στο επιλεγμένο διάστημα.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                            @if(count($expensesList) > 0)
                                <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Σύνολο εξόδων:</th>
                                    <th>{{ number_format($expensesTotal, 2, ',', '.') }} €</th>
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const distCtx = document.getElementById('distributionChart').getContext('2d');

            new Chart(distCtx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($chartDistribution['labels']) !!},
                    datasets: [{
                        label: 'Ποσό (€)',
                        data: {!! json_encode($chartDistribution['data']) !!}
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true } }
                }
            });

            const dailyCtx = document.getElementById('dailyChart').getContext('2d');

            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($dailyChart['labels']) !!},
                    datasets: [
                        {
                            label: 'Γιάννης – Προσωπικά έσοδα',
                            data: {!! json_encode($dailyChart['giannis']) !!},
                            tension: 0.3
                        },
                        {
                            label: 'Ελένη – Προσωπικά έσοδα',
                            data: {!! json_encode($dailyChart['eleni']) !!},
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true } }
                }
            });
        });
    </script>
@endpush
