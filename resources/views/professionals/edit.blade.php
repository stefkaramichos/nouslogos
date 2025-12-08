@extends('layouts.app')

@section('title', 'Επεξεργασία Επαγγελματία')

@section('content')
    <div class="card">
        <div class="card-header">
            Επεξεργασία Επαγγελματία
        </div>
        <div class="card-body">
            <form action="{{ route('professionals.update', $professional) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Όνομα</label>
                    <input
                        type="text"
                        name="first_name"
                        class="form-control"
                        value="{{ old('first_name', $professional->first_name) }}"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Επίθετο</label>
                    <input
                        type="text"
                        name="last_name"
                        class="form-control"
                        value="{{ old('last_name', $professional->last_name) }}"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Τηλέφωνο</label>
                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="{{ old('phone', $professional->phone) }}"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="{{ old('email', $professional->email) }}"
                    >
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
                        value="{{ old('salary', $professional->salary) }}"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Εταιρείες</label>
                    <select name="companies[]" class="form-select" multiple required>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}"
                                @selected($professional->companies->pluck('id')->contains($company->id))
                            >
                                {{ $company->name }} ({{ $company->city }})
                            </option>
                        @endforeach
                    </select>
                </div>

                  @if(auth()->user()->role === 'owner')
                    <hr>

                    <div class="mb-3">
                        <label class="form-label">Νέος Κωδικός (μόνο για αλλαγή)</label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                        >
                        <small class="text-muted">
                            Αφήστε το κενό αν δεν θέλετε να αλλάξετε τον κωδικό.
                        </small>
                        @error('password')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Επιβεβαίωση Νέου Κωδικού</label>
                        <input
                            type="password"
                            name="password_confirmation"
                            class="form-control"
                        >
                    </div>
                @endif

                <button class="btn btn-primary">Αποθήκευση Αλλαγών</button>
                <a href="{{ route('professionals.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
