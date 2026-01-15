@extends('layouts.app')

@section('title', 'Επεξεργασία Στοιχείου Τιμοκαταλόγου')

@section('content')
<div class="card">
    <div class="card-header">
        <strong>Επεξεργασία Στοιχείου Τιμοκαταλόγου</strong>
    </div>

    <div class="card-body">
        <form action="{{ route('price_items.update', $item) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Τίτλος</label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $item->title) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Περιγραφή</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $item->description) }}</textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Τιμή (€)</label>
                <input type="number" name="price" step="0.01" min="0" class="form-control" value="{{ old('price', $item->price) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Σειρά εμφάνισης</label>
                <input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', $item->sort_order) }}">
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    Ενεργό
                </label>
            </div>

            <button class="btn btn-primary">Αποθήκευση Αλλαγών</button>
            <a href="{{ route('price_items.index') }}" class="btn btn-secondary">Ακύρωση</a>
        </form>
    </div>
</div>
@endsection
