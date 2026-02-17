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

                {{-- ✅ Customer --}}
                <div class="mb-3">
                    <label class="form-label">Περιστατικό</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">— Επιλογή —</option>
                        @foreach(($customers ?? []) as $c)
                            <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>
                                {{ $c->last_name }} {{ $c->first_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                {{-- ✅ Visible to professional --}}
                <div class="mb-3">
                    <label class="form-label">Ορατό σε Επαγγελματία</label>
                    <select name="visible_professional_id" class="form-select" >
                        <option value="">— Επιλογή —</option>
                        @foreach(($professionals ?? []) as $p)
                            <option value="{{ $p->id }}" @selected(old('visible_professional_id') == $p->id)>
                                {{ $p->last_name }} {{ $p->first_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('visible_professional_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                {{-- File --}}
                <div class="mb-3">
                    <label class="form-label">Αρχείο</label>
                    <input type="file" name="file" class="form-control" required>
                    <small class="text-muted">Έως 10MB.</small>
                    @error('file')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Note --}}
                <div class="mb-3">
                    <label class="form-label">Σημείωση</label>
                    <textarea name="note" class="form-control" rows="4">{{ old('note') }}</textarea>
                    @error('note')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <button class="btn btn-primary">
                    <i class="bi bi-upload"></i> Ανέβασμα
                </button>
                <a href="{{ route('documents.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
