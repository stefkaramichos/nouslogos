@extends('layouts.app')

@section('title', 'Τιμοκατάλογος')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Τιμοκατάλογος</strong>

        <a href="{{ route('price_items.create') }}" class="btn btn-primary btn-sm">
            + Νέο Στοιχείο
        </a>
    </div>

    <div class="card-body">

        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md-5">
                <input type="text"
                       name="q"
                       value="{{ $q }}"
                       class="form-control"
                       placeholder="Αναζήτηση (τίτλος/περιγραφή)">
            </div>

            <div class="col-12 col-md-3">
                <select name="active" class="form-select">
                    <option value="" {{ $active === '' ? 'selected' : '' }}>Όλα</option>
                    <option value="1" {{ $active === '1' ? 'selected' : '' }}>Ενεργά</option>
                    <option value="0" {{ $active === '0' ? 'selected' : '' }}>Ανενεργά</option>
                </select>
            </div>

            <div class="col-12 col-md-4 d-flex gap-2">
                <button class="btn btn-outline-primary w-100">Φιλτράρισμα</button>
                <a href="{{ route('price_items.index') }}" class="btn btn-outline-secondary">Καθαρισμός</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Τίτλος</th>
                        <th>Τιμή</th>
                        <th>Σειρά</th>
                        <th>Κατάσταση</th>
                        <th class="text-nowrap">Ενέργειες</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>
                            <div class="fw-semibold">{{ $item->title }}</div>
                            <div class="text-muted small">{{ $item->description ? Str::limit($item->description, 80) : '' }}</div>
                        </td>
                        <td class="text-nowrap">{{ number_format((float)$item->price, 2, ',', '.') }} €</td>
                        <td>{{ $item->sort_order }}</td>
                        <td>
                            @if($item->is_active)
                                <span class="badge bg-success">Ενεργό</span>
                            @else
                                <span class="badge bg-secondary">Ανενεργό</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('price_items.edit', $item) }}" class="btn btn-sm btn-secondary" title="Επεξεργασία">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <form method="POST"
                                  action="{{ route('price_items.destroy', $item) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε;');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="Διαγραφή">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Δεν υπάρχουν στοιχεία τιμοκαταλόγου.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
            {{ $items->links() }}
        </div>

    </div>
</div>
@endsection
