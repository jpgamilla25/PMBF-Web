<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0.6in 0.75in; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #000; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 18pt; }
        .header .org { font-weight: bold; font-size: 13pt; margin: 0; }
        .header .title { font-weight: bold; font-size: 12pt; margin: 4pt 0 0; }
        .header .period { font-size: 11pt; margin: 3pt 0 0; }

        table.notice { width: 100%; border-collapse: collapse; }
        table.notice th, table.notice td {
            border: 1px solid #000;
            padding: 5pt 6pt;
            font-size: 10pt;
            vertical-align: top;
        }
        table.notice thead th { text-align: center; font-weight: bold; background: #fff; }
        table.notice .col-no      { width: 6%;  text-align: center; }
        table.notice .col-name    { width: 40%; }
        table.notice .col-amount  { width: 20%; text-align: right; }
        table.notice .col-remarks { width: 34%; }
        table.notice td.col-amount { text-align: right; font-variant-numeric: tabular-nums; }
        table.notice td.col-no    { text-align: center; }

        /* Blank filler rows keep the ruled-paper look of the paper form. */
        table.notice tr.blank td { height: 22pt; }

        .footer { margin-top: 34pt; font-size: 10pt; }
        .footer .sig-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 220pt;
            padding: 0 6pt;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }
        .footer .row { margin-bottom: 6pt; }
        .footer .prepared-by { margin-bottom: 22pt; }
    </style>
</head>
<body>
    <div class="header">
        <p class="org">PHILRICE MUTUAL BENEFIT FUND, INC.</p>
        <p class="title">NOTICE OF DEDUCTION{{ $division ? ' – ' . strtoupper($division) : '' }}</p>
        <p class="period">{{ $cutoff['label'] }} payroll</p>
    </div>

    @php
        $minRows = 10;
        $blankRows = max(0, $minRows - count($rows));
    @endphp

    <table class="notice">
        <thead>
            <tr>
                <th class="col-no">&nbsp;</th>
                <th class="col-name">NAME</th>
                <th class="col-amount">LOAN<br>AMORTIZATION</th>
                <th class="col-remarks">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                <td class="col-no">{{ $row['row_no'] }}</td>
                <td class="col-name">{{ $row['name'] }}</td>
                <td class="col-amount">{{ number_format($row['semi_monthly'], 2) }}</td>
                <td class="col-remarks">{{ $row['remarks'] }}</td>
            </tr>
            @endforeach

            @for($i = 0; $i < $blankRows; $i++)
            <tr class="blank">
                <td class="col-no">&nbsp;</td>
                <td class="col-name">&nbsp;</td>
                <td class="col-amount">&nbsp;</td>
                <td class="col-remarks">&nbsp;</td>
            </tr>
            @endfor
        </tbody>
    </table>

    <div class="footer">
        <div class="prepared-by">
            <div class="row">Prepared by:</div>
            <span class="sig-line">{{ $prepared_by }}</span>
        </div>
        <div class="row">Date: {{ $generated_at }}</div>
    </div>
</body>
</html>
