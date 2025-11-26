@extends('layouts.app')

@section('title', 'Νέο ραντεβού (θεραπευτή)')

@section('content')
    <div class="card">
        <div class="card-header">
            Νέο ραντεβού ({{ $user->first_name }} {{ $user->last_name }})
        </div>

        <div class="card-body">
            <form action="{{ route('therapist_appointments.store') }}" method="POST">
                @csrf

                {{-- ΠΕΛΑΤΗΣ --}}
                <div class="mb-3">
                    <label class="form-label">Πελάτης</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">-- Επιλέξτε πελάτη --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>
                                {{ $customer->last_name }} {{ $customer->first_name }} ({{ $customer->phone }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- ΗΜΕΡΟΜΗΝΙΑ & ΩΡΑ --}}
                <div class="mb-3">
                    <label class="form-label">Ημερομηνία & Ώρα</label>
                    <input type="datetime-local"
                           name="start_time"
                           class="form-control"
                           value="{{ old('start_time') }}"
                           required>
                </div>

                {{-- ΣΗΜΕΙΩΣΕΙΣ --}}
                <div class="mb-3">
                    <label class="form-label">Σημειώσεις (προαιρετικό)</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>

                <button class="btn btn-primary">Αποθήκευση</button>
                <a href="{{ route('therapist_appointments.index') }}" class="btn btn-secondary">Ακύρωση</a>
            </form>
        </div>
    </div>
@endsection
