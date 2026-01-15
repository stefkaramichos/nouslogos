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

            {{-- Πελάτης --}}
            <div class="mb-3">
                <label class="form-label">Πελάτης</label>
                <select name="customer_id" id="customer_select" class="form-select" {{ $user->role !== 'owner' ? 'required' : '' }}>
                    <option value="">-- Επιλέξτε --</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}"
                            @selected((string)$c->id === (string)$appointment->customer_id)>
                            {{ $c->last_name }} {{ $c->first_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if($user->role === 'owner')
                {{-- Επαγγελματίας (μόνο owner) --}}
                <div class="mb-3">
                    <label class="form-label">Επαγγελματίας</label>
                    <select name="with_professional_id" id="with_professional_select" class="form-select">
                        <option value="">-- Επιλέξτε --</option>
                        @foreach($withProfessionals as $p)
                            <option value="{{ $p->id }}"
                                @selected((string)$p->id === (string)$appointment->with_professional_id)>
                                {{ $p->last_name }} {{ $p->first_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

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

@push('scripts')
@if($user->role === 'owner')
<script>
    // Owner: μόνο ένα από τα δύο
    document.addEventListener('DOMContentLoaded', function () {
        const customerSel = document.getElementById('customer_select');
        const profSel     = document.getElementById('with_professional_select');

        function sync() {
            if (customerSel.value) profSel.value = '';
            if (profSel.value) customerSel.value = '';
        }

        customerSel.addEventListener('change', sync);
        profSel.addEventListener('change', sync);
    });
</script>
@endif
@endpush
