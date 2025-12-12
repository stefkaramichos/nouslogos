@php
    $from = $filters['from'] ?? null;
    $to   = $filters['to']   ?? null;

    // Γρήγορα ranges
    $today      = now()->toDateString();
    $tomorrow   = now()->copy()->addDay()->toDateString();
    $weekStart  = now()->copy()->startOfWeek()->toDateString();
    $weekEnd    = now()->copy()->endOfWeek()->toDateString();
    $monthStart = now()->copy()->startOfMonth()->toDateString();
    $monthEnd   = now()->copy()->endOfMonth()->toDateString();

    // Ποιο κουμπί είναι ενεργό;
    $isToday     = ($from === $today && $to === $today);
    $isTomorrow  = ($from === $tomorrow && $to === $tomorrow);
    $isThisWeek  = ($from === $weekStart && $to === $weekEnd);
    $isThisMonth = ($from === $monthStart && $to === $monthEnd);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 mt-3">
    {{-- Αριστερά: τι έχει επιλεγεί --}}
    <h2 class="h5 mb-0 d-flex align-items-center">
        <small class="text-muted" style="font-size: 11px;">Έχετε επιλέξει:</small>

        @if($from || $to)
            <span class="badge text-dark border ms-2" style="background: rgb(169 169 169);">
                @if($from && $to && $from === $to)
                    Ημερομηνία:
                    {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}
                @elseif($from && $to)
                    Από
                    {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}
                    έως
                    {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
                @elseif($from)
                    Από
                    {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}
                @else
                    Έως
                    {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
                @endif
            </span>
        @else
            <span class="badge bg-secondary ms-2">Όλο το ιστορικό</span>
        @endif
    </h2>

    {{-- Δεξιά: γρήγορα κουμπιά range --}}
    <div class="btn-group btn-group-sm" role="group" aria-label="Γρήγορα φίλτρα ημερομηνίας">

        {{-- Σήμερα --}}
        <a href="{{ request()->fullUrlWithQuery(['from' => $today, 'to' => $today]) }}"
           class="btn {{ $isToday ? 'btn-primary' : 'btn-outline-secondary' }}">
            Σήμερα
        </a>

        {{-- Αύριο --}}
        <a href="{{ request()->fullUrlWithQuery(['from' => $tomorrow, 'to' => $tomorrow]) }}"
           class="btn {{ $isTomorrow ? 'btn-primary' : 'btn-outline-secondary' }}">
            Αύριο
        </a>

        {{-- Αυτή την εβδομάδα --}}
        <a href="{{ request()->fullUrlWithQuery(['from' => $weekStart, 'to' => $weekEnd]) }}"
           class="btn {{ $isThisWeek ? 'btn-primary' : 'btn-outline-secondary' }}">
            Αυτή την εβδομάδα
        </a>

        {{-- Αυτόν τον μήνα --}}
        <a href="{{ request()->fullUrlWithQuery(['from' => $monthStart, 'to' => $monthEnd]) }}"
           class="btn {{ $isThisMonth ? 'btn-primary' : 'btn-outline-secondary' }}">
            Αυτόν τον μήνα
        </a>
    </div>
</div>
