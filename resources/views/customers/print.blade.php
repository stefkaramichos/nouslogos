<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Εκτύπωση Περιστατικών</title>

    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
        h1 { font-size: 16px; margin: 0 0 10px; }
        .meta { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; vertical-align: top; word-wrap: break-word; }
        th { background: #f2f2f2; text-align: left; font-size: 10px; }
        .muted { color: #777; font-size: 10px; }
        .nowrap { white-space: nowrap; }
        .compact { line-height: 1.3; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; font-size: 10px; }
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
            <th style="width: 10%;">Ονοματεπώνυμο</th>
        @endif
        @if(in_array('phone', $printFields))
            <th style="width: 10%;">Τηλέφωνο</th>
        @endif
        @if(in_array('company', $printFields))
            <th style="width: 12%;">Εταιρεία</th>
        @endif
        @if(in_array('professionals', $printFields))
            <th style="width: 12%;">Θεραπευτές</th>
        @endif
        @if(in_array('status', $printFields))
            <th style="width: 8%;">Κατάσταση</th>
        @endif
        @if(in_array('informations', $printFields))
            <th style="width: 20%;">Πληροφορίες</th>
        @endif
        @if(in_array('unissued_receipts', $printFields))
            <th style="width: 28%;">Αποδείξεις (ΟΧΙ κομμένες)</th>
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
                <td class="compact">
                    <strong style="font-size: 11px;">{{ $c->last_name }} {{ $c->first_name }}</strong>
                    @if(!$isActive)
                        <div class="muted" style="font-size: 9px;">Απενεργ.</div>
                    @endif
                </td>
            @endif

            @if(in_array('phone', $printFields))
                <td class="compact">
                    {{ $c->phone ?? '-' }}
                </td>
            @endif

            @if(in_array('company', $printFields))
                <td class="compact">
                    {{ $c->company->name ?? '-' }}
                </td>
            @endif

            @if(in_array('professionals', $printFields))
                <td class="compact">
                    @php $pros = $c->professionals ?? collect(); @endphp
                    @if($pros->isEmpty())
                        <span class="muted">-</span>
                    @else
                        {{ $pros->map(fn($p) => trim(($p->last_name ?? '').' '.($p->first_name ?? '')))->implode(', ') }}
                    @endif
                </td>
            @endif

            @if(in_array('status', $printFields))
                <td class="compact">
                    {{ $isActive ? 'Ενεργός' : 'Απενεργ.' }}
                </td>
            @endif

            @if(in_array('informations', $printFields))
                <td class="compact">
                    @if(!empty($c->informations))
                        <div style="font-size: 10px; line-height: 1.3;">{{ $c->informations }}</div>
                    @else
                        <span class="muted">-</span>
                    @endif
                </td>
            @endif

            @if(in_array('unissued_receipts', $printFields))
                <td class="compact">
                    @if($unissued->isEmpty())
                        <span class="muted">-</span>
                    @else
                        <div style="font-size: 9px; line-height: 1.3;">
                            <strong>Σύνολο:</strong> {{ number_format($sum, 2, ',', '.') }} € | 
                            <strong>Πλήθος:</strong> {{ $unissued->count() }} | 
                            @foreach($unissued as $r)
                                {{ $r->receipt_date ? \Carbon\Carbon::parse($r->receipt_date)->format('d/m/Y') : '-' }} — {{ number_format((float)$r->amount, 2, ',', '.') }} €
                                @if(!empty($r->comment)) ({{ \Illuminate\Support\Str::limit($r->comment, 40) }})@endif
                                @if(!$loop->last) • @endif
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
