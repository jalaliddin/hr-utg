<!DOCTYPE html>
<html lang="uz">

<head>
  <meta charset="UTF-8">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: DejaVu Sans, Arial, sans-serif;
      font-size: 10pt;
      color: #1a1a2e;
      background: #fff;
    }

    .page {
      padding: 18mm 18mm 15mm 22mm;
    }

    /* ── Header ── */
    .header-table {
      width: 100%;
      border-collapse: collapse;
      border-bottom: 2px solid #1565c0;
      padding-bottom: 4mm;
      margin-bottom: 6mm;
    }

    .header-logo-cell {
      width: 35mm;
      vertical-align: middle;
      padding-right: 4mm;
    }

    .header-title-cell {
      text-align: center;
      vertical-align: middle;
      padding: 0 4mm;
    }

    .header-title {
      font-size: 17pt;
      font-weight: bold;
      color: #1565c0;
      letter-spacing: 0.3px;
    }

    .header-cert-cell {
      width: 58mm;
      text-align: right;
      vertical-align: top;
    }

    .header-parent-org {
      font-size: 8pt;
      color: #888;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 1mm;
    }

    .header-org {
      font-size: 9pt;
      font-weight: bold;
      color: #1a1a2e;
      margin-bottom: 1mm;
    }

    .cert-badge {
      display: inline-block;
      background: #1565c0;
      color: #fff;
      font-size: 11pt;
      font-weight: bold;
      padding: 3mm 5mm;
      border-radius: 4px;
      letter-spacing: 1px;
    }

    .cert-date {
      font-size: 8.5pt;
      color: #666;
      margin-top: 2mm;
    }

    /* ── Employee card ── */
    .emp-card {
      background: #f0f4ff;
      border-left: 4px solid #1565c0;
      border-radius: 4px;
      padding: 3.5mm 5mm;
      margin-bottom: 5mm;
    }

    .emp-name {
      font-size: 12.5pt;
      font-weight: bold;
      color: #1a1a2e;
    }

    .emp-position {
      font-size: 9pt;
      color: #555;
      margin-top: 1mm;
    }

    /* ── Info grid ── */
    .info-grid {
      display: table;
      width: 100%;
      margin-bottom: 5mm;
      border-collapse: collapse;
    }

    .info-row {
      display: table-row;
    }

    .info-label {
      display: table-cell;
      width: 38%;
      font-size: 9pt;
      color: #666;
      padding: 1.5mm 2mm;
      vertical-align: top;
    }

    .info-value {
      display: table-cell;
      font-size: 9.5pt;
      font-weight: 500;
      color: #1a1a2e;
      padding: 1.5mm 2mm;
      border-bottom: 1px solid #e8eaf6;
      vertical-align: top;
    }

    .info-value.bold {
      font-weight: bold;
    }

    /* ── Section title ── */
    .section-title {
      font-size: 8.5pt;
      font-weight: bold;
      color: #1565c0;
      text-transform: uppercase;
      letter-spacing: 0.7px;
      border-bottom: 1px solid #bbdefb;
      padding-bottom: 1.5mm;
      margin-bottom: 3mm;
    }

    /* ── Signature block ── */
    .sign-section {
      margin-bottom: 8mm;
    }

    .sign-grid {
      display: table;
      width: 100%;
      border-collapse: collapse;
    }

    .sign-cell {
      display: table-cell;
      width: 50%;
      padding: 0 5mm 0 0;
      vertical-align: top;
    }

    .sign-cell:last-child {
      padding-right: 0;
    }

    .sign-role {
      font-size: 8.5pt;
      color: #555;
    }

    .sign-name {
      font-size: 10pt;
      font-weight: bold;
      margin-top: 1mm;
    }

    .sign-position {
      font-size: 8.5pt;
      color: #777;
      margin-top: 0.5mm;
    }

    .sign-line {
      border-bottom: 1px solid #aaa;
      margin-top: 10mm;
      margin-bottom: 1mm;
      width: 65%;
    }

    .sign-hint {
      font-size: 7.5pt;
      color: #aaa;
    }

    /* ── Destinations table ── */
    .dest-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 8.5pt;
      margin-top: 2mm;
    }

    .dest-table thead tr {
      background: #1565c0;
      color: #fff;
    }

    .dest-table th {
      padding: 2mm 3mm;
      text-align: center;
      font-weight: 600;
      font-size: 8pt;
      letter-spacing: 0.3px;
    }

    .dest-table td {
      padding: 2.5mm 3mm;
      border-bottom: 1px solid #e3e8ff;
      text-align: center;
      vertical-align: middle;
    }

    .dest-table tbody tr:nth-child(even) td {
      background: #f5f7ff;
    }

    .dest-table td.org-name {
      text-align: left;
      font-weight: 500;
    }

    .dest-sign-row td {
      padding: 5mm 3mm 2mm;
      border-bottom: none;
    }

    .mini-sign-line {
      display: inline-block;
      border-bottom: 1px solid #999;
      width: 35mm;
      margin: 0 auto;
    }

    /* ── Footer ── */
    .footer {
      border-top: 1px solid #e8eaf6;
      margin-top: 6mm;
      padding-top: 2mm;
      font-size: 7.5pt;
      color: #aaa;
      display: flex;
      justify-content: space-between;
    }
  </style>
</head>

