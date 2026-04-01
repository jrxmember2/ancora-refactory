<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProposalDashboardService
{
    public static function summary(int $year): array
    {
        $monthlyRaw = DB::table('propostas')
            ->selectRaw('MONTH(proposal_date) as month_num, COUNT(*) as total')
            ->whereYear('proposal_date', $year)
            ->groupByRaw('MONTH(proposal_date)')
            ->orderByRaw('MONTH(proposal_date)')
            ->get();

        $monthlyMap = array_fill(1, 12, 0);
        foreach ($monthlyRaw as $row) {
            $monthlyMap[(int) $row->month_num] = (int) $row->total;
        }

        $services = DB::table('propostas as p')
            ->join('servicos as s', 's.id', '=', 'p.service_id')
            ->selectRaw('s.name, COUNT(*) as total')
            ->whereYear('p.proposal_date', $year)
            ->groupBy('s.id', 's.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $admins = DB::table('propostas as p')
            ->join('administradoras as a', 'a.id', '=', 'p.administradora_id')
            ->selectRaw('a.name, COUNT(*) as total')
            ->whereYear('p.proposal_date', $year)
            ->groupBy('a.id', 'a.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $totals = DB::table('propostas as p')
            ->join('status_retorno as st', 'st.id', '=', 'p.response_status_id')
            ->selectRaw('COALESCE(SUM(p.proposal_total),0) as total_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN st.system_key = "approved" THEN COALESCE(p.closed_total,0) ELSE 0 END),0) as total_closed_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN st.system_key = "declined" THEN COALESCE(p.proposal_total,0) ELSE 0 END),0) as total_declined_amount')
            ->whereYear('p.proposal_date', $year)
            ->first();

        $alerts = DB::table('propostas as p')
            ->join('status_retorno as st', 'st.id', '=', 'p.response_status_id')
            ->select('p.id', 'p.proposal_code', 'p.client_name', 'p.followup_date', 'st.name as status_name')
            ->whereNotNull('p.followup_date')
            ->where('st.stop_followup_alert', 0)
            ->whereDate('p.followup_date', '<=', Carbon::now()->toDateString())
            ->orderBy('p.followup_date')
            ->limit(8)
            ->get();

        return [
            'year' => $year,
            'years' => DB::table('propostas')->selectRaw('DISTINCT proposal_year')->orderByDesc('proposal_year')->pluck('proposal_year')->all(),
            'monthly_labels' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
            'monthly_values' => array_values($monthlyMap),
            'services' => $services,
            'admins' => $admins,
            'totals' => [
                'proposal_total' => (float) ($totals->total_amount ?? 0),
                'closed_total' => (float) ($totals->total_closed_amount ?? 0),
                'declined_total' => (float) ($totals->total_declined_amount ?? 0),
            ],
            'alerts' => $alerts,
        ];
    }
}
