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
        return Professional::with('companies')->findOrFail(auth()->id());
    }

    /**
     * Επιστρέφει query για ειδοποιήσεις που επιτρέπεται να βλέπει ο χρήστης.
     * + ONLY FUTURE notifications
     * + ORDER BY notify_at ASC
     */
    private function visibleNotificationsQuery(Professional $me): Builder
    {
        // ✅ ONLY FUTURE + ASC
        $q = Notification::query()
            ->where('notify_at', '>=', now())
            ->orderBy('notify_at', 'asc');

        if ($me->role === 'grammatia') {
            $companyIds = $me->companies->pluck('id');

            if ($companyIds->isEmpty()) {
                return $q->whereRaw('1=0');
            }

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

        // (optional) extra filters
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
            'notify_at' => ['required', 'date'],
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
     * Endpoint για “due notifications” (ΑΥΤΟ δείχνει τα ληγμένα/τρέχοντα popups)
     * Εδώ σωστά είναι <= now()
     */
    public function due()
    {
        $me = $this->currentProfessional();

        $due = Notification::query()
            ->when($me->role === 'grammatia', function ($q) use ($me) {
                $companyIds = $me->companies->pluck('id');
                if ($companyIds->isEmpty()) {
                    return $q->whereRaw('1=0');
                }
                return $q->whereHas('professional', function ($p) use ($companyIds) {
                    $p->whereHas('companies', function ($c) use ($companyIds) {
                        $c->whereIn('companies.id', $companyIds);
                    });
                });
            }, function ($q) use ($me) {
                return $q->where('professional_id', $me->id);
            })
            ->where('is_read', false)
            ->where('notify_at', '<=', now())
            ->orderBy('notify_at', 'asc')
            ->limit(10)
            ->get();

        return response()->json(
            $due->map(fn ($n) => [
                'id' => $n->id,
                'note' => $n->note,
                'notify_at' => optional($n->notify_at)->format('Y-m-d H:i:s'),
                'notify_at_text' => optional($n->notify_at)->format('d/m/Y H:i'),
            ])->values()
        );
    }

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

        if ((int) $notification->professional_id === (int) $me->id) {
            return;
        }

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
