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
    private function canEditDocument($user, Document $document): bool
    {
        $canEditAll = $user && in_array($user->role, ['owner', 'grammatia'], true);
        $isOwnerOfDoc = $user && ((int)$document->professional_id === (int)$user->id);

        return $canEditAll || $isOwnerOfDoc;
    }

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
            'customer_id'             => 'nullable|exists:customers,id',
            'visible_professional_id' => 'nullable|exists:professionals,id',
            'file'                    => 'required',
            'file.*'                  => 'file|max:20480', // 20MB ανά αρχείο
            'note'                    => 'nullable|string|max:5000',
        ], [
            'customer_id.required' => 'Επιλέξτε περιστατικό (customer).',
            'file.required'        => 'Το αρχείο είναι υποχρεωτικό.',
            'file.*.file'          => 'Κάποιο από τα αρχεία δεν είναι έγκυρο.',
            'file.*.max'           => 'Κάθε αρχείο πρέπει να είναι έως 20MB.',
        ]);

        $files = $request->file('file');
        if (!is_array($files)) {
            $files = $files ? [$files] : [];
        }

        // ✅ IMPORTANT:
        // Αν το select επιστρέφει "" ή 0 τότε το κάνουμε NULL (όχι 0) για να μην σκάει FK.
        $customerId = $data['customer_id'] ? (int)$data['customer_id'] : null;

        $visibleId = $request->filled('visible_professional_id')
            ? (int)$request->input('visible_professional_id')
            : null;

        $uploadedCount = 0;

        foreach ($files as $file) {
            if (!$file) continue;

            $storedPath = $file->store('documents', 'public'); // storage/app/public/documents/...

            Document::create([
                'customer_id'             => $customerId,
                'professional_id'         => Auth::id(),   // uploader
                'visible_professional_id' => $visibleId,   // nullable

                'note'          => $data['note'] ?? null,
                'original_name' => $file->getClientOriginalName(),
                'stored_name'   => basename($storedPath),
                'path'          => $storedPath,
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize(),
            ]);

            $uploadedCount++;
        }

        if ($uploadedCount === 0) {
            return redirect()->back()->with('error', 'Δεν βρέθηκαν αρχεία για ανέβασμα.');
        }

        return redirect()->route('documents.index')
            ->with('success', $uploadedCount === 1
                ? 'Το αρχείο ανέβηκε επιτυχώς.'
                : "Ανέβηκαν επιτυχώς {$uploadedCount} αρχεία.");
    }

    public function edit(Document $document)
    {
        $user = Auth::user();

        if (!$this->canEditDocument($user, $document)) {
            abort(403);
        }

        $customers = Customer::orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $professionals = Professional::where('is_active', 1)
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('documents.edit', compact('document', 'customers', 'professionals'));
    }

    public function update(Request $request, Document $document)
    {
        $user = Auth::user();

        if (!$this->canEditDocument($user, $document)) {
            abort(403);
        }

        $data = $request->validate([
            'customer_id'             => 'required|exists:customers,id',
            'visible_professional_id' => 'nullable|exists:professionals,id',
            'file'                    => 'nullable|file|max:20480', // 20MB
            'note'                    => 'nullable|string|max:5000',
        ], [
            'customer_id.required' => 'Επιλέξτε περιστατικό (customer).',
            'file.file'            => 'Μη έγκυρο αρχείο.',
            'file.max'             => 'Το αρχείο πρέπει να είναι έως 20MB.',
        ]);

        $visibleId = $request->filled('visible_professional_id')
            ? (int)$request->input('visible_professional_id')
            : null;

        $updatePayload = [
            'customer_id'             => (int)$data['customer_id'],
            'visible_professional_id' => $visibleId,
            'note'                    => $data['note'] ?? null,
        ];

        if ($request->hasFile('file')) {
            $newFile = $request->file('file');
            $storedPath = $newFile->store('documents', 'public');

            if ($document->path && Storage::disk('public')->exists($document->path)) {
                Storage::disk('public')->delete($document->path);
            }

            $updatePayload = array_merge($updatePayload, [
                'original_name' => $newFile->getClientOriginalName(),
                'stored_name'   => basename($storedPath),
                'path'          => $storedPath,
                'mime_type'     => $newFile->getClientMimeType(),
                'size'          => $newFile->getSize(),
            ]);
        }

        $document->update($updatePayload);

        return redirect()->route('documents.index')
            ->with('success', 'Το αρχείο ενημερώθηκε επιτυχώς.');
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
        if (!$this->canEditDocument($user, $document)) {
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
