@extends('layouts.app')

@section('title', 'Επεξεργασία Ειδοποίησης')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Επεξεργασία Ειδοποίησης #{{ $notification->id }}</strong>
        <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Πίσω
        </a>
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route('notifications.update', $notification) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Σημείωση</label>
                <textarea name="note" rows="4" class="form-control" required>{{ old('note', $notification->note) }}</textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Ημερομηνία & Ώρα ειδοποίησης</label>
                <input type="datetime-local" name="notify_at" class="form-control" required
                       value="{{ old('notify_at', optional($notification->notify_at)->format('Y-m-d\TH:i')) }}">
            </div>

            <div class="mb-3 form-check">
                {{-- Ensures a value is always posted even if checkbox is unchecked --}}
                <input type="hidden" name="is_read" value="0">

                <input
                    type="checkbox"
                    name="is_read"
                    value="1"
                    class="form-check-input"
                    id="is_read"
                    {{ old('is_read', $notification->is_read) ? 'checked' : '' }}
                >
                <label class="form-check-label" for="is_read">Έχει διαβαστεί</label>
            </div>


            <button class="btn btn-primary">Αποθήκευση</button>
        </form>
    </div>
</div>
@endsection
