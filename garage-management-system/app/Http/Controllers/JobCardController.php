<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Job; // Assuming you'll have a Job model
use Illuminate\Support\Facades\View; // For View::exists

class JobCardController extends Controller
{
    /**
     * Display the specified job card.
     *
     * @param  int  $jobId  // Will change to Job $job for route model binding later
     * @return \Illuminate\Http\Response|\Illuminate\View\View
     */
    public function show($jobId) // Later: public function show(Job $job)
    {
        // Dummy data refined to match the Blade template's expectations
        $dateOpened = now();
        $dueDate = $dateOpened->copy()->addDays(30);

        $subTotal = 71656.00 + 22165.00 + 105000.00 + 12500.00 + 108476.76; // Based on static HTML items
        $discountRate = 0.05; // 5% discount
        $discountAmount = $subTotal * $discountRate;
        $subTotalAfterDiscount = $subTotal - $discountAmount;
        $taxRate = 0.18; // 18% VAT
        $taxAmount = $subTotalAfterDiscount * $taxRate;
        $totalDue = $subTotalAfterDiscount + $taxAmount;
        $amountPaid = 50000.00; // Example partial payment
        $balanceDue = $totalDue - $amountPaid;

        $dummyJobData = [
            'id' => $jobId,
            'job_card_number' => 'JC-' . str_pad($jobId, 4, '0', STR_PAD_LEFT) . '-' . $dateOpened->format('Y'),
            'status' => 'In Progress',
            'date_opened' => $dateOpened->toDateTimeString(),
            'due_date' => $dueDate->toDateTimeString(), // For payment terms
            'customer' => [ // Corresponds to 'Invoice To'
                'name' => 'Alpha Solutions Ltd.',
                'phone' => '0784 123 456',
                'email' => 'contact@alphasolutions.co.tz',
                'address' => '14 Industrial Area, Mikocheni',
                'city' => 'Dar es Salaam', // Added for completeness
                'tin_number' => '102-304-506', // Added for completeness
            ],
            'vehicle' => [ // Implicitly part of the job, though not directly on invoice header
                'make' => 'Mitsubishi',
                'model' => 'Fuso Canter',
                'year' => '2018',
                'registration_number' => 'T789 GHI',
                'vin' => 'MMC123FGHY987654',
            ],
            'branch' => [ // Corresponds to 'Invoice From'
                'name' => 'GARAGE PREMIUM - Masaki',
                'address_line1' => 'Plot 47, Haile Selassie Rd',
                'city' => 'Masaki, Dar es Salaam',
                'phone' => '+255 755 999 888',
                'email' => 'masaki@garage-tz.com',
                'logo_url' => null, // Will be handled later
                'tin_number' => '100-146-304', // Company TIN
                'vrn_number' => '10-006645-E', // Company VRN
                'account_number' => 'ACC-MSK-001', // Branch specific account if needed
                'salesman' => 'Mr. Juma Kondo', // Example salesman
            ],
            'services' => [ // Goods / Services
                ['description' => '00888084965 Engine Oil 15W40 SL / JO415238010 ELEMENT', 'quantity' => 2, 'unit_price' => 35828.00, 'total_price' => 71656.00],
                ['description' => 'J04152Y2ZD5 Auto Fluid WS', 'quantity' => 1, 'unit_price' => 22165.00, 'total_price' => 22165.00],
            ],
            'parts' => [ // More Goods / Services
                 ['name' => 'QEP5001A Emergency Safety Kit', 'quantity' => 1, 'unit_price' => 105000.00, 'total_price' => 105000.00],
                 ['name' => 'Professional Installation Service', 'quantity' => 1, 'unit_price' => 12500.00, 'total_price' => 12500.00],
                 ['name' => 'Express Shipping & Handling', 'quantity' => 1, 'unit_price' => 108476.76, 'total_price' => 108476.76], // Example of a service/charge
            ],
            'payment_terms' => "Payment due by " . $dueDate->format('d/m/Y') . ". Late payments subject to 1.5% monthly interest.",
            'sub_total' => $subTotal,
            'discount_rate' => $discountRate,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_due' => $totalDue,
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
            'notes_to_customer' => 'All parts and services are guaranteed for 90 days. Thank you for choosing Garage Premium!',
            'tra_verification_url' => 'https://verify.tra.go.tz/INV' . $jobId . $dateOpened->format('Ymd'),
            'internal_verification_code' => 'VF' . $jobId . '-' . time(), // Example internal code
            'branch_account_details' => 'GARAGE PREMIUM - Masaki, Account #: 0123456789 (NBC Bank)', // For payment instructions
        ];

        // Actual implementation will fetch Job $job and related data
        // For now, we pass this dummy data.
        // $job = Job::with(['branch', 'vehicle.owner', 'services', 'parts'])->findOrFail($jobId);

        if (!View::exists('jobcards.standard')) {
            return response('Job card view not found.', 404);
        }

        return view('jobcards.standard', ['job' => (object)$dummyJobData]); // Cast to object to mimic Eloquent model
    }
}
