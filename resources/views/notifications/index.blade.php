@extends('layouts.app')

@section('title', 'Ειδοποιήσεις')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Ειδοποιήσεις</strong>

        <a href="{{ route('notifications.create') }}" class="btn btn-primary btn-sm">
            + Νέα Ειδοποίηση
        </a>
    </div>

    <div class="card-body">

        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Από</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Έως</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="unread" value="1" id="unread"
                           {{ $onlyUnread ? 'checked' : '' }}>
                    <label class="form-check-label" for="unread">Μόνο αδιάβαστες</label>
                </div>
            </div>

            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-outline-primary w-100">Φιλτράρισμα</button>
                <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">Καθαρισμός</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    {{-- <th>Δημιουργήθηκε</th> --}}
                    <th>Ημερομηνία ειδοποίησης</th>
                    <th>Σημείωση</th>
                    <th>Κατάσταση</th>
                    <th class="text-end">Ενέργειες</th>
                </tr>
                </thead>
                <tbody>
                @forelse($notifications as $n)
                    @php
                        $colors = ['#e3f2fd', '#e8f5e9', '#e1ffe3', '#fce4ec', '#ede7f6'];
                        $bg = $colors[$n->professional_id % count($colors)];
                    @endphp

                    <tr class="notification-row"
                        style="cursor:pointer"
                        data-id="{{ $n->id }}"
                        data-note="{{ e($n->note) }}"
                        data-created="{{ optional($n->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}"
                        data-notify="{{ optional($n->notify_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}"
                        data-status="{{ $n->is_read ? 'Διαβασμένη' : 'Αδιάβαστη' }}"
                    >
                        {{-- <td>{{ optional($n->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td> --}}

                        <td>{{ optional($n->notify_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                        <td style="background-color: {{ $bg }};"
                            class="rounded px-2"
                            title="{{ $n->note }}">
                            {{ \Illuminate\Support\Str::limit($n->note, 60) }}
                        </td>


                        <td>
                            @if($n->is_read)
                                <span class="badge bg-success">Διαβασμένη</span>
                            @else
                                <span class="badge bg-warning text-dark">Αδιάβαστη</span>
                            @endif
                        </td>

                        <td class="text-end">
                            {{-- stop click from opening modal when pressing buttons --}}
                            <a href="{{ route('notifications.edit', $n) }}"
                               class="btn btn-sm btn-secondary"
                               title="Επεξεργασία"
                               onclick="event.stopPropagation();">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <form method="POST"
                                  action="{{ route('notifications.destroy', $n) }}"
                                  class="d-inline"
                                  onsubmit="event.stopPropagation(); return confirm('Σίγουρα θέλετε να διαγράψετε την ειδοποίηση;');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="Διαγραφή">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Δεν υπάρχουν ειδοποιήσεις.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $notifications->links() }}
        </div>

    </div>
</div>

{{-- ✅ MODAL --}}
<div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Λεπτομέρειες Ειδοποίησης</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
            </div>

            <div class="modal-body">
                <div class="mb-2"><strong>Δημιουργήθηκε:</strong> <span id="modalCreated"></span></div>
                <div class="mb-2"><strong>Ημερομηνία ειδοποίησης:</strong> <span id="modalNotify"></span></div>
                <div class="mb-2"><strong>Κατάσταση:</strong> <span id="modalStatus"></span></div>

                <hr>

                <div>
                    <strong>Σημείωση:</strong>
                    <div class="mt-2 p-3 rounded bg-light" style="white-space: pre-wrap;" id="modalNote"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('notificationModal');
    const modal = new bootstrap.Modal(modalEl);

    document.querySelectorAll('.notification-row').forEach(row => {
        row.addEventListener('click', function () {
            document.getElementById('modalCreated').textContent = this.dataset.created || '-';
            document.getElementById('modalNotify').textContent   = this.dataset.notify || '-';
            document.getElementById('modalStatus').textContent   = this.dataset.status || '-';
            document.getElementById('modalNote').textContent     = this.dataset.note || '';

            modal.show();
        });
    });
});
</script>
@endpush
