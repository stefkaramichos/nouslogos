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
                    <label class="form-label">Περιστατικό</label>
                    <select name="customer_id" id="customer_select" class="form-select select2">
                        <option value="">-- Επιλέξτε περιστατικό --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>
                                {{ $customer->last_name }} {{ $customer->first_name }} ({{ $customer->phone }})
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Επιλέξτε είτε Περιστατικό είτε Επαγγελματία.</small>
                </div>
                @if($user->role === 'owner')
                    {{-- ΕΠΑΓΓΕΛΜΑΤΙΑΣ (ραντεβού με άλλον professional) --}}
                    <div class="mb-3">
                        <label class="form-label">Επαγγελματίας</label>
                        <select name="with_professional_id" id="with_professional_select" class="form-select select2">
                            <option value="">-- Επιλέξτε επαγγελματία --</option>
                            @foreach($withProfessionals as $p)
                                <option value="{{ $p->id }}" @selected(old('with_professional_id') == $p->id)>
                                    {{ $p->last_name }} {{ $p->first_name }} ({{ $p->role }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Επιλέξτε είτε Περιστατικό είτε Επαγγελματία.</small>
                    </div>
                @endif
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

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(function () {
        $('#customer_select').select2({
            width: '100%',
            placeholder: '-- Επιλέξτε περιστατικό --',
            allowClear: true
        });

        $('#with_professional_select').select2({
            width: '100%',
            placeholder: '-- Επιλέξτε επαγγελματία --',
            allowClear: true
        });

        // Only one can be selected
        $('#customer_select').on('change', function () {
            const val = $(this).val();
            if (val) {
                $('#with_professional_select').val(null).trigger('change');
            }
        });

        $('#with_professional_select').on('change', function () {
            const val = $(this).val();
            if (val) {
                $('#customer_select').val(null).trigger('change');
            }
        });
    });
</script>
@endpush
