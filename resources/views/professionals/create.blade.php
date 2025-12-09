@extends('layouts.app')

@section('title', 'Νέος Επαγγελματίας')

@section('content')
    <div class="card">
        <div class="card-header">
            Νέος Επαγγελματίας
        </div>
        <div class="card-body">
            <form action="{{ route('professionals.store') }}" method="POST"  enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Όνομα</label>
                    <input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Επίθετο</label>
                    <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" >
                </div>

                <div class="mb-3">
                    <label class="form-label">Τηλέφωνο</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" >
                </div>

                <div class="mb-3">
                    <label class="form-label">Φωτογραφία Προφίλ</label>
                    <input type="file" name="profile_image" class="form-control" accept="image/*">
                    <small class="text-muted">Επιτρέπονται εικόνες (jpg, png, webp, μέχρι 2MB).</small>
                </div>


                {{-- Μισθός --}}
                <div class="mb-3">
                    <label class="form-label">Μισθός (€/μήνα)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="salary"
                        class="form-control"
                        value="{{ old('salary') }}"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Εταιρείες</label>
                    <select name="companies[]" class="form-select" multiple required>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">
                                {{ $company->name }} ({{ $company->city }})
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">
                        Κρατήστε πατημένο Ctrl (Windows) ή Command (Mac) για πολλαπλή επιλογή.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Κωδικός</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Επιβεβαίωση Κωδικού</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>

                <button class="btn btn-primary">Αποθήκευση</button>
                <a href="{{ route('professionals.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
