<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            // ── Interest Rates ────────────────────────────────
            [
                'key' => 'interest_rate_sc',
                'value' => '1.50',
                'type' => 'decimal',
                'group' => 'interest_rates',
                'description' => 'SC Members — Monthly Interest Rate',
                'suffix' => '%',
                'sort_order' => 1,
            ],
            [
                'key' => 'interest_rate_permanent',
                'value' => '1.00',
                'type' => 'decimal',
                'group' => 'interest_rates',
                'description' => 'Permanent Members — Monthly Interest Rate',
                'suffix' => '%',
                'sort_order' => 2,
            ],
            [
                'key' => 'interest_rate_non_member',
                'value' => '2.00',
                'type' => 'decimal',
                'group' => 'interest_rates',
                'description' => 'Non-Members — Monthly Interest Rate',
                'suffix' => '%',
                'sort_order' => 3,
            ],

            // ── SC Loan Rules ─────────────────────────────────
            [
                'key' => 'sc_min_take_home_pay',
                'value' => '5000.00',
                'type' => 'decimal',
                'group' => 'sc_loan_rules',
                'description' => 'Minimum Take-Home Pay for SC Salary Loan',
                'suffix' => 'PHP',
                'sort_order' => 1,
            ],
            [
                'key' => 'sc_requires_co_maker',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'sc_loan_rules',
                'description' => 'Require Permanent employee as Co-Maker',
                'sort_order' => 2,
            ],
            [
                'key' => 'sc_term_based_on_contract',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'sc_loan_rules',
                'description' => 'Loan term must not exceed remaining contract duration',
                'sort_order' => 3,
            ],
            [
                'key' => 'sc_max_loan_amount',
                'value' => '50000.00',
                'type' => 'decimal',
                'group' => 'sc_loan_rules',
                'description' => 'Maximum Loan Amount for SC',
                'suffix' => 'PHP',
                'sort_order' => 4,
            ],
            [
                'key' => 'sc_can_extend_with_board_approval',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'sc_loan_rules',
                'description' => 'SC loan term can be extended with Board Approval',
                'sort_order' => 5,
            ],
            [
                'key' => 'sc_available_terms',
                'value' => '3,6,12',
                'type' => 'text',
                'group' => 'sc_loan_rules',
                'description' => 'Available loan terms for SC (comma-separated, in months)',
                'suffix' => 'months',
                'sort_order' => 6,
            ],

            // ── Permanent Loan Rules ──────────────────────────
            [
                'key' => 'permanent_min_take_home_pay',
                'value' => '5000.00',
                'type' => 'decimal',
                'group' => 'permanent_loan_rules',
                'description' => 'Minimum Net Take-Home Pay for Permanent Loans',
                'suffix' => 'PHP',
                'sort_order' => 1,
            ],
            [
                'key' => 'permanent_max_loan_consolidated',
                'value' => '200000.00',
                'type' => 'decimal',
                'group' => 'permanent_loan_rules',
                'description' => 'Maximum Amount — Consolidated Loan',
                'suffix' => 'PHP',
                'sort_order' => 2,
            ],
            [
                'key' => 'permanent_max_loan_multipurpose',
                'value' => '100000.00',
                'type' => 'decimal',
                'group' => 'permanent_loan_rules',
                'description' => 'Maximum Amount — Multi-Purpose Loan',
                'suffix' => 'PHP',
                'sort_order' => 3,
            ],
            [
                'key' => 'permanent_max_loan_emergency',
                'value' => '30000.00',
                'type' => 'decimal',
                'group' => 'permanent_loan_rules',
                'description' => 'Maximum Amount — Emergency Loan',
                'suffix' => 'PHP',
                'sort_order' => 4,
            ],
            [
                'key' => 'permanent_max_loan_hospitalization',
                'value' => '50000.00',
                'type' => 'decimal',
                'group' => 'permanent_loan_rules',
                'description' => 'Maximum Amount — Hospitalization Loan',
                'suffix' => 'PHP',
                'sort_order' => 5,
            ],
            [
                'key' => 'permanent_requires_purpose',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'permanent_loan_rules',
                'description' => 'Require stated purpose for all Permanent loan types',
                'sort_order' => 6,
            ],
            [
                'key' => 'permanent_available_terms',
                'value' => '3,6,12,18,24,36,48,60',
                'type' => 'text',
                'group' => 'permanent_loan_rules',
                'description' => 'Available loan terms for Permanent (comma-separated, in months)',
                'suffix' => 'months',
                'sort_order' => 7,
            ],

            // ── Non-Member Rules ──────────────────────────────
            [
                'key' => 'non_member_max_loan_amount',
                'value' => '30000.00',
                'type' => 'decimal',
                'group' => 'non_member_rules',
                'description' => 'Maximum Loan Amount for Non-Members',
                'suffix' => 'PHP',
                'sort_order' => 1,
            ],
            [
                'key' => 'non_member_available_terms',
                'value' => '3,6,12,18,24',
                'type' => 'text',
                'group' => 'non_member_rules',
                'description' => 'Available loan terms for Non-Members (comma-separated, in months)',
                'suffix' => 'months',
                'sort_order' => 2,
            ],

            // ── Approval Workflow ─────────────────────────────
            [
                'key' => 'approval_levels_required',
                'value' => '3',
                'type' => 'number',
                'group' => 'approval_workflow',
                'description' => 'Number of approval levels before release (Receiver → Committee → Chairperson)',
                'sort_order' => 1,
            ],
            [
                'key' => 'auto_release_after_approval',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'approval_workflow',
                'description' => 'Automatically release loan after final approval',
                'sort_order' => 2,
            ],

            // ── Share Capital ─────────────────────────────────
            [
                'key' => 'share_capital_visible_to',
                'value' => 'permanent_only',
                'type' => 'select',
                'group' => 'share_capital',
                'description' => 'Who can view Share Capital',
                'options' => json_encode([
                    ['value' => 'permanent_only', 'label' => 'Permanent Members Only'],
                    ['value' => 'all_members', 'label' => 'All Members'],
                    ['value' => 'admin_only', 'label' => 'Admin Only'],
                ]),
                'sort_order' => 1,
            ],

            // ── Dependent & Coverage ──────────────────────────
            [
                'key' => 'dependent_coverage_options',
                'value' => 'Dental,Hospitalization',
                'type' => 'text',
                'group' => 'dependents_coverage',
                'description' => 'Available coverage options (comma-separated)',
                'sort_order' => 1,
            ],

            // ── Loan General Rules ────────────────────────────
            [
                'key' => 'min_loan_amount',
                'value' => '1000.00',
                'type' => 'decimal',
                'group' => 'approval_workflow',
                'description' => 'Minimum Loan Application Amount',
                'suffix' => 'PHP',
                'sort_order' => 10,
            ],
            [
                'key' => 'max_loan_term_months',
                'value' => '60',
                'type' => 'number',
                'group' => 'approval_workflow',
                'description' => 'Maximum Loan Term (absolute cap for all types)',
                'suffix' => 'months',
                'sort_order' => 11,
            ],
            [
                'key' => 'sc_extension_max_months',
                'value' => '12',
                'type' => 'number',
                'group' => 'sc_loan_rules',
                'description' => 'Maximum term allowed when SC contract extension is approved',
                'suffix' => 'months',
                'sort_order' => 7,
            ],
            [
                'key' => 'exemption_approval_validity_days',
                'value' => '90',
                'type' => 'number',
                'group' => 'approval_workflow',
                'description' => 'How long an approved exemption remains valid',
                'suffix' => 'days',
                'sort_order' => 12,
            ],

            // ── Notifications ─────────────────────────────────
            [
                'key' => 'overdue_payment_notify',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Send notification for overdue payments',
                'sort_order' => 1,
            ],
            [
                'key' => 'overdue_days_threshold',
                'value' => '30',
                'type' => 'number',
                'group' => 'notifications',
                'description' => 'Days after due date to trigger overdue alert',
                'suffix' => 'days',
                'sort_order' => 2,
            ],
            [
                'key' => 'payment_severely_overdue_days',
                'value' => '30',
                'type' => 'number',
                'group' => 'notifications',
                'description' => 'Days overdue to trigger severely overdue notification',
                'suffix' => 'days',
                'sort_order' => 3,
            ],
            [
                'key' => 'payment_due_soon_days',
                'value' => '5',
                'type' => 'number',
                'group' => 'notifications',
                'description' => 'Days before due date to send due-soon reminder',
                'suffix' => 'days',
                'sort_order' => 4,
            ],
            [
                'key' => 'notification_channel',
                'value' => 'email',
                'type' => 'select',
                'group' => 'notifications',
                'description' => 'Notification channel',
                'options' => json_encode([
                    ['value' => 'email', 'label' => 'Email Only'],
                    ['value' => 'sms', 'label' => 'SMS Only (coming soon)'],
                    ['value' => 'both', 'label' => 'Email + SMS'],
                ]),
                'sort_order' => 3,
            ],

            // ── OTP & Security ────────────────────────────────
            [
                'key' => 'otp_expiry_minutes',
                'value' => '10',
                'type' => 'number',
                'group' => 'security',
                'description' => 'OTP Expiration Time',
                'suffix' => 'min',
                'sort_order' => 1,
            ],
            [
                'key' => 'otp_length',
                'value' => '6',
                'type' => 'number',
                'group' => 'security',
                'description' => 'OTP Code Length',
                'suffix' => 'digits',
                'sort_order' => 2,
            ],
            [
                'key' => 'loan_otp_required',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'security',
                'description' => 'Require Email OTP for every Loan Application',
                'sort_order' => 3,
            ],
            [
                'key' => 'one_device_per_user',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'security',
                'description' => 'Lock mobile account to 1 device only',
                'sort_order' => 4,
            ],
            // ── Mobile App Settings ───────────────────────────
            [
                'key' => 'app_name',
                'value' => 'PMBF',
                'type' => 'text',
                'group' => 'mobile_app_general',
                'description' => 'App Display Name',
                'sort_order' => 1,
            ],
            [
                'key' => 'app_tagline',
                'value' => 'PhilRice Mutual Benefit Fund',
                'type' => 'text',
                'group' => 'mobile_app_general',
                'description' => 'App Tagline / Subtitle',
                'sort_order' => 2,
            ],
            [
                'key' => 'app_version_minimum',
                'value' => '1.0.0',
                'type' => 'text',
                'group' => 'mobile_app_general',
                'description' => 'Minimum Required App Version',
                'sort_order' => 3,
            ],
            [
                'key' => 'app_maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'mobile_app_general',
                'description' => 'Enable Mobile App Maintenance Mode',
                'sort_order' => 4,
            ],
            [
                'key' => 'app_maintenance_message',
                'value' => 'The app is currently under maintenance. Please try again later.',
                'type' => 'text',
                'group' => 'mobile_app_general',
                'description' => 'Maintenance Mode Message',
                'sort_order' => 5,
            ],
            [
                'key' => 'app_support_email',
                'value' => 'pmbf.philrice@gmail.com',
                'type' => 'text',
                'group' => 'mobile_app_general',
                'description' => 'Support Email Address',
                'sort_order' => 6,
            ],
            [
                'key' => 'app_support_phone',
                'value' => '',
                'type' => 'text',
                'group' => 'mobile_app_general',
                'description' => 'Support Phone Number',
                'sort_order' => 7,
            ],

            // ── Mobile App Features ──────────────────────────
            [
                'key' => 'mobile_login_otp_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'mobile_app_features',
                'description' => 'Enable Email OTP Login',
                'sort_order' => 1,
            ],
            [
                'key' => 'mobile_qr_login_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'mobile_app_features',
                'description' => 'Enable QR Code Login (scan web QR)',
                'sort_order' => 2,
            ],
            [
                'key' => 'mobile_biometric_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'mobile_app_features',
                'description' => 'Enable Biometric Login (Face/Fingerprint)',
                'sort_order' => 3,
            ],
            [
                'key' => 'mobile_loan_apply_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'mobile_app_features',
                'description' => 'Allow Loan Applications from Mobile',
                'sort_order' => 4,
            ],
            [
                'key' => 'mobile_claims_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'mobile_app_features',
                'description' => 'Allow Filing Claims from Mobile',
                'sort_order' => 5,
            ],

            // ── Mobile App Security ──────────────────────────
            [
                'key' => 'mobile_trust_device_days',
                'value' => '30',
                'type' => 'number',
                'group' => 'mobile_app_security',
                'description' => 'Trust Device Duration',
                'suffix' => 'days',
                'sort_order' => 1,
            ],
            [
                'key' => 'mobile_session_timeout',
                'value' => '60',
                'type' => 'number',
                'group' => 'mobile_app_security',
                'description' => 'Session Timeout (inactivity)',
                'suffix' => 'min',
                'sort_order' => 2,
            ],
            [
                'key' => 'mobile_max_otp_attempts',
                'value' => '5',
                'type' => 'number',
                'group' => 'mobile_app_security',
                'description' => 'Max OTP Attempts Before Lockout',
                'sort_order' => 3,
            ],
            [
                'key' => 'mobile_lockout_duration',
                'value' => '15',
                'type' => 'number',
                'group' => 'mobile_app_security',
                'description' => 'Lockout Duration After Max Attempts',
                'suffix' => 'min',
                'sort_order' => 4,
            ],
        ];

        foreach ($configs as $config) {
            $config['created_at'] = now();
            $config['updated_at'] = now();
            $config['options'] = $config['options'] ?? null;
            $config['suffix'] = $config['suffix'] ?? null;
            DB::table('configurations')->updateOrInsert(
                ['key' => $config['key']],
                $config
            );
        }
    }
}
