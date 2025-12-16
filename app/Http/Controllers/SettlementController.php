<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Expense;
use App\Models\Professional;
use App\Models\Settlement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SettlementController extends Controller
{
    // Συνεταίροι (ids professionals)
    private array $partnerProfessionals = [
        'partner1' => 1, // Γιάννης
        'partner2' => 2, // Ελένη
    ];

    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'owner') {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη σελίδα.');
        }

        // ===== ΦΙΛΤΡΟ ΜΗΝΩΝ (YYYY-MM) =====
        $fromMonth = $request->input('from_month');
        $toMonth   = $request->input('to_month');

        if (!$fromMonth && !$toMonth) {
            $fromMonth = Carbon::now()->format('Y-m');
            $toMonth   = $fromMonth;
        }
        if ($fromMonth && !$toMonth) $toMonth = $fromMonth;
        if (!$fromMonth && $toMonth) $fromMonth = $toMonth;

        $rangeStart = Carbon::createFromFormat('Y-m', $fromMonth)->startOfMonth()->startOfDay();
        $rangeEndExclusive = Carbon::createFromFormat('Y-m', $toMonth)->startOfMonth()->addMonth()->startOfDay();

        // ✅ ΠΑΝΤΑ live υπολογισμός (να πιάνει και αλλαγές μετά την εκκαθάριση)
        $calc = $this->calculateForRange($rangeStart, $rangeEndExclusive);

        // Live totals
        $totalAmount      = $calc['totalAmount'];
        $cashToBank       = $calc['cashToBank'];
        $partner1Total    = $calc['partner1Total'];
        $partner2Total    = $calc['partner2Total'];

        // Live υπόλοιπα
        $companyBankTotal = $calc['companyBankTotal'];
        $cardTotal        = $calc['cardTotal'];
        $cashNoTax        = $calc['cashNoTax'];
        $cashWithTax      = $calc['cashWithTax'];
        $sharedPool       = $calc['sharedPool'];
        $partner1Personal = $calc['partner1Personal'];
        $partner2Personal = $calc['partner2Personal'];
        $chartDistribution= $calc['chartDistribution'];
        $dailyChart       = $calc['dailyChart'];
        $payments         = $calc['payments'];

        // ===== Αποθηκευμένες εκκαθαρίσεις (μόνο για εμφάνιση/σύγκριση UI) =====
        $companyId = $user->company_id ?? 1;

        $settlements = Settlement::where('company_id', $companyId)
            ->where('month', '>=', $rangeStart->toDateString())
            ->where('month', '<',  $rangeEndExclusive->toDateString())
            ->orderBy('month')
            ->get();

        // totals από saved settlements (για compare μόνο)
        $savedTotals = [
            'cashToBank'    => round((float) $settlements->sum('cash_to_bank'), 2),
            'partner1Total' => round((float) $settlements->sum('partner1_total'), 2),
            'partner2Total' => round((float) $settlements->sum('partner2_total'), 2),
        ];

        $liveTotals = [
            'cashToBank'    => round((float) $cashToBank, 2),
            'partner1Total' => round((float) $partner1Total, 2),
            'partner2Total' => round((float) $partner2Total, 2),
        ];

        // tolerance για floating
        $eps = 0.01;

        // ✅ Αν ΔΕΝ υπάρχουν settlements για το range => not settled
        $isSettled = $settlements->count() > 0
            && abs($liveTotals['cashToBank'] - $savedTotals['cashToBank']) < $eps
            && abs($liveTotals['partner1Total'] - $savedTotals['partner1Total']) < $eps
            && abs($liveTotals['partner2Total'] - $savedTotals['partner2Total']) < $eps;

        // ===== ΕΞΟΔΑ (live) =====
        $expensesList = Expense::where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEndExclusive)
            ->orderBy('created_at', 'desc')
            ->get();

        $expensesTotal = (float) $expensesList->sum('amount');

        // ===== ΜΗΝΕΣ στο διάστημα (inclusive) =====
        $startIndex = $rangeStart->year * 12 + $rangeStart->month;
        $endDateInclusive = $rangeEndExclusive->copy()->subDay();
        $endIndex = $endDateInclusive->year * 12 + $endDateInclusive->month;
        $monthsCount = max(($endIndex - $startIndex + 1), 1);

        // ===== ΜΙΣΘΟΙ =====
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

        $totalOutflow = $expensesTotal + $employeesTotalSalary;
        $companyNetAfterExpenses = $companyBankTotal - $totalOutflow;

        $filters = [
            'from_month' => $fromMonth,
            'to_month'   => $toMonth,
        ];

        return view('settlements.index', compact(
            'filters',
            'totalAmount',
            'cashToBank',
            'cardTotal',
            'companyBankTotal',
            'cashNoTax',
            'cashWithTax',
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
            'companyNetAfterExpenses',
            'settlements',
            'savedTotals',
            'liveTotals',
            'isSettled'
        ));
    }

    /**
     * POST: Πατάς "Εκκαθάριση" -> αποθηκεύει (ή ενημερώνει) settlement για ΚΑΘΕ μήνα του range.
     */
    public function store(Request $request)
    {
        $professional = Auth::user();

        if (!$professional) {
            abort(401, 'Δεν είστε συνδεδεμένος.');
        }

        if (!in_array($professional->role, ['owner'], true)) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτή τη λειτουργία.');
        }

        $fromMonth = $request->input('from_month');
        $toMonth   = $request->input('to_month');

        if (!$fromMonth && !$toMonth) {
            $fromMonth = Carbon::now()->format('Y-m');
            $toMonth   = $fromMonth;
        }
        if ($fromMonth && !$toMonth) $toMonth = $fromMonth;
        if (!$fromMonth && $toMonth) $fromMonth = $toMonth;

        try {
            $start = Carbon::createFromFormat('Y-m', $fromMonth)->startOfMonth()->startOfDay();
            $endExclusive = Carbon::createFromFormat('Y-m', $toMonth)->startOfMonth()->addMonth()->startOfDay();
        } catch (\Throwable $e) {
            return back()->withErrors(['from_month' => 'Μη έγκυρος μήνας επιλογής.']);
        }

        $companyId = $professional->company_id;

        $cursor = $start->copy();

        while ($cursor->lt($endExclusive)) {
            $monthStart = $cursor->copy()->startOfMonth()->startOfDay();
            $monthEndExclusive = $monthStart->copy()->addMonth();

            $monthCalc = $this->calculateForRange($monthStart, $monthEndExclusive);

            Settlement::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'month'      => $monthStart->toDateString(),
                ],
                [
                    'total_amount'   => $monthCalc['totalAmount'],
                    'cash_to_bank'   => $monthCalc['cashToBank'],
                    'partner1_total' => $monthCalc['partner1Total'],
                    'partner2_total' => $monthCalc['partner2Total'],
                    'created_by'     => $professional->id, // professional id
                ]
            );

            $cursor = $monthEndExclusive;
        }

        return redirect()
            ->route('settlements.index', [
                'from_month' => $fromMonth,
                'to_month'   => $toMonth,
            ])
            ->with('success', 'Η εκκαθάριση αποθηκεύτηκε επιτυχώς.');
    }

    /**
     * Υπολογισμός για όλο το range: [start, endExclusive)
     */
    private function calculateForRange(Carbon $rangeStart, Carbon $rangeEndExclusive): array
    {
        $payments = Payment::with(['appointment.professional', 'customer'])
            ->whereHas('appointment', function ($q) use ($rangeStart, $rangeEndExclusive) {
                $q->where('start_time', '>=', $rangeStart)
                  ->where('start_time', '<',  $rangeEndExclusive)
                  ->whereNull('deleted_at');
            })
            ->get();

        $totalAmount = 0;

        $cashToBank   = 0;
        $cashNoTax    = 0;
        $cashWithTax  = 0;
        $cardTotal    = 0;

        $partner1Personal = 0;
        $partner2Personal = 0;

        $sharedPoolRaw = 0;
        $partnerCardPersonal = 0;

        $daily = [];

        foreach ($payments as $payment) {
            $appointment = $payment->appointment;
            if (!$appointment) continue;

            $dateKey = $appointment->start_time->toDateString();

            if (!isset($daily[$dateKey])) {
                $daily[$dateKey] = ['giannis' => 0, 'eleni' => 0];
            }

            $amount          = (float) $payment->amount;
            $method          = $payment->method;
            $tax             = $payment->tax ?? 'N';
            $professionalAmt = (float) ($appointment->professional_amount ?? 0);
            $professionalId  = $appointment->professional_id;

            $totalAmount += $amount;

            $isPartner = in_array($professionalId, $this->partnerProfessionals, true);

            if ($isPartner && $professionalAmt > 0) {
                if ($professionalId === $this->partnerProfessionals['partner1']) {
                    $partner1Personal += $professionalAmt;
                    $daily[$dateKey]['giannis'] += $professionalAmt;
                }
                if ($professionalId === $this->partnerProfessionals['partner2']) {
                    $partner2Personal += $professionalAmt;
                    $daily[$dateKey]['eleni'] += $professionalAmt;
                }
            }

            if ($method === 'cash') {
                if ($tax === 'Y') {
                    $cashWithTax += $amount;

                    if ($isPartner) {
                        $cashToBank += max($amount - $professionalAmt, 0);
                    } else {
                        $cashToBank += $amount;
                    }
                    continue;
                }

                $cashNoTax += $amount;

                if ($isPartner) {
                    $sharedPoolRaw += max($amount - $professionalAmt, 0);
                } else {
                    $sharedPoolRaw += $amount;
                }
                continue;
            }

            if ($method === 'card') {
                $cardTotal += $amount;

                if ($isPartner && $professionalAmt > 0) {
                    $partnerCardPersonal += $professionalAmt;
                }
                continue;
            }

            $cashWithTax += $amount;
            $cashToBank  += $amount;
        }

        $sharedPool = max($sharedPoolRaw - $partnerCardPersonal, 0);

        $partner1Total = $partner1Personal + ($sharedPool / 2);
        $partner2Total = $partner2Personal + ($sharedPool / 2);

        $companyBankTotal = $cashToBank + $cardTotal;

        ksort($daily);

        $chartDistribution = [
            'labels' => ['Μετρητά προς κατάθεση', 'Γιάννης #1', 'Ελένη #2'],
            'data'   => [round($cashToBank, 2), round($partner1Total, 2), round($partner2Total, 2)],
        ];

        $dailyChart = [
            'labels'  => array_keys($daily),
            'giannis' => array_map(fn($d) => round($d['giannis'], 2), $daily),
            'eleni'   => array_map(fn($d) => round($d['eleni'], 2), $daily),
        ];

        return [
            'payments'         => $payments,
            'totalAmount'      => round($totalAmount, 2),
            'cashToBank'       => round($cashToBank, 2),
            'cardTotal'        => round($cardTotal, 2),
            'companyBankTotal' => round($companyBankTotal, 2),
            'cashNoTax'        => round($cashNoTax, 2),
            'cashWithTax'      => round($cashWithTax, 2),
            'sharedPool'       => round($sharedPool, 2),
            'partner1Personal' => round($partner1Personal, 2),
            'partner2Personal' => round($partner2Personal, 2),
            'partner1Total'    => round($partner1Total, 2),
            'partner2Total'    => round($partner2Total, 2),
            'chartDistribution'=> $chartDistribution,
            'dailyChart'       => $dailyChart,
        ];
    }
}
