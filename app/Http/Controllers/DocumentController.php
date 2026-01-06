<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::with('professional')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('documents.index', compact('documents'));
    }

    public function create()
    {
        return view('documents.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|max:10240', // 10MB
            'note' => 'nullable|string|max:5000',
        ], [
            'file.required' => 'Το αρχείο είναι υποχρεωτικό.',
            'file.file'     => 'Μη έγκυρο αρχείο.',
            'file.max'      => 'Το αρχείο πρέπει να είναι έως 10MB.',
        ]);

        $file = $request->file('file');

        $storedPath = $file->store('documents', 'public'); // storage/app/public/documents/...
        $doc = Document::create([
            'professional_id' => Auth::id(),
            'note'            => $data['note'] ?? null,
            'original_name'   => $file->getClientOriginalName(),
            'stored_name'     => basename($storedPath),
            'path'            => $storedPath,
            'mime_type'       => $file->getClientMimeType(),
            'size'            => $file->getSize(),
        ]);

        return redirect()->route('documents.index')
            ->with('success', 'Το αρχείο ανέβηκε επιτυχώς.');
    }

    public function download(Document $document)
    {
        if (!Storage::disk('public')->exists($document->path)) {
            abort(404);
        }

        return Storage::disk('public')->download($document->path, $document->original_name);
    }

    public function view(Document $document)
    {
        if (!Storage::disk('public')->exists($document->path)) {
            abort(404);
        }

        if (!$document->isPreviewable()) {
            // αν δεν υποστηρίζεται preview -> κατέβασμα
            return redirect()->route('documents.download', $document);
        }

        $fullPath = Storage::disk('public')->path($document->path);

        // inline preview στο browser
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

        // therapist -> only own uploads
        $isOwnerOfDoc = $user && ((int)$document->professional_id === (int)$user->id);

        if (!$canDeleteAll && !$isOwnerOfDoc) {
            abort(403);
        }

        // delete file from disk (if exists)
        if ($document->path && Storage::disk('public')->exists($document->path)) {
            Storage::disk('public')->delete($document->path);
        }

        // delete row
        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Το αρχείο διαγράφηκε επιτυχώς.');
    }

}
