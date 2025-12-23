<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Professional;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function currentProfessional(): Professional
    {
        // επειδή κάνεις Auth::attempt() από professionals
        return Professional::with('companies')->findOrFail(auth()->id());
    }

    /**
     * Επιστρέφει query για ειδοποιήσεις που επιτρέπεται να βλέπει ο χρήστης.
     * - grammatia: βλέπει ειδοποιήσεις από επαγγελματίες που μοιράζονται τουλάχιστον ένα ίδιο γραφείο (company).
     * - όλοι οι άλλοι: βλέπουν μόνο τις δικές τους.
     */
    private function visibleNotificationsQuery(Professional $me): Builder
    {
        $q = Notification::query()->orderByDesc('notify_at');

        if ($me->role === 'grammatia') {
            $companyIds = $me->companies->pluck('id');

            // αν δεν έχει γραφείο, μην δείχνεις τίποτα
            if ($companyIds->isEmpty()) {
                return $q->whereRaw('1=0');
            }

            // χρειάζεται Notification::professional() relation (belongsTo Professional)
            return $q->whereHas('professional', function ($p) use ($companyIds) {
                $p->whereHas('companies', function ($c) use ($companyIds) {
                    $c->whereIn('companies.id', $companyIds);
                });
            });
        }

        return $q->where('professional_id', $me->id);
    }

    public function index(Request $request)
    {
        $me = $this->currentProfessional();

        $from = $request->get('from');
        $to   = $request->get('to');
        $onlyUnread = $request->boolean('unread');

        $q = $this->visibleNotificationsQuery($me);

        if ($from) {
            $q->whereDate('notify_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('notify_at', '<=', $to);
        }
        if ($onlyUnread) {
            $q->where('is_read', false);
        }

        $notifications = $q->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications', 'from', 'to', 'onlyUnread'));
    }

    public function create()
    {
        return view('notifications.create');
    }

    public function store(Request $request)
    {
        $me = $this->currentProfessional();

        $data = $request->validate([
            'note'      => ['required', 'string', 'max:5000'],
            'notify_at' => ['required', 'date'], // expects "YYYY-MM-DDTHH:MM" from datetime-local too
        ]);

        Notification::create([
            'professional_id' => $me->id,
            'note'            => $data['note'],
            'notify_at'       => Carbon::parse($data['notify_at']),
            'is_read'         => false,
        ]);

        return redirect()->route('notifications.index')->with('success', 'Η ειδοποίηση καταχωρήθηκε.');
    }

    public function edit(Notification $notification)
    {
        $this->authorizeVisible($notification);

        return view('notifications.edit', compact('notification'));
    }

    public function update(Request $request, Notification $notification)
    {
        $this->authorizeVisible($notification);

        $data = $request->validate([
            'note'      => ['required', 'string', 'max:5000'],
            'notify_at' => ['required', 'date'],
        ]);

        $notification->update($data);

        return redirect()->route('notifications.index')->with('success', 'Η ειδοποίηση ενημερώθηκε.');
    }

    public function destroy(Notification $notification)
    {
        $this->authorizeVisible($notification);

        $notification->delete();

        return back()->with('success', 'Η ειδοποίηση διαγράφηκε.');
    }

    /**
     * Endpoint που καλεί το JS για “due notifications”
     */
    public function due()
    {
        $me = $this->currentProfessional();

        $due = $this->visibleNotificationsQuery($me)
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
        $this->authorizeVisible($notification);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function authorizeVisible(Notification $notification): void
    {
        $me = $this->currentProfessional();

        // ο δημιουργός πάντα έχει πρόσβαση
        if ((int) $notification->professional_id === (int) $me->id) {
            return;
        }

        // grammatia: πρόσβαση αν ο ιδιοκτήτης της ειδοποίησης είναι σε κοινό γραφείο
        if ($me->role === 'grammatia') {
            $myCompanyIds = $me->companies->pluck('id');

            $owner = Professional::with('companies')->find($notification->professional_id);
            if (!$owner) {
                abort(403, 'Δεν έχετε πρόσβαση.');
            }

            $ownerCompanyIds = $owner->companies->pluck('id');

            if ($myCompanyIds->intersect($ownerCompanyIds)->isNotEmpty()) {
                return;
            }
        }

        abort(403, 'Δεν έχετε πρόσβαση.');
    }
}
