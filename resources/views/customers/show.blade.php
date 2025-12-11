@extends('layouts.app')

@section('title', 'Πελάτης: ' . $customer->last_name . ' ' . $customer->first_name)

@section('content')
    <div class="mb-3">
        <a href="{{ route('customers.index') }}" class="btn btn-secondary btn-sm">← Πίσω στη λίστα πελατών</a>
    </div>

    {{-- Στοιχεία Πελάτη + Οικονομική εικόνα --}}
    <div class="card mb-4">
        <div class="card-header">
            Στοιχεία Πελάτη
        </div>
        <div class="card-body">
            <div class="row mb-3">
    <div class="col-md-3">
        <p><strong>Ονοματεπώνυμο:</strong> {{ $customer->last_name }} {{ $customer->first_name }}</p>
        <p><strong>Τηλέφωνο:</strong> {{ $customer->phone }}</p>
        <p><strong>Email:</strong> {{ $customer->email ?? '-' }}</p>
        <p><strong>Εταιρεία:</strong> {{ $customer->company->name ?? '-' }}</p>
        <p><strong>Σύνολο Ραντεβού (με βάση τα φίλτρα):</strong> {{ $appointmentsCount }}</p>
    </div>

    <div class="col-md-3">
        <p><strong>ΑΦΜ:</strong> {{ $customer->vat_number ?? '-' }}</p>
        <p><strong>ΔΟΥ:</strong> {{ $customer->tax_office ?? '-' }}</p>
        <p><strong>Πληροφορίες:</strong> {!! $customer->informations ? nl2br(e($customer->informations)) : '-' !!}</p>
    </div>

    <div class="col-md-3">
        <p>
            <strong>Συνολικό Ποσό Ραντεβού:</strong><br>
            <span class="badge bg-primary fs-6">
                {{ number_format($totalAmount, 2, ',', '.') }} €
            </span>
        </p>
        <p>
            <strong>Συνολικό Ποσό που Έχει Πληρώσει:</strong><br>
            <span class="badge bg-success fs-6">
                {{ number_format($paidTotal, 2, ',', '.') }} €
            </span>
        </p>
        <p>
            <strong>Υπόλοιπο (απλήρωτο):</strong><br>
            <span class="badge {{ $outstandingTotal > 0 ? 'bg-danger' : 'bg-secondary' }} fs-6">
                {{ number_format($outstandingTotal, 2, ',', '.') }} €
            </span>
        </p>
    </div>

    {{-- ⭐ ΝΕΑ ΣΤΗΛΗ: Ιστορικό πληρωμών ομαδοποιημένο ανά ημερομηνία --}}
    <div class="col-md-3">
        <p><strong>Ιστορικό Πληρωμών:</strong></p>

        <div class="border rounded p-2"
             style="max-height: 180px; overflow-y: auto; font-size: 0.8rem; background-color: #f8f9fa;">
            @forelse($paymentsByDate as $dateKey => $dayPayments)
                @php
                    // Αν είναι "Χωρίς ημερομηνία" το αφήνουμε έτσι, αλλιώς το κάνουμε d/m/Y
                    $dateLabel = $dateKey === 'Χωρίς ημερομηνία'
                        ? 'Χωρίς ημερομηνία'
                        : \Carbon\Carbon::parse($dateKey)->format('d/m/Y');

                    $dayTotal = $dayPayments->sum('amount');
                @endphp

                <div class="mb-2">
                    {{-- Γραμμή ημερομηνίας + σύνολο --}}
                    <div>
                        <strong>{{ $dateLabel }}</strong>
                        <span class="badge bg-primary ms-1">
                            {{ number_format($dayTotal, 2, ',', '.') }} €
                        </span>
                    </div>

                    {{-- Αναλυτικά οι πληρωμές της ημέρας --}}
                    @foreach($dayPayments as $payment)
                        <div class="text-muted" style="font-size: 0.75rem;">
                            {{ number_format($payment->amount, 2, ',', '.') }} €

                            ·
                            @if($payment->method === 'cash')
                                Μετρητά
                            @elseif($payment->method === 'card')
                                Κάρτα
                            @else
                                Άλλο
                            @endif

                            ·
                            @if($payment->tax === 'Y')
                                Με απόδειξη
                            @else
                                Χωρίς απόδειξη
                            @endif

                            @if($payment->is_full)
                                · Πλήρης
                            @else
                                · Μερική
                            @endif
                        </div>
                    @endforeach
                </div>

                <hr class="my-1">
            @empty
                <span class="text-muted">Δεν έχουν γίνει πληρωμές για αυτόν τον πελάτη.</span>
            @endforelse
        </div>
    </div>
