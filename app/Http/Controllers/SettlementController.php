<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Expense;
use App\Models\Professional;
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
            'partner1' => 1, // Γιάννης
            'partner2' => 2, // Ελένη
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
        $totalAmount   = 0; // σύνολο εισπράξεων (όλα)
        $cashToBank    = 0; // ποσό από ΜΕΤΡΗΤΑ με απόδειξη που (θεωρητικά) πάει στην τράπεζα
        $cashNoTax     = 0; // μετρητά χωρίς απόδειξη (σύνολο)
        $cashWithTax   = 0; // μετρητά με απόδειξη (σύνολο)
        $cardTotal     = 0; // σύνολο πληρωμών με κάρτα (bruto, αυτό που είναι ήδη στην τράπεζα)
        $bankFromCard  = 0; // καθαρό της επιχείρησης από κάρτα (πληροφοριακά)

        // ποσά επαγγελματία στους συνεταίρους
        $partner1Personal = 0;
        $partner2Personal = 0;

        // κοινό "μαύρο" ταμείο (χωρίς απόδειξη), μοιράζεται 50-50
        $sharedPool = 0;

        // ΠΟΣΑ 10€ από ΚΑΡΤΑ που αφορούν Γιάννη/Ελένη
        // (για να αφαιρεθούν από τα μετρητά προς κατάθεση)
        $partnerCardProfessional = 0;

        // ΠΟΣΑ 10€ από ΜΕΤΡΗΤΑ ΧΩΡΙΣ ΑΠΟΔΕΙΞΗ (Γιάννης/Ελένη)
        $partnerCashNoTaxProfessional = 0;

        // για διαγράμματα ανά μέρα
        // ['Y-m-d' => ['giannis' => ..., 'eleni' => ... ]]
        $daily = [];

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
                    'giannis' => 0,
                    'eleni'   => 0,
                ];
            }

            $amount          = (float) $payment->amount;
            $method          = $payment->method;            // cash / card / null
            $tax             = $payment->tax ?? 'N';        // 'Y' ή 'N'
            $professionalAmt = (float) ($appointment->professional_amount ?? 0);
            $professionalId  = $appointment->professional_id;

            $totalAmount += $amount;

            $isPartnerProfessional = in_array($professionalId, $partnerProfessionals, true);

            // ===== Προσωπικά έσοδα Γιάννη / Ελένης + ημερήσια ενημέρωση =====
            if ($isPartnerProfessional) {
                if ($professionalId === $partnerProfessionals['partner1']) {
                    $partner1Personal += $professionalAmt;

                    if ($dateKey) {
                        $daily[$dateKey]['giannis'] += $professionalAmt;
                    }

                } elseif ($professionalId === $partnerProfessionals['partner2']) {
                    $partner2Personal += $professionalAmt;

                    if ($dateKey) {
                        $daily[$dateKey]['eleni'] += $professionalAmt;
                    }
                }
            }

            // ===== CASE 1: CASH χωρίς απόδειξη =====
            if ($method === 'cash' && $tax === 'N') {
                $cashNoTax += $amount;

                if ($isPartnerProfessional) {
                    // Μαζεύουμε τα 10€ του επαγγελματία από ΜΑΥΡΑ
                    $partnerCashNoTaxProfessional += ($professionalAmt > 0 ? $professionalAmt : 0);

                    // ΟΛΟ το ποσό πάει στο κοινό μαύρο ταμείο
                    $sharedPool += $amount;
                } else {
                    // Τρίτος επαγγελματίας: όλο στο κοινό μαύρο ταμείο
                    $sharedPool += $amount;
                }

                continue;
            }

            // ===== CASE 2: CASH με απόδειξη =====
            if ($method === 'cash' && $tax === 'Y') {
                $cashWithTax += $amount;

                if ($isPartnerProfessional) {
                    // Υπόλοιπο πρέπει να μπει στην τράπεζα από μετρητά
                    $bankPortion = max($amount - $professionalAmt, 0);
                    $cashToBank += $bankPortion;

                } else {
                    // Τρίτος επαγγελματίας: όλο στην τράπεζα από μετρητά
                    $cashToBank += $amount;
                }

                continue;
            }

            // ===== CASE 3: CARD (με απόδειξη) =====
            if ($method === 'card') {
                // αυτό είναι "Ήδη στην τράπεζα (bruto)"
                $cardTotal += $amount;

                if ($isPartnerProfessional) {
                    // 10άρια από κάρτα που αφορούν συνεταίρους
                    $partnerCardProfessional += ($professionalAmt > 0 ? $professionalAmt : 0);

                    // Καθαρό της επιχείρησης από κάρτα
                    $bankPortion = max($amount - $professionalAmt, 0);
                    $bankFromCard += $bankPortion;

                } else {
                    // Τρίτος επαγγελματίας: όλο της επιχείρησης
                    $bankFromCard += $amount;
                }

                continue;
            }

            // ===== Παλιά/άγνωστα δεδομένα: όλα στην επιχείρηση (τράπεζα από μετρητά) =====
            $cashToBank += $amount;
        }

        // Τελικός επιμερισμός sharedPool 50-50
        $partner1Total = $partner1Personal + ($sharedPool / 2);
        $partner2Total = $partner2Personal + ($sharedPool / 2);

        // --- ΤΕΛΙΚΗ ΔΙΟΡΘΩΣΗ ---
        // Από τα "μετρητά προς κατάθεση" αφαιρούμε:
        //  - τα 10€ από ΚΑΡΤΑ (partnerCardProfessional)
        //  - τα 10€ από ΜΕΤΡΗΤΑ ΧΩΡΙΣ ΑΠΟΔΕΙΞΗ (partnerCashNoTaxProfessional)
        $cashToBank = max(
            $cashToBank - $partnerCardProfessional - $partnerCashNoTaxProfessional,
            0
        );

        // Ποσό επιχείρησης στην τράπεζα (σύνολο κινήσεων: κάρτα + μετρητά κατάθεση)
        $companyBankTotal = $cashToBank + $cardTotal;

        // 🔹 Δεδομένα για Chart.js (κατανομή)
        $chartDistribution = [
            'labels' => [
                'Μετρητά προς κατάθεση',
                'Γιάννης #1',
                'Ελένη #2',
            ],
            'data'   => [
                round($cashToBank, 2),
                round($partner1Total, 2),
                round($partner2Total, 2),
            ],
        ];

        // 🔹 Δεδομένα για 2ο γράφημα: ΜΟΝΟ Γιάννης / Ελένη ανά μέρα
        ksort($daily);

        $dailyChart = [
            'labels'  => array_keys($daily),
            'giannis' => array_map(fn($d) => round($d['giannis'], 2), $daily),
            'eleni'   => array_map(fn($d) => round($d['eleni'], 2), $daily),
        ];

        // ----------------- ΕΞΟΔΑ & ΜΙΣΘΟΙ -----------------

        // Έξοδα από πίνακα expenses στο ίδιο διάστημα
        $expensesQuery = Expense::query();

        if ($from) {
            $expensesQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $expensesQuery->whereDate('created_at', '<=', $to);
        }

        $expensesList  = $expensesQuery->orderBy('created_at', 'desc')->get();
        $expensesTotal = (float) $expensesList->sum('amount');

        // Πόσοι μήνες καλύπτει το διάστημα (π.χ. 2 μήνες => 2 μισθοί)
        // Πόσες μέρες καλύπτει το διάστημα (inclusive)
        $startDate = Carbon::parse($from);
        $endDate   = Carbon::parse($to);

        // diffInDays = διαφορά χωρίς να μετράει και τις 2 άκρες, οπότε +1 για inclusive
        $daysDiff = $startDate->diffInDays($endDate) + 1;

        // Από 0–31 ημέρες => 1 μισθός, 32–62 => 2, κ.ο.κ.
        $monthsCount = (int) ceil($daysDiff / 31);

        // ασφαλιστική δικλείδα: τουλάχιστον 1
        if ($monthsCount < 1) {
            $monthsCount = 1;
        }


        // Υπάλληλοι με μισθό
        $employees           = Professional::whereNotNull('salary')
            ->where('salary', '>', 0)
            ->orderBy('last_name')
            ->get();

        $employeesSalaryRows  = [];
        $employeesTotalSalary = 0.0;

        foreach ($employees as $employee) {
            $monthly = (float) $employee->salary;
            $period  = $monthly * $monthsCount;

            $employeesSalaryRows[] = [
                'professional'   => $employee,
                'monthly_salary' => $monthly,
                'months'         => $monthsCount,
                'period_salary'  => $period,
            ];

            $employeesTotalSalary += $period;
        }

        // Σύνολο εξόδων = έξοδα + μισθοί όλων των υπαλλήλων
        $totalOutflow = $expensesTotal + $employeesTotalSalary;

        // "Net" της επιχείρησης στην τράπεζα μετά τα έξοδα
        $companyNetAfterExpenses = $companyBankTotal - $totalOutflow;

        $filters = [
            'from' => $from,
            'to'   => $to,
        ];

        return view('settlements.index', compact(
            'filters',
            'totalAmount',
            'cashToBank',          // Μετρητά προς κατάθεση
            'cardTotal',           // Πληρωμές με κάρτα (πληροφοριακά στα cards μόνο)
            'companyBankTotal',    // Σύνολο επιχείρησης στην τράπεζα (cashToBank + cardTotal)
            'cashNoTax',
            'cashWithTax',
            'bankFromCard',
            'sharedPool',
            'partner1Personal',
            'partner2Personal',
            'partner1Total',
            'partner2Total',
            'chartDistribution',
            'dailyChart',
            'payments',
            // ΝΕΑ δεδομένα για έξοδα + μισθούς
            'expensesList',
            'expensesTotal',
            'monthsCount',
            'employeesSalaryRows',
            'employeesTotalSalary',
            'totalOutflow',
            'companyNetAfterExpenses'
        ));
    }
}
