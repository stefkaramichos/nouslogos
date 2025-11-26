@extends('layouts.app')

@section('title', 'Επεξεργασία Ραντεβού')

@section('content')
<div class="card">
    <div class="card-header">
        <strong>Επεξεργασία Ραντεβού</strong>
    </div>

    <div class="card-body">

        <form action="{{ route('therapist_appointments.update', $appointment) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Πελάτης</label>
                <select name="customer_id" class="form-select" required>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}"
                            @selected($c->id == $appointment->customer_id)>
                            {{ $c->last_name }} {{ $c->first_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Ημερομηνία & Ώρα</label>
                <input type="datetime-local"
                       name="start_time"
                       value="{{ \Carbon\Carbon::parse($appointment->start_time)->format('Y-m-d\TH:i') }}"
                       class="form-control"
                       required>
            </div>

            <div class="mb-3">
                <label class="form-label">Σημειώσεις</label>
                <textarea name="notes" class="form-control" rows="3">{{ $appointment->notes }}</textarea>
            </div>

            <button class="btn btn-primary">Αποθήκευση Αλλαγών</button>
            <a href="{{ route('therapist_appointments.index') }}"
               class="btn btn-secondary">Ακύρωση</a>
        </form>

    </div>
</div>
@endsection
