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

        // Μόνο owner
        if (!$user || $user->role !== 'owner') {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        // mapping professionals -> συνεταίροι
        $partnerProfessionals = [
            'partner1' => 1, // Γιάννης
            'partner2' => 2, // Ελένη
        ];

        // ===== ΦΙΛΤΡΟ ΗΜΕΡΟΜΗΝΙΩΝ =====
        $from = $request->input('from');
        $to   = $request->input('to');

        if (!$from && !$to) {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to   = Carbon::now()->endOfMonth()->toDateString();
        }

        if ($from && !$to) {
            $to = $from;
        }

        // ===== ΦΕΡΝΟΥΜΕ ΠΛΗΡΩΜΕΣ =====
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

        // ===== ΣΥΝΟΛΙΚΑ ΠΟΣΑ ΕΙΣΠΡΑΞΕΩΝ =====
        $totalAmount = 0;

        // Μετρητά / κάρτα / απόδειξη
        $cashToBank   = 0; // μετρητά ΜΕ απόδειξη που πάνε στην τράπεζα (εταιρικό μέρος όταν είναι συνεταίρος)
        $cashNoTax    = 0; // μετρητά χωρίς απόδειξη (σύνολο)
        $cashWithTax  = 0; // μετρητά με απόδειξη (σύνολο)
        $cardTotal    = 0; // σύνολο πληρωμών με κάρτα (bruto)

        // Προσωπικά ποσά συνεταίρων
        $partner1Personal = 0; // Γιάννης
        $partner2Personal = 0; // Ελένη

        // Κοινό "μαύρο" ταμείο από μετρητά χωρίς απόδειξη (RAW)
        $sharedPoolRaw = 0;

        // Σύνολο 10€ επαγγελματία από ΚΑΡΤΑ (συνεταίροι) – θα αφαιρεθεί από το κοινό ταμείο
        $partnerCardPersonal = 0;

        // Δεδομένα για ημερήσιο chart
        $daily = []; // ['Y-m-d' => ['giannis' => ..., 'eleni' => ...]]

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
            $method          = $payment->method;             // cash / card / null
            $tax             = $payment->tax ?? 'N';         // 'Y' ή 'N'
            $professionalAmt = (float) ($appointment->professional_amount ?? 0);
            $professionalId  = $appointment->professional_id;

            $totalAmount += $amount;

            $isPartnerProfessional = in_array($professionalId, $partnerProfessionals, true);

            // ===== ΠΡΟΣΩΠΙΚΟ ΠΟΣΟ ΣΥΝΕΤΑΙΡΩΝ (πάντα τα 10€ του επαγγελματία) =====
            if ($isPartnerProfessional && $professionalAmt > 0) {
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

            // ===== CASH =====
            if ($method === 'cash') {

                // --- Με απόδειξη ---
                if ($tax === 'Y') {
                    $cashWithTax += $amount;

                    if ($isPartnerProfessional && $professionalAmt > 0) {
                        // Στην τράπεζα πάει μόνο το εταιρικό κομμάτι (π.χ. 35 - 10 = 25)
                        $cashToBank += max($amount - $professionalAmt, 0);
                    } else {
                        // Τρίτος επαγγελματίας → όλο στην εταιρεία
                        $cashToBank += $amount;
                    }

                    continue;
                }

                // --- Χωρίς απόδειξη ---
                $cashNoTax += $amount;

                if ($isPartnerProfessional) {
                    // στο RAW κοινό ταμείο μπαίνει ΜΟΝΟ το εταιρικό κομμάτι (amount - professionalAmt)
                    $sharedPoolRaw += max($amount - $professionalAmt, 0);
                } else {
                    // τρίτος επαγγελματίας: όλο στο RAW κοινό ταμείο
                    $sharedPoolRaw += $amount;
                }

                continue;
            }

            // ===== CARD =====
            if ($method === 'card') {
                $cardTotal += $amount; // bruto (ό,τι περνάει από POS)

                if ($isPartnerProfessional && $professionalAmt > 0) {
                    // Τα 10€ του συνεταίρου από κάρτα θα πληρωθούν από το μαύρο κοινό ταμείο
                    $partnerCardPersonal += $professionalAmt;
                }

                continue;
            }

            // ===== ΆΛΛΗ/ΑΓΝΩΣΤΗ ΜΕΘΟΔΟΣ -> σαν μετρητά με απόδειξη, όλο στην εταιρεία =====
            $cashWithTax += $amount;
            $cashToBank  += $amount;
        }

        // ===== ΤΕΛΙΚΟΣ ΚΟΙΝΟΣ ΚΟΥΜΠΑΡΑΣ =====
        // Από το RAW κοινό ταμείο αφαιρούμε τα 10άρια των συνεταίρων από ΚΑΡΤΕΣ
        $sharedPool = max($sharedPoolRaw - $partnerCardPersonal, 0);

        // ===== ΤΕΛΙΚΑ ΠΟΣΑ ΣΥΝΕΤΑΙΡΩΝ =====
        $partner1Total = $partner1Personal + ($sharedPool / 2);
        $partner2Total = $partner2Personal + ($sharedPool / 2);

        // Ποσό επιχείρησης στην Τράπεζα (ό,τι περνάει από τράπεζα)
        $companyBankTotal = $cashToBank + $cardTotal;

        // για πληροφόρηση (αν το χρειαστείς)
        $bankFromCard = $cardTotal;

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

        // 🔹 Ημερήσιο chart (μόνο τα προσωπικά 10€)
        ksort($daily);

        $dailyChart = [
            'labels'  => array_keys($daily),
            'giannis' => array_map(fn($d) => round($d['giannis'], 2), $daily),
            'eleni'   => array_map(fn($d) => round($d['eleni'], 2), $daily),
        ];

        // ================= ΕΞΟΔΑ & ΜΙΣΘΟΙ =================

        $expensesQuery = Expense::query();

        if ($from) {
            $expensesQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $expensesQuery->whereDate('created_at', '<=', $to);
        }

        $expensesList  = $expensesQuery->orderBy('created_at', 'desc')->get();
        $expensesTotal = (float) $expensesList->sum('amount');

        // Πόσες μέρες καλύπτει το διάστημα (inclusive)
        $startDate = Carbon::parse($from);
        $endDate   = Carbon::parse($to);

        $daysDiff = $startDate->diffInDays($endDate) + 1;

        // Από 0–31 ημέρες => 1 μισθός, 32–62 => 2, κ.ο.κ.
        $monthsCount = (int) ceil($daysDiff / 31);
        if ($monthsCount < 1) {
            $monthsCount = 1;
        }

        // Υπάλληλοι με μισθό
        $employees = Professional::whereNotNull('salary')
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

        // Net εταιρείας στην τράπεζα μετά τα έξοδα
        $companyNetAfterExpenses = $companyBankTotal - $totalOutflow;

        $filters = [
            'from' => $from,
            'to'   => $to,
        ];

        return view('settlements.index', compact(
            'filters',
            'totalAmount',
            'cashToBank',          // Μετρητά προς κατάθεση (τώρα 25€ στο σενάριο cash N + cash Y)
            'cardTotal',           // Πληρωμές με κάρτα (bruto)
            'companyBankTotal',    // Ποσό επιχείρησης στην τράπεζα
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
