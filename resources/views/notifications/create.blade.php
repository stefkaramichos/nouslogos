@extends('layouts.app')

@section('title', 'Νέα Ειδοποίηση')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Νέα Ειδοποίηση</strong>
        <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Πίσω
        </a>
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route('notifications.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Σημείωση</label>
                <textarea name="note" rows="4" class="form-control" required>{{ old('note') }}</textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Ημερομηνία & Ώρα ειδοποίησης</label>
                <input type="datetime-local" name="notify_at" class="form-control" required
                       value="{{ old('notify_at') }}">
            </div>

            <button class="btn btn-primary">Αποθήκευση</button>
        </form>
    </div>
</div>
@endsection
