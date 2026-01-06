@extends('layouts.app')

@section('title', 'Αρχεία')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('documents.create') }}" class="btn btn-primary">
                <i class="bi bi-upload"></i> Νέο αρχείο
            </a>
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
                                        $user = Auth::user();
                                        $canDeleteAll = $user && in_array($user->role, ['owner', 'grammatia']);
                                        $isOwnerOfDoc = $user && ((int)$doc->professional_id === (int)$user->id);
                                    @endphp

                                    @if($canDeleteAll || $isOwnerOfDoc)
                                        <form action="{{ route('documents.destroy', $doc) }}" method="POST" class="d-inline"
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
                                <td colspan="6" class="text-center py-4 text-muted">
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
