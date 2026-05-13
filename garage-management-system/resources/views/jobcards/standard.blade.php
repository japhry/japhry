<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GARAGE Tanzania - Premium Invoice</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">

  <!-- Styles will be primarily from external CSS files -->
  <link rel="stylesheet" href="{{ asset('css/jobcard_screen.css') }}" media="screen">
  <link rel="stylesheet" href="{{ asset('css/jobcard_print.css') }}" media="print">

  <style>
    /* Minimal, essential inline styles */
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        padding: 30px 20px; /* Retain overall body padding for screen context */
        font-family: 'Roboto', sans-serif; /* Base font */
        color: #333; /* Base text color */
        /* Background and centering are now in jobcard_screen.css */
    }
  </style>
</head>
<body>
  <div class="invoice-container">
    <div class="corner-decoration"></div>

    <div class="invoice-header">
      <div class="header-overlay"></div>

      <div class="company-logo">
        <svg viewBox="0 0 24 24" fill="#eb0a1e">
          <path d="M12,2C6.48,2,2,6.48,2,12s4.48,10,10,10s10-4.48,10-10S17.52,2,12,2z M12,20c-4.41,0-8-3.59-8-8s3.59-8,8-8s8,3.59,8,8 S16.41,20,12,20z"/>
          <path d="M12,7c-2.76,0-5,2.24-5,5s2.24,5,5,5s5-2.24,5-5S14.76,7,12,7z M12,15c-1.65,0-3-1.35-3-3s1.35-3,3-3s3,1.35,3,3 S13.65,15,12,15z"/>
        </svg>
      </div>

      <div class="company-info">
        <h1>{{ $job->branch['name'] ?? 'GARAGE TANZANIA LTD' }}</h1>
        <div>{{ $job->branch['address_line1'] ?? 'Nyerere Road, P.O. Box 9060' }}, {{ $job->branch['city'] ?? 'Dar es Salaam' }}</div>
        <div>Tel: {{ $job->branch['phone'] ?? '(255) 22 2866815-9' }} | Fax: (255) 22 2866814</div>
        <div>TIN: {{ $job->branch['tin_number'] ?? '100-146-304' }}&emsp;VRN: {{ $job->branch['vrn_number'] ?? '10-006645-E' }}</div>
      </div>
      <div class="document-type">Internal Invoice</div>
    </div>

    <div class="watermark">{{ $job->branch['name'] ?? 'GARAGE TANZANIA' }}</div>

    <div class="security-features">
      <div class="security-stamp">Certified</div>
      <div id="qrcode-top"></div>
    </div>

    <div class="invoice-body">
      <div class="invoice-details">
        <div class="detail-box">
          <h3>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#555">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
            Invoice From
          </h3>
          <div class="invoice-grid">
            <div>
              <label>Company</label>
              <span>{{ $job->branch['name'] ?? 'GARAGE TANZANIA LTD' }}</span>
            </div>
            <div>
              <label>Account No.</label>
              <span>{{ $job->branch['account_number'] ?? 'N/A' }}</span>
            </div>
            <div>
              <label>Address</label>
              <span>{{ $job->branch['address_line1'] ?? 'Nyerere Road, P.O. Box 9060' }}</span>
            </div>
            <div>
              <label>Salesman</label>
              <span>{{ $job->branch['salesman'] ?? 'N/A' }}</span>
            </div>
            <div>
              <label>City</label>
              <span>{{ $job->branch['city'] ?? 'Dar es Salaam' }}</span>
            </div>
            <div>
              <label>Phone</label>
              <span>{{ $job->branch['phone'] ?? '(255) 22 2866815-9' }}</span>
            </div>
          </div>
        </div>

        <div class="detail-box">
          <h3>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#555">
              <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
            </svg>
            Invoice To
          </h3>
          <div class="invoice-grid">
            <div>
              <label>Company</label>
              <span>{{ $job->customer['name'] ?? 'T/I Automark Repairs' }}</span>
            </div>
            <div>
              <label>Order Number</label>
              <span>{{ $job->id ?? '48131' }}</span>
            </div>
            <div>
              <label>Address</label>
              <span>{{ $job->customer['address'] ?? 'Kij Building, Sokoine Drive' }}</span>
            </div>
            <div>
              <label>Invoice Date</label>
              <span>{{ isset($job->date_opened) ? \Carbon\Carbon::parse($job->date_opened)->format('d/m/Y') : 'N/A' }}</span>
            </div>
            <div>
              <label>City</label>
              <span>{{ $job->customer['city'] ?? 'N/A' }}</span>
            </div>
            <div>
              <label>TIN</label>
              <span>{{ $job->customer['tin_number'] ?? 'N/A' }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="highlight-info">
        <strong>Payment Terms:</strong> {{ $job->payment_terms ?? 'Payment terms not specified.' }}
      </div>

      <table class="items-table">
        <thead>
          <tr>
            <th>Description of Goods / Services</th>
            <th class="qty">Qty</th>
            <th class="price">Unit Price</th>
            <th class="total">Net Total</th>
          </tr>
        </thead>
        <tbody>
          @php $items = array_merge($job->services ?? [], $job->parts ?? []); @endphp
          @forelse ($items as $item)
          <tr>
            <td>{{ $item['description'] ?? $item['name'] ?? 'N/A' }}</td>
            <td class="qty">{{ $item['quantity'] ?? 1 }}</td>
            <td class="price">{{ number_format($item['unit_price'] ?? 0, 2) }}</td>
            <td class="total">{{ number_format($item['total_price'] ?? 0, 2) }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="4">No items listed.</td>
          </tr>
          @endforelse
          {{-- Example from static HTML for reference if needed
          <tr>
            <td>00888084965 Engine Oil 15W40 SL / JO415238010 ELEMENT</td>
            <td class="qty">2</td>
            <td class="price">35,828.00</td>
            <td class="total">71,656.00</td>
          </tr>
          --}}
        </tbody>
      </table>

      <div class="total-section">
        <div class="total-box">
          <div class="total-row">
            <div class="total-label">Subtotal</div>
            <div class="total-value">{{ number_format($job->sub_total ?? 0, 2) }}</div>
          </div>
          <div class="total-row">
            <div class="total-label">Discount ({{ ($job->discount_rate ?? 0) * 100 }}%)</div>
            <div class="total-value">{{ number_format($job->discount_amount ?? 0, 2) }}</div>
          </div>
          <div class="total-row">
            <div class="total-label">V.A.T ({{ ($job->tax_rate ?? 0) * 100 }}%)</div>
            <div class="total-value">{{ number_format($job->tax_amount ?? 0, 2) }}</div>
          </div>
          <div class="total-row highlight">
            <div class="total-label">Total Amount</div>
            <div class="total-value">{{ number_format($job->total_due ?? 0, 2) }}</div>
          </div>
          <div class="total-row">
            <div class="total-label">Amount Paid</div>
            <div class="total-value">{{ number_format($job->amount_paid ?? 0, 2) }}</div>
          </div>
          <div class="total-row highlight">
            <div class="total-label">Balance Due</div>
            <div class="total-value">{{ number_format($job->balance_due ?? 0, 2) }}</div>
          </div>
        </div>
      </div>

      <div class="payment-info">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#e6a000">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
        </svg>
        <div>
          <strong>Payment Instructions:</strong> {{-- Dynamically set payment instructions or use default --}}
          Please make payment via bank transfer to {{ $job->branch['name'] ?? 'GARAGE Tanzania Ltd' }}. Account #: {{ $job->branch_account_details ?? 'N/A' }}.
          Include invoice number in payment reference. For assistance, contact {{ $job->branch['email'] ?? 'accounts@example.com' }}
        </div>
      </div>

      <div class="signature-area">
        <div class="signature-box">
          <div class="signature-line"></div>
          <div class="signature-label">Authorized Signature</div>
        </div>
        <div class="signature-box">
          <div class="signature-line"></div>
          <div class="signature-label">Customer Signature</div>
        </div>
      </div>

      <div class="barcode">*{{ $job->job_card_number ?? 'TZ-TT-48131-2025-31979776' }}*</div>

      <div class="microprint">
        {{ $job->branch['name'] ?? 'GARAGE TANZANIA LTD' }} | INTERNAL INVOICE | DOCUMENT ID: {{ $job->job_card_number ?? 'TT-2025-48131' }} | ISSUED: {{ isset($job->date_opened) ? \Carbon\Carbon::parse($job->date_opened)->format('d/m/Y') : '16/05/2025' }}<br>
        THIS DOCUMENT IS COMPUTER GENERATED AND REQUIRES NO SIGNATURE | UNAUTHORIZED DUPLICATION PROHIBITED<br>
        &copy; {{ date('Y') }} {{ $job->branch['name'] ?? 'GARAGE TANZANIA LTD' }}. ALL RIGHTS RESERVED. | CONFIDENTIAL DOCUMENT - FOR AUTHORIZED USE ONLY
      </div>

      <div class="footer-note">
        {{ $job->notes_to_customer ?? 'Thank you for your business! We value your partnership and look forward to serving you again.' }}
      </div>
    </div>

    <div class="invoice-footer">
      <div class="verification">
        <div class="verification-code">{{ $job->internal_verification_code ?? 'N/A' }}</div>
        <a href="{{ $job->tra_verification_url ?? '#' }}" class="verification-link" target="_blank">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
          </svg>
          {{ $job->tra_verification_url ?? 'https://verify.tra.go.tz/F0198C121256_155242' }}
        </a>
      </div>
      <div id="qrcode-bottom"></div>
    </div>
  </div>

  <script>
    // Ensure $job object and its properties are available to JavaScript if needed for QR codes
    // For example, by outputting them to JS variables or data attributes.
    const jobCardUrl = "{{ url('/jobcards', $job->id ?? 'dummy-id') }}"; // Example URL
    const traVerificationUrl = "{{ $job->tra_verification_url ?? 'https://verify.tra.go.tz/default' }}";

    // Generate QR codes
    if (document.getElementById("qrcode-top")) {
        new QRCode(document.getElementById("qrcode-top"), {
        text: jobCardUrl, // Make this dynamic
        width: 75, height: 75,
        colorDark: "#eb0a1e",
        colorLight: "rgba(255,255,255,0.9)",
        correctLevel: QRCode.CorrectLevel.H
        });
    }

    if (document.getElementById("qrcode-bottom")) {
        new QRCode(document.getElementById("qrcode-bottom"), {
        text: traVerificationUrl, // Make this dynamic
        width: 85, height: 85,
        colorDark: "#222",
        colorLight: "rgba(255,255,255,0.9)",
        correctLevel: QRCode.CorrectLevel.H
        });
    }
  </script>
</body>
</html>
