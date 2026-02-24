<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentTrashController extends Controller
{
    private function blockTherapist()
    {
        if (auth()->user()?->role === 'therapist') {
            abort(403, 'Δεν έχετε πρόσβαση.');
        }
    }

    public function index(Request $request)
    {
        $this->blockTherapist();

        $user = $request->user();

        $from = $request->get('from');
        $to   = $request->get('to');

        $q = Appointment::onlyTrashed()
            ->with(['customer', 'professional'])
            ->orderByDesc('deleted_at');

        // Αν θες περιορισμό ανά company (προαιρετικό αλλά καλό)
        // if (!empty($user->company_id)) {
        //     $q->where('company_id', $user->company_id);
        // }

        if ($from) $q->whereDate('deleted_at', '>=', $from);
        if ($to)   $q->whereDate('deleted_at', '<=', $to);

        $appointments = $q->paginate(20)->withQueryString();

        return view('appointments.recycle', compact('appointments', 'from', 'to', 'user'));
    }

    public function restore(Request $request, $appointmentId)
    {
        $this->blockTherapist();

        $user = $request->user();

        $a = Appointment::onlyTrashed()->whereKey($appointmentId)->firstOrFail();

        if (!empty($user->company_id) && $a->company_id !== $user->company_id) {
            abort(403, 'Δεν έχετε πρόσβαση.');
        }

        $a->restore();

        return back()->with('success', 'Το ραντεβού επαναφέρθηκε.');
    }

    public function forceDelete(Request $request, $appointmentId)
    {
        $this->blockTherapist();

        $user = $request->user();

        $a = Appointment::onlyTrashed()->whereKey($appointmentId)->firstOrFail();

        if (!empty($user->company_id) && $a->company_id !== $user->company_id) {
            abort(403, 'Δεν έχετε πρόσβαση.');
        }

        $a->forceDelete();

        return back()->with('success', 'Το ραντεβού διαγράφηκε οριστικά.');
    }
}
