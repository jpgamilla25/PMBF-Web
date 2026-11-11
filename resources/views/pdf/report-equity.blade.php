<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5px; color: #111; margin: 0; }
        .title { margin-bottom: 8px; }
        .title div { font-weight: bold; font-size: 10px; line-height: 1.35; }
        .title .sub { font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 2px 3px; }
        th { background: #e2efda; font-weight: bold; text-align: center; vertical-align: middle; }
        td.num { text-align: right; white-space: nowrap; }
        td.c { text-align: center; }
        tr.total td { font-weight: bold; border-top: 2px solid #111; background: #f5f5f5; }
        tr.member-start td { border-top: 1.5px solid #111; }
        td.blank { border-top: none; border-bottom: none; }
        .page-break { page-break-before: always; }
        .footer { margin-top: 6px; font-size: 7px; color: #666; }
    </style>
</head>
<body>
@php
    $year = $report['year'];
    $loanCols = $report['loan_columns'];
    $money = fn ($v) => (float) $v == 0.0 ? '-' : number_format((float) $v, 2);
    // Negative net equity reads as an obligation: show it in parentheses.
    $signed = fn ($v) => (float) $v < 0 ? '(' . number_format(abs((float) $v), 2) . ')' : $money($v);
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d-M-y') : '';
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
@endphp

{{-- ── 1. EQUITY & LOANS ── --}}
<div class="title">
    <div>PHILRICE MUTUAL BENEFIT FUND</div>
    <div>SUMMARY SCHEDULE OF MEMBERS EQUITY AND LOANS</div>
    <div>YEAR ENDING DECEMBER 31, {{ $year }}</div>
</div>
<table>
    <thead>
        <tr>
            <th rowspan="2" style="width:18px;"></th>
            <th rowspan="2">NAME</th>
            <th rowspan="2">PERSONAL CONTRIBUTION</th>
            <th>PHILRICE</th>
            <th rowspan="2">TOTAL EQUITY</th>
            <th colspan="{{ count($loanCols) + 1 }}">LOANS</th>
            <th rowspan="2">NET EQUITY (OBLIGATION)</th>
        </tr>
        <tr>
            <th>COUNTERPART</th>
            @foreach($loanCols as $label)<th>{{ $label }}</th>@endforeach
            <th>TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['equity']['rows'] as $r)
        <tr>
            <td class="c">{{ $r['no'] }}</td>
            <td>{{ $r['name'] }}</td>
            <td class="num">{{ $money($r['personal']) }}</td>
            <td class="num">{{ $money($r['counterpart']) }}</td>
            <td class="num">{{ $money($r['total_equity']) }}</td>
            @foreach($loanCols as $key => $label)<td class="num">{{ $money($r['loans'][$key]) }}</td>@endforeach
            <td class="num">{{ $money($r['total_loans']) }}</td>
            <td class="num">{{ $signed($r['net_equity']) }}</td>
        </tr>
        @empty
        <tr><td colspan="{{ 6 + count($loanCols) + 1 }}" class="c">No Permanent members found.</td></tr>
        @endforelse
        @php $t = $report['equity']['totals']; @endphp
        <tr class="total">
            <td></td>
            <td>TOTAL</td>
            <td class="num">{{ $money($t['personal']) }}</td>
            <td class="num">{{ $money($t['counterpart']) }}</td>
            <td class="num">{{ $money($t['total_equity']) }}</td>
            @foreach($loanCols as $key => $label)<td class="num">{{ $money($t['loans'][$key]) }}</td>@endforeach
            <td class="num">{{ $money($t['total_loans']) }}</td>
            <td class="num">{{ $signed($t['net_equity']) }}</td>
        </tr>
    </tbody>
</table>
<div class="footer">Generated {{ now()->format('F d, Y h:i A') }}</div>

{{-- ── 2. MEDICAL FOR DEPENDENTS ── --}}
<div class="page-break"></div>
<div class="title">
    <div>Philrice Mutual Benefit Fund, Inc. (PMBF)</div>
    <div>Health and Medical Assistance Benefit for Regulars' Dependents</div>
    <div>{{ $year }}</div>
</div>
<table>
    <thead>
        <tr>
            <th rowspan="2">PMBF MEMBER</th>
            <th rowspan="2">Name of dependent</th>
            <th rowspan="2">Relationship</th>
            <th colspan="16">PREMIUM PAYMENT</th>
        </tr>
        <tr>
            <th>Premium (P{{ number_format($report['medical']['default_premium']) }}/ DEPENDENT)</th>
            <th>Monthly deduction</th>
            @foreach($months as $m)<th>{{ $m }}</th>@endforeach
            <th>Total</th>
            <th>Balance</th>
        </tr>
    </thead>
    <tbody>
        @php $prev = null; @endphp
        @forelse($report['medical']['rows'] as $r)
        @php $first = $r['user_id'] !== $prev; $prev = $r['user_id']; @endphp
        <tr class="{{ $first ? 'member-start' : '' }}">
            <td>{{ $first ? $r['member'] : '' }}</td>
            <td>{{ $r['dependent'] }}</td>
            <td>{{ ucfirst((string) $r['relationship']) }}</td>
            <td class="num">{{ $money($r['premium']) }}</td>
            <td class="num">{{ $money($r['monthly_deduction']) }}</td>
            @for($m = 1; $m <= 12; $m++)<td class="num">{{ $money($r['months'][$m]) }}</td>@endfor
            <td class="num">{{ $money($r['total']) }}</td>
            <td class="num">{{ $signed($r['balance']) }}</td>
        </tr>
        @empty
        <tr><td colspan="19" class="c">No covered dependents found.</td></tr>
        @endforelse
        @php $t = $report['medical']['totals']; @endphp
        <tr class="total">
            <td colspan="3">TOTAL</td>
            <td class="num">{{ $money($t['premium']) }}</td>
            <td class="num">{{ $money($t['monthly_deduction']) }}</td>
            @for($m = 1; $m <= 12; $m++)<td class="num">{{ $money($t['months'][$m]) }}</td>@endfor
            <td class="num">{{ $money($t['total']) }}</td>
            <td class="num">{{ $signed($t['balance']) }}</td>
        </tr>
    </tbody>
</table>

{{-- ── 3. DEPENDENTS & BENEFICIARIES ── --}}
<div class="page-break"></div>
<div class="title">
    <div>PHILRICE MUTUAL BENEFIT FUND, INC. (PMBF)</div>
    <div>LIST OF REGULAR MEMBERS DECLARED DEPENDENTS &amp; BENEFICIARIES</div>
    <div>{{ $station }}</div>
</div>
<table>
    <thead>
        <tr>
            <th style="width:18px;">NO.</th>
            <th>NAME</th>
            <th>BIRTHDAY</th>
            <th style="width:22%;">ADDRESS</th>
            <th>DATE STARTED</th>
            <th>DEPENDENTS' NAME</th>
            <th>RELATIONSHIP</th>
            <th>BENEFICIARIES</th>
            <th>RELATIONSHIP</th>
            <th>UPDATED</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['dependents'] as $m)
        @php $span = max(1, count($m['dependents']), count($m['beneficiaries'])); @endphp
        @for($i = 0; $i < $span; $i++)
        <tr class="{{ $i === 0 ? 'member-start' : '' }}">
            @if($i === 0)
            <td class="c" rowspan="{{ $span }}" style="vertical-align:top;">{{ $m['no'] }}</td>
            <td rowspan="{{ $span }}" style="vertical-align:top; font-weight:bold;">{{ $m['name'] }}</td>
            <td rowspan="{{ $span }}" style="vertical-align:top;">{{ $date($m['birth_date']) }}</td>
            <td rowspan="{{ $span }}" style="vertical-align:top;">{{ $m['address'] }}</td>
            <td rowspan="{{ $span }}" style="vertical-align:top;">{{ $date($m['date_started']) }}</td>
            @endif
            <td>{{ $m['dependents'][$i]['name'] ?? '' }}</td>
            <td>{{ ucfirst((string) ($m['dependents'][$i]['relationship'] ?? '')) }}</td>
            <td>{{ $m['beneficiaries'][$i]['name'] ?? '' }}</td>
            <td>{{ ucfirst((string) ($m['beneficiaries'][$i]['relationship'] ?? '')) }}</td>
            @if($i === 0)
            <td rowspan="{{ $span }}" style="vertical-align:top;">{{ $date($m['updated']) }}</td>
            @endif
        </tr>
        @endfor
        @empty
        <tr><td colspan="10" class="c">No Permanent members found.</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
