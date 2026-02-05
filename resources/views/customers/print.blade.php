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

<table>
    <thead>
    <tr>
        <th style="width: 26%;">Ονοματεπώνυμο</th>
        <th style="width: 44%;">Πληροφορίες</th>
        <th style="width: 30%;">Αποδείξεις (ΟΧΙ κομμένες)</th>
    </tr>
    </thead>
    <tbody>
    @forelse($customers as $c)
        @php
            $unissued = $c->receipts ?? collect(); // only is_issued=0
            $sum = (float)$unissued->sum('amount');
        @endphp

        <tr>
            <td>
                <strong>{{ $c->last_name }} {{ $c->first_name }}</strong>
                @if((int)($c->is_active ?? 1) === 0)
                    <div class="muted">Απενεργοποιημένος</div>
                @endif
            </td>

            <td>
                @if(!empty($c->informations))
                    {!! nl2br(e($c->informations)) !!}
                @else
                    <span class="muted">-</span>
                @endif
            </td>

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
        </tr>
    @empty
        <tr>
            <td colspan="3" class="muted">Δεν υπάρχουν δεδομένα.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<script>
    // auto-print (αν θες): window.onload = () => window.print();
</script>

</body>
</html>
