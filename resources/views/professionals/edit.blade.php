@extends('layouts.app')

@section('title', 'Επεξεργασία Επαγγελματία')

@section('content')
    <div class="card">
        <div class="card-header">
            Επεξεργασία Επαγγελματία
        </div>
        <div class="card-body">
            <form action="{{ route('professionals.update', $professional) }}" method="POST"  enctype="multipart/form-data">
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
                    >
                </div>
                
                <input type="hidden" name="redirect" value="{{ request('redirect', url()->previous()) }}">

                <div class="mb-3">
                    <label class="form-label">Ειδικότητα</label>
                    <select name="eidikotita" class="form-select">
                        <option value="">-- Επιλέξτε ειδικότητα --</option>
                        <option value="Λογοθεραπευτής" @selected(old('eidikotita', $professional->eidikotita) == 'Λογοθεραπευτής')>Λογοθεραπευτής</option>
                        <option value="Ειδικός παιδαγωγός" @selected(old('eidikotita', $professional->eidikotita) == 'Ειδικός παιδαγωγός')>Ειδικός παιδαγωγός</option>
                        <option value="Εργοθεραπευτής" @selected(old('eidikotita', $professional->eidikotita) == 'Εργοθεραπευτής')>Εργοθεραπευτής</option>
                        <option value="Ψυχοθεραπευτής" @selected(old('eidikotita', $professional->eidikotita) == 'Ψυχοθεραπευτής')>Ψυχοθεραπευτής</option>
                    </select>
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
                    <label class="form-label">Φωτογραφία Προφίλ</label>

                    @if($professional->profile_image)
                        <div class="mb-2">
                            <img src="{{ asset('storage/'.$professional->profile_image) }}"
                                alt="Profile image"
                                class="img-thumbnail"
                                style="max-width: 150px;">
                        </div>
                    @endif

                    <input type="file" name="profile_image" class="form-control" accept="image/*">
                    <small class="text-muted">
                        Αν ανεβάσετε νέα εικόνα, θα αντικαταστήσει την παλιά.
                    </small>
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
                @if(auth()->user()->role === 'owner')
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
                @endif

                <div class="mb-3">
                    <label class="form-label">Γραφείο</label>
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

                {{-- ✅ ΝΕΟ: Παιδιά (Customers) --}}
                <div class="mb-3">
                    <label class="form-label">Παιδιά</label>
                    <select name="customers[]" class="form-select js-select2" multiple>
                        @php
                            $selectedCustomers = collect(old('customers', $professional->customers->pluck('id')->toArray()));
                        @endphp

                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}"
                                @selected($selectedCustomers->contains($customer->id))
                            >
                                {{ $customer->last_name }} {{ $customer->first_name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">
                        Προαιρετικό. Κρατήστε πατημένο Ctrl (Windows) ή Command (Mac) για πολλαπλή επιλογή.
                    </small>
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
@push('scripts')
<script>
  $(function () {
    $('.js-select2').select2({
      placeholder: 'Αναζήτηση παιδιών...',
      allowClear: true,
      width: '100%'
    });
  });
</script>
@endpush
