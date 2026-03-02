<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Εκτύπωση Περιστατικών</title>

    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
        h1 { font-size: 16px; margin: 0 0 10px; }
        .meta { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; vertical-align: top; }
        th { background: #f2f2f2; text-align: left; }
        .muted { color: #777; }
        .nowrap { white-space: nowrap; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print" style="margin-bottom:10px;">
    <button onclick="window.print()">🖨 Εκτύπωση</button>
    <button onclick="window.close()">✖ Κλείσιμο</button>
</div>

<h1>Λίστα Περιστατικών (Εκτύπωση)</h1>
<div class="meta">
    <div>Ημ/νία: <span class="nowrap">{{ now()->format('d/m/Y H:i') }}</span></div>
    @if(!empty($search))
        <div>Αναζήτηση: <strong>{{ $search }}</strong></div>
    @endif
    <div>Κατάσταση: <strong>{{ $active === 'all' ? 'ΟΛΑ' : ($active === '1' ? 'ΕΝΕΡΓΟΙ' : 'ΑΝΕΝΕΡΓΟΙ') }}</strong></div>
</div>

@php
    // Χρησιμοποιούμε το print_fields αν υπάρχει, διαφορετικά default πεδία
    $printFields = isset($printFields) ? $printFields : ['name', 'phone', 'email', 'informations', 'unissued_receipts'];
    
    // Υπολογίζουμε το πλάτος κάθε στήλης δυναμικά
    $colCount = count($printFields);
    $defaultWidth = (100 / max($colCount, 1)) . '%';
@endphp

<table>
    <thead>
    <tr>
        @if(in_array('name', $printFields))
            <th style="width: 20%;">Ονοματεπώνυμο</th>
        @endif
        @if(in_array('phone', $printFields))
            <th style="width: 15%;">Τηλέφωνο</th>
        @endif
        @if(in_array('company', $printFields))
            <th style="width: 15%;">Εταιρεία/Τοποθεσία</th>
        @endif
        @if(in_array('professionals', $printFields))
            <th style="width: 15%;">Θεραπευτές</th>
        @endif
        @if(in_array('status', $printFields))
            <th style="width: 10%;">Κατάσταση</th>
        @endif
        @if(in_array('informations', $printFields))
            <th style="width: 25%;">Πληροφορίες</th>
        @endif
        @if(in_array('unissued_receipts', $printFields))
            <th style="width: 30%;">Αποδείξεις (ΟΧΙ κομμένες)</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @forelse($customers as $c)
        @php
            $unissued = $c->receipts ?? collect(); // only is_issued=0
            $sum = (float)$unissued->sum('amount');
            $isActive = (int)($c->is_active ?? 1) === 1;
        @endphp

        <tr>
            @if(in_array('name', $printFields))
                <td>
                    <strong>{{ $c->last_name }} {{ $c->first_name }}</strong>
                    @if(!$isActive)
                        <div class="muted">Απενεργοποιημένος</div>
                    @endif
                </td>
            @endif

            @if(in_array('phone', $printFields))
                <td>
                    {{ $c->phone ?? '-' }}
                </td>
            @endif

            @if(in_array('company', $printFields))
                <td>
                    {{ $c->company->name ?? '-' }}
                </td>
            @endif

            @if(in_array('professionals', $printFields))
                <td>
                    @php $pros = $c->professionals ?? collect(); @endphp
                    @if($pros->isEmpty())
                        <span class="muted">-</span>
                    @else
                        {{ $pros->map(fn($p) => trim(($p->last_name ?? '').' '.($p->first_name ?? '')))->implode(', ') }}
                    @endif
                </td>
            @endif

            @if(in_array('status', $printFields))
                <td>
                    {{ $isActive ? 'Ενεργός' : 'Απενεργοποιημένος' }}
                </td>
            @endif

            @if(in_array('informations', $printFields))
                <td>
                    @if(!empty($c->informations))
                        {!! nl2br(e($c->informations)) !!}
                    @else
                        <span class="muted">-</span>
                    @endif
                </td>
            @endif

            @if(in_array('unissued_receipts', $printFields))
                <td>
                    @if($unissued->isEmpty())
                        <span class="muted">-</span>
                    @else
                        <div><strong>Σύνολο:</strong> {{ number_format($sum, 2, ',', '.') }} €</div>
                        <div><strong>Πλήθος:</strong> {{ $unissued->count() }}</div>
                        <div style="margin-top:6px;">
                            @foreach($unissued as $r)
                                <div style="margin-bottom:4px;">
                                    • {{ $r->receipt_date ? \Carbon\Carbon::parse($r->receipt_date)->format('d/m/Y') : '-' }}
                                    — {{ number_format((float)$r->amount, 2, ',', '.') }} €
                                    @if(!empty($r->comment))
                                        <div class="muted">{{ $r->comment }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </td>
            @endif
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($printFields) }}" class="muted">Δεν υπάρχουν δεδομένα.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<script>
    // auto-print (αν θες): window.onload = () => window.print();
</script>

</body>
</html>