<body>
  <div class="page">

    {{-- ── HEADER ── --}}
    <table class="header-table">
      <tr>
        {{-- Chap: Logo --}}
        <td class="header-logo-cell">
          <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('logo.svg'))) }}"
            style="height:20mm; width:auto;" />
        </td>
        {{-- O'rta: Sarlavha --}}
        <td class="header-title-cell">
          <div class="header-title">Xizmat safari guvohnomasi</div>
        </td>
        {{-- O'ng: UK nomi + tashkilot + raqam --}}
        <td class="header-cert-cell">
          <div class="header-parent-org">Urganchtransgaz UK</div>
          <div class="header-org">{{ $organization->name }}</div>
          <div style="margin-top:2mm;">
            <span class="cert-badge">№ {{ $trip->certificate_serial ?? '___' }}</span>
          </div>
          <div class="cert-date">
            Sana: {{ $trip->order_date ? $trip->order_date->format('d.m.Y') : now()->format('d.m.Y') }}
          </div>
        </td>
      </tr>
    </table>

    {{-- ── EMPLOYEE CARD ── --}}
    <div class="emp-card">
      <div class="emp-name">
        {{ $employee->last_name }} {{ $employee->first_name }} {{ $employee->middle_name }}
      </div>
      <div class="emp-position">
        @if($employee->department){{ $employee->department }}@if($employee->position) · {{ $employee->position }}@endif
        @else{{ $employee->position }}@endif
      </div>
    </div>

    {{-- ── TRIP INFO ── --}}
    <div class="info-grid">
      <div class="info-row">
        <div class="info-label">Safari muddati:</div>
        <div class="info-value bold">
          {{ $trip->start_date->format('d.m.Y') }} — {{ $trip->effective_end_date->format('d.m.Y') }}
          &nbsp;({{ $trip->days_count + ($trip->extension_days ?? 0) }} kun)
        </div>
      </div>
      @if($trip->transport)
        <div class="info-row">
          <div class="info-label">Transport:</div>
          <div class="info-value">
            {{ ['car' => 'Avtomobil', 'train' => 'Poyezd', 'plane' => 'Samolyot', 'bus' => 'Avtobus', 'other' => 'Boshqa'][$trip->transport] ?? $trip->transport }}
          </div>
        </div>
      @endif
      @if($trip->order_number)
        <div class="info-row">
          <div class="info-label">Buyruq:</div>
          <div class="info-value">
            Raqam: {{ $trip->order_number }} Sana:
            @if($trip->order_date) {{ $trip->order_date->format('d.m.Y') }} @endif
          </div>
        </div>
      @endif
      @if($trip->passport_series)
        <div class="info-row">
          <div class="info-label">Pasport:</div>
          <div class="info-value">{{ $trip->passport_series }}</div>
        </div>
      @endif
      @if($trip->service_id_number)
        <div class="info-row">
          <div class="info-label">Xizmat guvohnomasi:</div>
          <div class="info-value">{{ $trip->service_id_number }}</div>
        </div>
      @endif
      @if($trip->description)
        <div class="info-row">
          <div class="info-label">Izoh:</div>
          <div class="info-value">{{ $trip->description }}</div>
        </div>
      @endif
      @if($trip->extension_days)
        <div class="info-row">
          <div class="info-label">Uzaytirish:</div>
          <div class="info-value">
            +{{ $trip->extension_days }} kun
            @if($trip->extension_order_number) · Buyruq № {{ $trip->extension_order_number }} @endif
            @if($trip->extension_reason) <br><span
            style="color:#777;font-size:8.5pt;">{{ $trip->extension_reason }}</span> @endif
          </div>
        </div>
      @endif
    </div>

    {{-- ── SIGNATURE BLOCK ── --}}
    <div class="sign-section">
      <div class="section-title">Tasdiqlash</div>
      <div class="sign-grid">
        <div class="sign-cell">
          <div class="sign-role">Yuboruvchi rahbar:</div>
          @if($director)
            <div class="sign-name">{{ $director->full_name }}</div>
            <div class="sign-position">{{ $director->position }}</div>
          @endif
          <div class="sign-line"></div>
          <div class="sign-hint">imzo / sana</div>
        </div>
        <div class="sign-cell">
          <div class="sign-role">M.O. (Muhr o'rni):</div>
          <div class="sign-line"
            style="margin-top: 12mm; width: 40mm; height: 14mm; border: 1px dashed #ccc; border-radius: 50%; display: inline-block;">
          </div>
        </div>
      </div>
    </div>

    {{-- ── DESTINATIONS TABLE ── --}}
    @if($trip->destinations && $trip->destinations->count() > 0)
      <div>
        <div class="section-title">Borgan joylari qaydnomasi</div>
        <table class="dest-table">
          <thead>
            <tr>
              <th style="width:5%">#</th>
              <th style="width:28%; text-align:left; padding-left:4mm">Tashkilot</th>
              <th style="width:14%">Kelgan sana</th>
              <th style="width:14%">Ketgan sana</th>
              <th style="width:19%">Qabul qildi (imzo)</th>
              <th style="width:19%">Kuzatdi (imzo)</th>
            </tr>
          </thead>
          <tbody>
            @foreach($trip->destinations as $i => $dest)
              <tr>
                <td>{{ $i + 1 }}</td>
                <td class="org-name">{{ $dest->organization?->name ?? '—' }}</td>
                <td>{{ $dest->arrival_date ? $dest->arrival_date->format('d.m.Y H:i') : '' }}</td>
                <td>{{ $dest->departure_date ? $dest->departure_date->format('d.m.Y H:i') : '' }}</td>
                <td>
                  @if($dest->arrival_signed_by)
                    {{ $dest->arrival_signed_by }}
                  @else
                    <div class="mini-sign-line">&nbsp;</div>
                  @endif
                </td>
                <td>
                  @if($dest->departure_signed_by)
                    {{ $dest->departure_signed_by }}
                  @else
                    <div class="mini-sign-line">&nbsp;</div>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    {{-- ── FOOTER ── --}}
    <div class="footer">
      <span>{{ $organization->name }}</span>
      <span>Sana: {{ now()->format('d.m.Y') }}</span>
    </div>

  </div>
</body>

</html>