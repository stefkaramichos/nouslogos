<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Carbon\Carbon; 
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function currentProfessionalId(): int
    {
        return (int) auth()->id(); // επειδή κάνεις Auth::attempt() από professionals
    }

    public function index(Request $request)
    {
        $pid = $this->currentProfessionalId();

        $from = $request->get('from');
        $to   = $request->get('to');
        $onlyUnread = $request->boolean('unread');

        $q = Notification::where('professional_id', $pid)->orderByDesc('notify_at');

        if ($from) $q->whereDate('notify_at', '>=', $from);
        if ($to)   $q->whereDate('notify_at', '<=', $to);
        if ($onlyUnread) $q->where('is_read', false);

        $notifications = $q->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications', 'from', 'to', 'onlyUnread'));
    }

    public function create()
    {
        return view('notifications.create');
    }

    public function store(Request $request)
    {
        $pid = $this->currentProfessionalId();

        $data = $request->validate([
            'note'      => ['required', 'string', 'max:5000'],
            'notify_at' => ['required', 'date'], // expects "YYYY-MM-DDTHH:MM" from datetime-local too
        ]);

        Notification::create([
            'professional_id' => $pid,
            'note'            => $data['note'],
            'notify_at'       => Carbon::parse($data['notify_at']),
            'is_read'         => false,
        ]);

        return redirect()->route('notifications.index')->with('success', 'Η ειδοποίηση καταχωρήθηκε.');
    }

    public function edit(Notification $notification)
    {
        $this->authorizeOwner($notification);
        return view('notifications.edit', compact('notification'));
    }

    public function update(Request $request, Notification $notification)
    {
        $this->authorizeOwner($notification);

        $data = $request->validate([
            'note'      => ['required', 'string', 'max:5000'],
            'notify_at' => ['required', 'date'],
        ]);

        $notification->update($data);

        return redirect()->route('notifications.index')->with('success', 'Η ειδοποίηση ενημερώθηκε.');
    }

    public function destroy(Notification $notification)
    {
        $this->authorizeOwner($notification);
        $notification->delete();

        return back()->with('success', 'Η ειδοποίηση διαγράφηκε.');
    }

    /**
     * Endpoint που καλεί το JS για “due notifications”
     */
    public function due()
    {
        $pid = $this->currentProfessionalId();

        $due = Notification::where('professional_id', $pid)
            ->where('is_read', false)
            ->where('notify_at', '<=', now())
            ->orderBy('notify_at')
            ->limit(10)
            ->get();

        return response()->json(
            $due->map(fn ($n) => [
                'id' => $n->id,
                'note' => $n->note,
                // ✅ στείλε string (όχι Carbon) για να ΜΗΝ γίνει UTC conversion
                'notify_at' => optional($n->notify_at)->format('Y-m-d H:i:s'),
                // ✅ έτοιμο 24ωρο για εμφάνιση
                'notify_at_text' => optional($n->notify_at)->format('d/m/Y H:i'),
            ])->values()
        );
    }


    /**
     * Mark as read (για να μην ξαναπετάει popup)
     */
    public function markRead(Notification $notification)
    {
        $this->authorizeOwner($notification);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function authorizeOwner(Notification $notification): void
    {
        if ((int)$notification->professional_id !== $this->currentProfessionalId()) {
            abort(403, 'Δεν έχετε πρόσβαση.');
        }
    }
}
