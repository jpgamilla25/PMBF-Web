<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Slightly tighter than Word's 0.5" so signature + notary + intro all
           fit on one A4 page. */
        @page { margin: 0.45in 0.5in; }
        /* DejaVu Sans is DomPDF's built-in Unicode font — it has the ₱ glyph
           (Helvetica/Arial's built-in Type1 does not) and is visually close to
           Arial. Heading uses DejaVu Serif (Cambria-substitute). */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; line-height: 1.35; }
        .header { text-align: center; margin-bottom: 12pt; }
        .header h1 { font-family: 'DejaVu Serif', 'Cambria', 'Times New Roman', serif; color: #000; font-size: 14pt; margin: 0; font-weight: bold; }
        .header p { color: #000; margin: 2pt 0 0; font-size: 10pt; font-style: italic; }
        .ref-line { text-align: right; color: #444; font-size: 9pt; margin-bottom: 6pt; }

        p, li { font-size: 10pt; }
        .intro { text-align: justify; margin: 0 0 8pt; text-indent: 20pt; }
        ol.undertake { padding-left: 24pt; margin: 0 0 8pt; list-style-type: upper-roman; }
        ol.undertake li { margin-bottom: 5pt; text-align: justify; }

        .witness { text-align: justify; margin: 10pt 0 12pt; text-indent: 20pt; }

        /* Underlined fill-in blanks.
           .blank-fit → sizes to its content (used for pre-filled values so
           the sentence flows naturally). .blank-empty* → fixed width empty
           blank for pen-and-paper fields. */
        .blank { border-bottom: 1px solid #000; padding: 0 3pt; font-weight: bold; color: #000; }
        .blank-fit { white-space: nowrap; }
        .blank-empty { display: inline-block; min-width: 160pt; color: transparent; text-align: center; }
        .blank-sm { display: inline-block; min-width: 36pt; color: transparent; text-align: center; }

        /* Signature grid */
        .sig-grid { display: table; width: 100%; border-collapse: collapse; margin-top: 4pt; }
        .sig-cell { display: table-cell; width: 50%; padding: 10pt 12pt 4pt; vertical-align: top; }
        .sig-line { border-top: 1px solid #000; margin: 22pt 0 3pt; }
        .sig-name { font-weight: bold; text-align: center; text-transform: uppercase; font-size: 10pt; }
        .sig-role { text-align: center; font-size: 9pt; margin-bottom: 8pt; }
        .sig-detail { font-size: 10pt; margin: 2pt 0; }
        .sig-detail .val { border-bottom: 1px solid #000; padding: 0 3pt; font-weight: bold; display: inline-block; min-width: 60pt; text-align: center; }

        /* Notary block */
        .notary { margin-top: 14pt; }
        .notary p { margin: 3pt 0; text-align: justify; }
        .notary-sig { margin-top: 18pt; text-align: center; }
        .notary-sig .sig-line { border-top: 1px solid #000; width: 200pt; margin: 0 auto 3pt; }
        .notary-sig .sig-name { font-weight: bold; font-size: 10pt; }
        .notary-book { margin-top: 8pt; font-size: 10pt; }
        .notary-book div { margin-bottom: 2pt; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMBF Service Contractor Loan Agreement</h1>
        <p>PhilRice Mutual Benefit Fund</p>
    </div>

    <div class="ref-line">Ref. No. {{ $loan->reference_no }}</div>

    @php
        $applicantName = trim(($loan->user->first_name ?? '') . ' ' . ($loan->user->middle_name ?? '') . ' ' . ($loan->user->last_name ?? '') . ' ' . ($loan->user->suffix ?? ''));
        $applicantName = preg_replace('/\s+/', ' ', $applicantName);
        $coMakerName   = $loan->coMaker ? trim(($loan->coMaker->first_name ?? '') . ' ' . ($loan->coMaker->middle_name ?? '') . ' ' . ($loan->coMaker->last_name ?? '') . ' ' . ($loan->coMaker->suffix ?? '')) : '';
        $coMakerName   = $coMakerName ? preg_replace('/\s+/', ' ', $coMakerName) : '';
    @endphp

    <p class="intro">
        I, <span class="blank blank-fit">{{ strtoupper($applicantName) }}</span>
        of the <strong>Philippine Rice Research Institute</strong>,
        having been granted a loan amounting to <span class="blank blank-fit">₱{{ number_format($loan->amount, 2) }}</span>
        (<span class="blank blank-fit">{{ $amountInWords }}</span>)
        HEREBY VOLUNTARILY undertake:
    </p>

    <ol class="undertake">
        <li>
            That I authorize the deduction of loan amortization of <span class="blank blank-fit">₱{{ number_format($loan->monthly_amortization, 2) }}</span>
            from my salary every payroll for a period of <span class="blank blank-fit">{{ $loan->term_months }}</span> months;
        </li>
        <li>
            That I will abide by all policies of PhilRice and shall comply with those imposed
            by the project I am under to ensure that my engagement with PhilRice will not be
            terminated prematurely;
        </li>
        <li>
            That I have the assurance of my co-maker to shoulder any unsettled portion of the
            loan in case of sudden termination of my contract for reasons beyond my control;
        </li>
        <li>
            That, during the effectivity of this loan agreement, I will not loan my ATM card
            to any lender, which will drastically reduce my take home pay;
        </li>
        <li>
            That I recognize that any breach of this agreement will be ground for my
            blacklisting from future loans or may serve as basis for PhilRice to terminate my
            engagement as service contractor;
        </li>
        <li>
            That this agreement will cease once the loan is fully settled.
        </li>
    </ol>

    <p class="witness">
        IN WITNESS WHEREOF, I and my co-maker have voluntarily and freely signed this
        agreement, this <span class="blank blank-empty">&nbsp;</span>
        at <span class="blank blank-empty">&nbsp;</span>, Philippines.
    </p>

    <div class="sig-grid">
        <div class="sig-cell">
            <div class="sig-line"></div>
            <div class="sig-name">{{ strtoupper($applicantName) }}</div>
            <div class="sig-role">Signature Over Printed Name<br>of Service Contractor</div>
            <div class="sig-detail">ID No. <span class="val">{{ $loan->user->employee_id ?? '' }}</span></div>
            <div class="sig-detail">Issued at <span class="val">PhilRice</span></div>
            <div class="sig-detail">Issued on <span class="val">{{ $applicantIssuedOn }}</span></div>
        </div>
        <div class="sig-cell">
            <div class="sig-line"></div>
            <div class="sig-name">{!! $coMakerName ? strtoupper($coMakerName) : '&nbsp;' !!}</div>
            <div class="sig-role">Signature Over Printed Name<br>of Co-maker</div>
            <div class="sig-detail">ID No. <span class="val">{{ optional($loan->coMaker)->employee_id ?? '' }}</span></div>
            <div class="sig-detail">Issued at <span class="val">{{ $loan->coMaker ? 'PhilRice' : '' }}</span></div>
            <div class="sig-detail">Issued on <span class="val">{{ $coMakerIssuedOn }}</span></div>
        </div>
    </div>

    <div class="notary">
        <p>
            SUBSCRIBED AND SWORN to before me, this
            <span class="blank blank-empty">&nbsp;</span>
            at <span class="blank blank-empty">&nbsp;</span>.
        </p>

        <div class="notary-sig">
            <div class="sig-line"></div>
            <div class="sig-name">Notary Public</div>
        </div>

        <div class="notary-book">
            <div>Doc. No. <span class="blank blank-sm">&nbsp;</span>;</div>
            <div>Page No. <span class="blank blank-sm">&nbsp;</span>;</div>
            <div>Book No. <span class="blank blank-sm">&nbsp;</span>;</div>
            <div>Series of 20 <span class="blank blank-sm">&nbsp;</span>.</div>
        </div>
    </div>
</body>
</html>
