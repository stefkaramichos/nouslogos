@extends('layouts.app')

@section('title', 'Ανέβασμα Αρχείου')

@section('content')
    <div class="mb-3">
        <a href="{{ route('documents.index') }}" class="btn btn-secondary btn-sm">← Πίσω στα αρχεία</a>
    </div>

    <div class="card">
        <div class="card-header">Νέο αρχείο</div>
        <div class="card-body">
            <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Αρχείο</label>
                    <input type="file" name="file" class="form-control" required>
                    <small class="text-muted">Έως 10MB.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Σημείωση</label>
                    <textarea name="note" class="form-control" rows="4">{{ old('note') }}</textarea>
                </div>

                <button class="btn btn-primary">
                    <i class="bi bi-upload"></i> Ανέβασμα
                </button>
                <a href="{{ route('documents.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
