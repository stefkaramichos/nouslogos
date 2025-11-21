<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Http\Request;
use Carbon\Carbon;

class SettlementController extends Controller
{

    
    public function index(Request $request)
    {

        $user = Auth::user();

        // Αν δεν είναι συνδεδεμένος ή δεν είναι owner → 403
        if (!$user || $user->role !== 'owner') {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        // mapping professionals -> συνεταίροι
        $partnerProfessionals = [
            'partner1' => 1,   // Γιάννης
            'partner2' => 2,   // Ελένη
        ];

        // === Φίλτρο ημερομηνιών ===
        $from = $request->input('from');
        $to   = $request->input('to');

        if (!$from && !$to) {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to   = Carbon::now()->endOfMonth()->toDateString();
        }

        if ($from && !$to) {
            $to = $from;
        }

        // === Φέρνουμε πληρωμές ===
        $payments = Payment::with(['appointment.professional', 'customer'])
            ->when($from, function ($q) use ($from) {
                $q->whereHas('appointment', function ($qq) use ($from) {
                    $qq->whereDate('start_time', '>=', $from);
                });
            })
            ->when($to, function ($q) use ($to) {
                $q->whereHas('appointment', function ($qq) use ($to) {
                    $qq->whereDate('start_time', '<=', $to);
                });
            })
            ->get();

        // === Συνολικά ===
        $totalAmount = 0;         // σύνολο εισπράξεων (όλα)
        $cashToBank  = 0;         // ποσό από ΜΕΤΡΗΤΑ με απόδειξη που (θεωρητικά) πάει στην τράπεζα
        $cashNoTax   = 0;         // μετρητά χωρίς απόδειξη (σύνολο)
        $cashWithTax = 0;         // μετρητά με απόδειξη (σύνολο)
        $cardTotal   = 0;         // σύνολο πληρωμών με κάρτα (bruto, αυτό που είναι ήδη στην τράπεζα)

        // καθαρό της επιχείρησης από κάρτα (πληροφοριακά)
        $bankFromCard = 0;

        // ποσά επαγγελματία στους συνεταίρους
        $partner1Personal = 0;
        $partner2Personal = 0;

        // κοινό "μαύρο" ταμείο (χωρίς απόδειξη), μοιράζεται 50-50
        $sharedPool = 0;

        // ΠΟΣΑ 10€ από ΚΑΡΤΑ που αφορούν Γιάννη/Ελένη (για να αφαιρεθούν από τα μετρητά προς κατάθεση)
        $partnerCardProfessional = 0;

        // για διαγράμματα ανά μέρα
        $daily = []; // ['Y-m-d' => ['bank' => ..., 'partners' => ... ]]

        foreach ($payments as $payment) {
            $appointment = $payment->appointment;
            if (!$appointment) {
                continue;
            }

            $dateKey = $appointment->start_time
                ? $appointment->start_time->toDateString()
                : ($payment->paid_at ? Carbon::parse($payment->paid_at)->toDateString() : null);

            if ($dateKey && !isset($daily[$dateKey])) {
                $daily[$dateKey] = [
                    'bank'     => 0,
                    'partners' => 0,
                ];
            }

            $amount          = (float) $payment->amount;
            $method          = $payment->method;   // cash / card / null
            $tax             = $payment->tax ?? 'N'; // 'Y' ή 'N'
            $professionalAmt = (float) ($appointment->professional_amount ?? 0);
            $professionalId  = $appointment->professional_id;

            $totalAmount += $amount;

            $isPartnerProfessional = in_array($professionalId, $partnerProfessionals, true);

            // ===== CASE 1: CASH χωρίς απόδειξη =====
            if ($method === 'cash' && $tax === 'N') {
                $cashNoTax += $amount;

                if ($isPartnerProfessional) {
                    // Ραντεβού Γιάννης/Ελένη
                    $remainder = max($amount - $professionalAmt, 0);

                    if ($professionalId === $partnerProfessionals['partner1']) {
                        $partner1Personal += $professionalAmt;
                    } elseif ($professionalId === $partnerProfessionals['partner2']) {
                        $partner2Personal += $professionalAmt;
                    }

                    // Υπόλοιπο στο κοινό μαύρο ταμείο
                    $sharedPool += $remainder;

                    if ($dateKey) {
                        // όλο το ποσό πάει στους συνεταίρους (προσωπικό + μαύρο)
                        $daily[$dateKey]['partners'] += $amount;
                    }
                } else {
                    // Τρίτος επαγγελματίας: όλο στους συνεταίρους 50-50
                    $sharedPool += $amount;

                    if ($dateKey) {
                        $daily[$dateKey]['partners'] += $amount;
                    }
                }

                continue;
            }

            // ===== CASE 2: CASH με απόδειξη =====
            if ($method === 'cash' && $tax === 'Y') {
                $cashWithTax += $amount;

                if ($isPartnerProfessional) {
                    // Προσωπικό ποσό επαγγελματία
                    if ($professionalId === $partnerProfessionals['partner1']) {
                        $partner1Personal += $professionalAmt;
                    } elseif ($professionalId === $partnerProfessionals['partner2']) {
                        $partner2Personal += $professionalAmt;
                    }

                    // Υπόλοιπο πρέπει να μπει στην τράπεζα από μετρητά
                    $bankPortion = max($amount - $professionalAmt, 0);
                    $cashToBank += $bankPortion;

                    if ($dateKey) {
                        $daily[$dateKey]['bank']     += $bankPortion;
                        $daily[$dateKey]['partners'] += $professionalAmt;
                    }
                } else {
                    // Τρίτος επαγγελματίας: όλο στην τράπεζα από μετρητά
                    $cashToBank += $amount;

                    if ($dateKey) {
                        $daily[$dateKey]['bank'] += $amount;
                    }
                }

                continue;
            }

            // ===== CASE 3: CARD (με απόδειξη) =====
            if ($method === 'card') {
                $cardTotal += $amount; // αυτό είναι "Ήδη στην τράπεζα (bruto)"

                if ($isPartnerProfessional) {
                    if ($professionalId === $partnerProfessionals['partner1']) {
                        $partner1Personal += $professionalAmt;
                    } elseif ($professionalId === $partnerProfessionals['partner2']) {
                        $partner2Personal += $professionalAmt;
                    }

                    // 10άρια από κάρτα που αφορούν συνεταίρους
                    $partnerCardProfessional += $professionalAmt;

                    // Καθαρό της επιχείρησης από κάρτα
                    $bankPortion = max($amount - $professionalAmt, 0);
                    $bankFromCard += $bankPortion;

                    if ($dateKey) {
                        $daily[$dateKey]['bank']     += $bankPortion;
                        $daily[$dateKey]['partners'] += $professionalAmt;
                    }
                } else {
                    // Τρίτος επαγγελματίας: όλο της επιχείρησης
                    $bankFromCard += $amount;

                    if ($dateKey) {
                        $daily[$dateKey]['bank'] += $amount;
                    }
                }

                continue;
            }

            // ===== Παλιά/άγνωστα δεδομένα: όλα στην επιχείρηση (τράπεζα από μετρητά) =====
            $cashToBank += $amount;
            if ($dateKey) {
                $daily[$dateKey]['bank'] += $amount;
            }
        }

        // Τελικός επιμερισμός sharedPool 50-50
        $partner1Total = $partner1Personal + ($sharedPool / 2);
        $partner2Total = $partner2Personal + ($sharedPool / 2);

        // --- ΕΔΩ ΓΙΝΕΤΑΙ ΑΥΤΟ ΠΟΥ ΖΗΤΗΣΕΣ ---
        // Από τα "μετρητά προς κατάθεση" αφαιρούμε τα 10€ που έχουν πληρωθεί με κάρτα
        // σε ραντεβού του Γιάννη/Ελένης (τα δίνεις στους συνεταίρους από τα μετρητά).
        $cashToBank = max($cashToBank - $partnerCardProfessional, 0);

        // Ποσό επιχείρησης στην τράπεζα (σύνολο κινήσεων: κάρτα + μετρητά κατάθεση)
        $companyBankTotal = $cashToBank + $cardTotal;

        // Δεδομένα για Chart.js (κατανομή)
        $chartDistribution = [
            'labels' => ['Επιχείρηση (τράπεζα)', 'Συνεταίρος 1', 'Συνεταίρος 2'],
            'data'   => [
                round($companyBankTotal, 2),
                round($partner1Total, 2),
                round($partner2Total, 2),
            ],
        ];

        // Δεδομένα ανά ημέρα
        ksort($daily);
        $dailyChart = [
            'labels'   => array_keys($daily),
            'bank'     => array_map(fn($d) => round($d['bank'], 2), $daily),
            'partners' => array_map(fn($d) => round($d['partners'], 2), $daily),
        ];

        $filters = [
            'from' => $from,
            'to'   => $to,
        ];

        return view('settlements.index', compact(
            'filters',
            'totalAmount',
            'cashToBank',        // Μετρητά προς κατάθεση (ΜΕΤΑ την αφαίρεση των 10€ από κάρτες)
            'cardTotal',         // Πληρωμές με κάρτα (ήδη στην τράπεζα, bruto)
            'companyBankTotal',  // Σύνολο επιχείρησης στην τράπεζα (cashToBank + cardTotal)
            'cashNoTax',
            'cashWithTax',
            'bankFromCard',      // πληροφοριακά: καθαρό επιχείρησης από κάρτες
            'sharedPool',
            'partner1Personal',
            'partner2Personal',
            'partner1Total',
            'partner2Total',
            'chartDistribution',
            'dailyChart',
            'payments'
        ));
    }

}