</div>



            <!-- ⭐ Edit Button Bottom Right -->
            <div class="d-flex justify-content-end mt-3">
                <a href="{{ route('customers.edit', $customer) }}" title="Επεξεργασία πελάτη" class="btn btn-sm btn-secondary">
                    <i class="bi bi-pencil-square"></i>
                </a>
            </div>
        </div>

    </div>

    {{-- Ραντεβού Πελάτη --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Ραντεβού Πελάτη</span>

            {{-- ΝΕΟ κουμπί: Προσθήκη ραντεβού για αυτόν τον πελάτη --}}
            <a href="{{ route('appointments.create', ['customer_id' => $customer->id, 'redirect' => request()->fullUrl()]) }}"
               class="btn btn-primary mb-3">
                + Προσθήκη Ραντεβού
            </a>
        </div>

        <div class="card-body">

            {{-- Φίλτρα --}}
            <form method="GET" action="{{ route('customers.show', $customer) }} " class="mb-3">
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
                        <label class="form-label">Κατάσταση Πληρωμής</label>
                        @php $ps = $filters['payment_status'] ?? 'all'; @endphp
                        <select name="payment_status" class="form-select">
                            <option value="all" @selected($ps === 'all')>Όλα</option>
                            <option value="unpaid" @selected($ps === 'unpaid')>Απλήρωτα</option>
                            <option value="partial" @selected($ps === 'partial')>Μερικώς πληρωμένα</option>
                            <option value="full" @selected($ps === 'full')>Πλήρως πληρωμένα</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end justify-content-end">
                        <button class="btn btn-outline-primary me-2">
                            Εφαρμογή Φίλτρων
                        </button>
                        <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary">
                            Καθαρισμός
                        </a>
                    </div>
                </div>
            </form>

            {{-- Πίνακας ραντεβών --}}
            <div class="table-responsive mb-3">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th class="text-center">
                            <input type="checkbox" id="select_all">
                        </th>
                        <th>Created</th>
                        <th>Ημ/νία & Ώρα</th>
                        <th>Επαγγελματίας</th>
                        <th>Εταιρεία</th>
                        <th>Υπηρεσία</th>
                        <th>Σύνολο (€)</th>
                        <th>Πληρωμή</th>
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
                            {{-- Επιλογή για μαζική πληρωμή / διαγραφή --}}
                            <td class="text-center">
                                @if($total > 0)
                                    <input type="checkbox"
                                           class="appointment-checkbox"
                                           value="{{ $appointment->id }}">
                                @else
                                    <input type="checkbox"
                                           class="appointment-checkbox"
                                           value="{{ $appointment->id }}">
                                @endif
                            </td>

                            <td>
                                @if($appointment->creator)
                                        <small class="text-muted">
                                            {{ $appointment->creator->first_name }}
                                        </small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>{{ $appointment->start_time?->format('d/m/Y H:i') }}</td>

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
                                <span class="badge
                                    @if($appointment->status === 'completed') bg-success
                                    @elseif($appointment->status === 'cancelled') bg-danger
                                    @elseif($appointment->status === 'no_show') bg-warning text-dark
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

                                    <small class="text-muted d-block">
                                        {{ $methodLabel }} · {{ $taxLabel }}
                                    </small>
                                @endif
                            </td>

                            {{-- Ενέργειες --}}
                            <td>
                                {{-- Επεξεργασία Ραντεβού --}}
                                <a href="{{ route('appointments.edit', ['appointment' => $appointment, 'redirect' => request()->fullUrl()]) }}"
                                   class="btn btn-sm btn-secondary mb-1"
                                   title="Επεξεργασία ραντεβού">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                {{-- Επεξεργασία Πληρωμής --}}
                                <a href="{{ route('appointments.payment.edit', ['appointment' => $appointment, 'redirect' => request()->fullUrl()]) }}"
                                   class="btn btn-sm btn-outline-primary mb-1"
                                   title="Επεξεργασία πληρωμής">
                                    <i class="bi bi-credit-card"></i>
                                </a>

                                {{-- Διαγραφή Ραντεβού (μονή) --}}
                                <form action="{{ route('appointments.destroy', $appointment) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτό το ραντεβού;');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
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
                                Δεν υπάρχουν ραντεβού για αυτόν τον πελάτη.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Σελιδοποίηση ραντεβών --}}
            <div class="d-flex justify-content-center mb-3">
                {{ $appointments->links() }}
            </div>

            {{-- Φόρμα για μαζική πληρωμή επιλεγμένων ραντεβών --}}
            <form id="payAllForm"
                  method="POST"
                  action="{{ route('customers.payAll', $customer) }}"
                  onsubmit="return preparePayAllForm();">
                @csrf

                {{-- εδώ θα μπουν δυναμικά τα hidden appointments[] --}}
                <div id="appointmentsHiddenContainer"></div>

                <div class="row g-2 mt-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Τρόπος πληρωμής (για όλα τα επιλεγμένα)</label>
                        <select name="method" id="bulk_method" class="form-select" required>
                            <option value="">-- Επιλέξτε --</option>
                            <option value="cash">Μετρητά</option>
                            <option value="card">Κάρτα</option>
                        </select>
                    </div>

                    <div class="col-md-3" id="bulk_tax_wrapper">
                        <label class="form-label">ΦΠΑ (για όλα τα επιλεγμένα)</label>
                        <select name="tax" id="bulk_tax" class="form-select">
                            <option value="Y">Με απόδειξη</option>
                            <option value="N" selected>Χωρίς απόδειξη</option>
                        </select>
                    </div>

                    <div class="col-md-6 text-end">
                        <button type="submit" class="btn btn-success">
                            💶 Πληρωμή επιλεγμένων ραντεβού (πλήρης εξόφληση)
                        </button>
                    </div>
                </div>
            </form>

            {{-- Φόρμα για μαζική διαγραφή επιλεγμένων ραντεβών --}}
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
                            class="btn btn-outline-danger"
                            onclick="return confirm('Σίγουρα θέλετε να διαγράψετε τα επιλεγμένα ραντεβού; Αυτή η ενέργεια δεν μπορεί να αναιρεθεί.');">
                        🗑 Διαγραφή επιλεγμένων ραντεβού
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const bulkMethod      = document.getElementById('bulk_method');
            const bulkTaxWrapper  = document.getElementById('bulk_tax_wrapper');
            const bulkTax         = document.getElementById('bulk_tax');
            const selectAll       = document.getElementById('select_all');
            const checkboxes      = document.querySelectorAll('.appointment-checkbox');

            function updateTaxVisibility() {
                const method = bulkMethod.value;

                if (method === 'card') {
                    // Κάρτα => πάντα με απόδειξη
                    bulkTaxWrapper.classList.add('d-none');
                    if (bulkTax) {
                        bulkTax.value = 'Y';
                    }
                } else {
                    bulkTaxWrapper.classList.remove('d-none');
                }
            }

            if (bulkMethod) {
                bulkMethod.addEventListener('change', updateTaxVisibility);
                updateTaxVisibility();
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(cb => cb.checked = selectAll.checked);
                });
            }
        });

        function collectSelectedAppointments() {
            return Array.from(document.querySelectorAll('.appointment-checkbox:checked'))
                .map(cb => cb.value);
        }

        function preparePayAllForm() {
            const container = document.getElementById('appointmentsHiddenContainer');
            const ids = collectSelectedAppointments();

            if (!ids.length) {
                alert('Παρακαλώ επιλέξτε τουλάχιστον ένα ραντεβού.');
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
    </script>
@endsection
