@extends('layouts.app')

@section('title', 'Αρχεία')

@section('content')
    @php
        $user = Auth::user();
        $isTherapist = $user && $user->role === 'therapist';
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            {{-- ✅ Κρύψε το ανέβασμα στους therapists --}}
            @unless($isTherapist)
                <a href="{{ route('documents.create') }}" class="btn btn-primary">
                    <i class="bi bi-upload"></i> Νέο αρχείο
                </a>
            @endunless
        </div>
    </div>

    {{-- ✅ Προαιρετικά φίλτρα --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('documents.index') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Περιστατικό</label>
                    <select class="form-select" name="customer_id">
                        <option value="">— Όλα —</option>
                        @foreach(($customers ?? []) as $c)
                            <option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>
                                {{ $c->last_name }} {{ $c->first_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- ✅ Κρύψε “Ορατό σε Επαγγελματία” στους therapists --}}
                @unless($isTherapist)
                    <div class="col-md-5">
                        <label class="form-label">Ορατό σε Επαγγελματία</label>
                        <select class="form-select" name="professional_id">
                            <option value="">— Όλοι —</option>
                            @foreach(($professionals ?? []) as $p)
                                <option value="{{ $p->id }}" @selected(request('professional_id') == $p->id)>
                                    {{ $p->last_name }} {{ $p->first_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endunless

                <div class="{{ $isTherapist ? 'col-md-2' : 'col-md-2' }} text-end">
                    <button class="btn btn-outline-primary w-100">Φίλτρο</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Λίστα αρχείων</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Αρχείο</th>
                            <th>Περιστατικό</th>

                            {{-- ✅ Κρύψε στήλη “Ορατό σε” στους therapists --}}
                            @unless($isTherapist)
                                <th>Ορατό σε</th>
                            @endunless

                            <th>Σημείωση</th>
                            <th>Ανέβηκε από</th>
                            <th>Ημερομηνία</th>
                            <th class="text-end">Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                            <tr>
                                <td>{{ $doc->id }}</td>

                                <td>
                                    <div class="fw-semibold">{{ $doc->original_name }}</div>
                                    <div class="text-muted small">
                                        {{ $doc->mime_type ?? '—' }}
                                        @if($doc->size)
                                            • {{ number_format($doc->size / 1024, 0, ',', '.') }} KB
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    @php $c = $doc->customer; @endphp
                                    @if($c)
                                        {{ $c->last_name }} {{ $c->first_name }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                {{-- ✅ Κρύψε value “Ορατό σε” στους therapists --}}
                                @unless($isTherapist)
                                    <td>
                                        @php $vp = $doc->visibleProfessional; @endphp
                                        @if($vp)
                                            {{ $vp->last_name }} {{ $vp->first_name }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                @endunless

                                <td style="max-width: 380px;">
                                    <div class="small">{{ $doc->note }}</div>
                                </td>

                                <td>
                                    @php $p = $doc->professional; @endphp
                                    {{ $p?->last_name }} {{ $p?->first_name }}
                                </td>

                                <td>{{ optional($doc->created_at)->format('d-m-Y H:i') }}</td>

                                <td class="text-end">
                                    @if($doc->isPreviewable())
                                        <a class="btn btn-sm btn-outline-primary"
                                           target="_blank"
                                           href="{{ route('documents.view', $doc) }}"
                                           title="Προβολή">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endif

                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="{{ route('documents.download', $doc) }}"
                                       title="Λήψη">
                                        <i class="bi bi-download"></i>
                                    </a>

                                    @php
                                        $canDeleteAll = $user && in_array($user->role, ['owner', 'grammatia'], true);
                                        $isOwnerOfDoc = $user && ((int)$doc->professional_id === (int)$user->id);
                                    @endphp

                                    @if($canDeleteAll || $isOwnerOfDoc)
                                        <form action="{{ route('documents.destroy', $doc) }}"
                                              method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('Σίγουρα θέλετε να διαγράψετε αυτό το αρχείο;');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Διαγραφή">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isTherapist ? 7 : 8 }}" class="text-center py-4 text-muted">
                                    Δεν υπάρχουν αρχεία ακόμα.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $documents->links() }}
    </div>
@endsection
