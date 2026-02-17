<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $q = Document::query()
            ->with(['professional', 'customer', 'visibleProfessional'])
            ->orderByDesc('created_at');

        // ✅ Optional filters (owner/grammatia μπορούν να τα χρησιμοποιούν για εύρεση)
        if ($request->filled('customer_id')) {
            $q->where('customer_id', (int)$request->input('customer_id'));
        }

        // φίλτρο "ορατό σε επαγγελματία"
        if ($request->filled('professional_id')) {
            $q->where('visible_professional_id', (int)$request->input('professional_id'));
        }

        // ✅ Access control:
        // - owner/grammatia: βλέπουν όλα
        // - therapist: βλέπει μόνο όσα (α) ανέβασε ο ίδιος ή (β) είναι visible σε αυτόν
        if ($user && $user->role === 'therapist') {
            $uid = (int)$user->id;

            $q->where(function ($qq) use ($uid) {
                $qq->where('professional_id', $uid)
                   ->orWhere('visible_professional_id', $uid);
            });
        }

        $documents = $q->paginate(25)->withQueryString();

        // dropdowns για φίλτρα
        $customers = Customer::orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $professionals = Professional::where('is_active', 1)
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('documents.index', compact('documents', 'customers', 'professionals'));
    }

    public function create()
    {
        $customers = Customer::orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $professionals = Professional::where('is_active', 1)
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('documents.create', compact('customers', 'professionals'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'             => 'required|exists:customers,id',
            'visible_professional_id' => 'nullable|exists:professionals,id',
            'file'                    => 'required|file|max:10240', // 10MB
            'note'                    => 'nullable|string|max:5000',
        ], [
            'customer_id.required' => 'Επιλέξτε περιστατικό (customer).',
            'file.required'        => 'Το αρχείο είναι υποχρεωτικό.',
            'file.file'            => 'Μη έγκυρο αρχείο.',
            'file.max'             => 'Το αρχείο πρέπει να είναι έως 10MB.',
        ]);

        $file = $request->file('file');

        $storedPath = $file->store('documents', 'public'); // storage/app/public/documents/...

        // ✅ IMPORTANT:
        // Αν το select επιστρέφει "" τότε το κάνουμε NULL (όχι 0) για να μην σκάει FK.
        $visibleId = $request->filled('visible_professional_id')
            ? (int)$request->input('visible_professional_id')
            : null;

        Document::create([
            'customer_id'             => (int)$data['customer_id'],
            'professional_id'         => Auth::id(),   // uploader
            'visible_professional_id' => $visibleId,   // nullable

            'note'          => $data['note'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'stored_name'   => basename($storedPath),
            'path'          => $storedPath,
            'mime_type'     => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ]);

        return redirect()->route('documents.index')
            ->with('success', 'Το αρχείο ανέβηκε επιτυχώς.');
    }

    public function download(Document $document)
    {
        if (!$document->canBeViewedBy(Auth::user())) {
            abort(403);
        }

        if (!Storage::disk('public')->exists($document->path)) {
            abort(404);
        }

        return Storage::disk('public')->download($document->path, $document->original_name);
    }

    public function view(Document $document)
    {
        if (!$document->canBeViewedBy(Auth::user())) {
            abort(403);
        }

        if (!Storage::disk('public')->exists($document->path)) {
            abort(404);
        }

        // Αν δεν κάνει preview, κάνε download
        if (!$document->isPreviewable()) {
            return redirect()->route('documents.download', $document);
        }

        $fullPath = Storage::disk('public')->path($document->path);

        return response()->file($fullPath, [
            'Content-Type'        => $document->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($document->original_name) . '"',
        ]);
    }

    public function destroy(Document $document)
    {
        $user = Auth::user();

        // owner/grammatia -> delete all
        $canDeleteAll = $user && in_array($user->role, ['owner', 'grammatia'], true);

        // therapist -> μόνο ό,τι ανέβασε ο ίδιος
        $isOwnerOfDoc = $user && ((int)$document->professional_id === (int)$user->id);

        if (!$canDeleteAll && !$isOwnerOfDoc) {
            abort(403);
        }

        if ($document->path && Storage::disk('public')->exists($document->path)) {
            Storage::disk('public')->delete($document->path);
        }

        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Το αρχείο διαγράφηκε επιτυχώς.');
    }
}
