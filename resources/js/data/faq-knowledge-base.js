/**
 * PMBF FAQ Knowledge Base
 * ========================
 * This file contains the structured FAQ data used by the AI chatbot (Gemini)
 * as system context, and also by the ML recommendation engine for suggesting
 * related questions to users.
 *
 * Categories:
 *   1. General / About PMBF
 *   2. Account & Login
 *   3. Loans (General)
 *   4. Loans (SC Members)
 *   5. Loans (Permanent Members)
 *   6. Loans (Non-Members)
 *   7. Loan Application Process
 *   8. Loan Approval Workflow
 *   9. Co-Maker
 *  10. Exemptions
 *  11. Payments
 *  12. Share Capital
 *  13. Dependents & Benefits
 *  14. Claims
 *  15. Mobile App
 *  16. Admin & Staff
 */

export const FAQ_CATEGORIES = [
  'General',
  'Account & Login',
  'Loans',
  'Loan Application',
  'Approval Process',
  'Co-Maker',
  'Exemptions',
  'Payments',
  'Share Capital',
  'Dependents & Benefits',
  'Claims',
  'Mobile App',
  'Admin & Staff',
]

export const FAQ_ITEMS = [
  // ─── 1. GENERAL / ABOUT PMBF ──────────────────────────────────
  {
    id: 1,
    category: 'General',
    question: 'What is PMBF?',
    answer: 'PMBF stands for PhilRice Mutual Benefit Fund. It is a cooperative-style financial management system for PhilRice employees that provides loan services, share capital management, and member benefits including dental and hospitalization coverage.',
    keywords: ['pmbf', 'what', 'about', 'provident', 'mutual', 'benefit', 'fund', 'philrice'],
  },
  {
    id: 2,
    category: 'General',
    question: 'Who can be a member of PMBF?',
    answer: 'PMBF serves three types of members:\n• **Permanent employees** — full access to all loan types and benefits\n• **SC (Service Contract) employees** — eligible for Salary Loans with terms based on contract duration\n• **Non-Members** — limited loan access (Salary Loan, Multi-Purpose, Emergency) with a maximum of ₱30,000',
    keywords: ['member', 'join', 'eligible', 'who', 'types', 'permanent', 'sc', 'contract', 'non-member'],
  },
  {
    id: 3,
    category: 'General',
    question: 'What services does PMBF offer?',
    answer: 'PMBF offers the following services:\n• **Loan facilities** — multiple loan types with competitive interest rates\n• **Share capital** — monthly savings/investment for permanent members\n• **Benefits** — dental and hospitalization coverage for members and dependents\n• **Claims** — file claims for dental, hospitalization, or other covered expenses',
    keywords: ['services', 'offer', 'provide', 'features', 'loan', 'share', 'benefit', 'claim'],
  },
  {
    id: 4,
    category: 'General',
    question: 'How can I contact PMBF support?',
    answer: 'You can reach PMBF support through:\n• **Email:** pmbf.philrice@gmail.com\n• You can also visit the PMBF office during business hours for in-person assistance.',
    keywords: ['contact', 'support', 'help', 'email', 'phone', 'reach', 'office'],
  },

  // ─── 2. ACCOUNT & LOGIN ──────────────────────────────────────
  {
    id: 5,
    category: 'Account & Login',
    question: 'How do I register for a PMBF account?',
    answer: 'Registration is simple and uses your PhilRice Employee ID:\n1. Enter your **Employee ID** on the registration page\n2. The system verifies your identity against the HRIS (Human Resource Information System)\n3. An **OTP (One-Time Password)** is sent to your registered email\n4. Enter the OTP to complete registration\n\nNo password is needed — PMBF uses passwordless authentication.',
    keywords: ['register', 'sign up', 'create account', 'new account', 'employee id', 'otp'],
  },
  {
    id: 6,
    category: 'Account & Login',
    question: 'How do I log in to PMBF?',
    answer: 'PMBF uses **passwordless OTP login**:\n1. Enter your **Employee ID**\n2. An OTP is sent to your registered email\n3. Enter the OTP to log in\n\nIf your device is **trusted**, you may be logged in automatically without needing an OTP.',
    keywords: ['login', 'sign in', 'log in', 'otp', 'password', 'access'],
  },
  {
    id: 7,
    category: 'Account & Login',
    question: 'What is a trusted device?',
    answer: 'A trusted device is a phone or computer that you\'ve previously verified. When you log in and choose to "trust this device," the system remembers it for **30 days**. During this period, you can log in without entering an OTP each time. You can view and revoke trusted devices from your profile settings.',
    keywords: ['trusted', 'device', 'remember', 'skip otp', '30 days', 'revoke'],
  },
  {
    id: 8,
    category: 'Account & Login',
    question: 'My OTP is not arriving. What should I do?',
    answer: 'If your OTP isn\'t arriving:\n• Check your **spam/junk folder** — the email comes from pmbf.philrice@gmail.com\n• Make sure your registered email is correct in the HRIS\n• Wait a moment — OTPs can take up to a minute to arrive\n• Use the **"Resend OTP"** button to request a new one\n• OTPs expire after **10 minutes**, so request a fresh one if needed\n• After **5 failed attempts**, you\'ll be locked out for **15 minutes**',
    keywords: ['otp', 'not arriving', 'email', 'spam', 'resend', 'expired', 'locked out', 'code'],
  },
  {
    id: 9,
    category: 'Account & Login',
    question: 'What is QR code login?',
    answer: 'QR login lets you sign in to the **web app** using your **mobile app**:\n1. The web app displays a QR code\n2. Open the PMBF mobile app and scan the QR code\n3. Approve the login on your phone\n4. The web app automatically logs you in\n\nThis is a convenient and secure way to access the web portal.',
    keywords: ['qr', 'code', 'scan', 'web', 'mobile', 'login', 'approve'],
  },
  {
    id: 10,
    category: 'Account & Login',
    question: 'Can I use multiple devices?',
    answer: 'By default, PMBF enforces **single-device login** for security. When you log in on a new device, your previous session is automatically ended. This means only one active session is allowed at a time. You can trust multiple devices, but only one can be actively logged in.',
    keywords: ['multiple', 'devices', 'session', 'single', 'one device', 'another phone'],
  },

  // ─── 3. LOANS (GENERAL) ──────────────────────────────────────
  {
    id: 11,
    category: 'Loans',
    question: 'What types of loans does PMBF offer?',
    answer: 'PMBF offers the following loan types:\n\n**For Permanent Members:**\n• Consolidated — up to ₱200,000\n• Multi-Purpose — up to ₱100,000\n• Emergency — up to ₱30,000\n• Hospitalization — up to ₱50,000\n\n**For SC Members:**\n• Salary Loan — based on monthly salary × contract months\n\n**For Non-Members:**\n• Salary Loan, Multi-Purpose, Emergency — up to ₱30,000 each',
    keywords: ['loan types', 'kinds', 'available', 'consolidated', 'multi-purpose', 'emergency', 'hospitalization', 'salary loan'],
  },
  {
    id: 12,
    category: 'Loans',
    question: 'What are the interest rates?',
    answer: 'PMBF uses **flat interest rates** (not diminishing balance):\n\n| Member Type | Monthly Rate |\n|---|---|\n| SC Members | 1.50% per month |\n| Permanent Members | 1.00% per month |\n| Non-Members | 2.00% per month |\n\n**Example:** A ₱10,000 loan at 1% for 12 months:\n• Total Interest = ₱10,000 × 1% × 12 = ₱1,200\n• Monthly Amortization = (₱10,000 + ₱1,200) ÷ 12 = ₱933.33',
    keywords: ['interest', 'rate', 'percent', 'monthly', 'flat', 'how much', 'computation', 'calculate'],
  },
  {
    id: 13,
    category: 'Loans',
    question: 'What loan terms are available?',
    answer: 'Available repayment terms depend on your member type:\n\n• **Permanent Members:** 3, 6, 12, 18, 24, 36, 48, or 60 months\n• **SC Members:** 3, 6, or 12 months (limited by remaining contract duration)\n• **Non-Members:** 3, 6, 12, 18, or 24 months\n\nThe maximum term across all types is **60 months (5 years)**.',
    keywords: ['term', 'months', 'duration', 'how long', 'repayment', 'period', 'years'],
  },
  {
    id: 14,
    category: 'Loans',
    question: 'What is the minimum loan amount?',
    answer: 'The minimum loan amount is **₱1,000** for all member types.',
    keywords: ['minimum', 'amount', 'lowest', 'smallest', 'at least', '1000'],
  },
  {
    id: 15,
    category: 'Loans',
    question: 'Can I have multiple active loans?',
    answer: 'You **cannot** have two active loans of the **same type** at the same time. For example, you cannot have two active Emergency loans. However, you may have different types of loans active simultaneously (e.g., one Consolidated and one Emergency loan), provided you are eligible.\n\nAlso, you **cannot apply** for a new loan while you have any loan still **pending approval**.',
    keywords: ['multiple', 'loans', 'active', 'two', 'same time', 'pending', 'another loan', 'duplicate'],
  },
  {
    id: 16,
    category: 'Loans',
    question: 'How is my monthly amortization calculated?',
    answer: 'PMBF uses **flat interest** calculation:\n\n```\nTotal Interest = Loan Amount × Monthly Rate × Term (months)\nMonthly Amortization = (Loan Amount + Total Interest) ÷ Term\n```\n\n**Example:** ₱50,000 loan, 1% monthly rate, 12 months:\n• Total Interest = ₱50,000 × 0.01 × 12 = ₱6,000\n• Monthly Amortization = (₱50,000 + ₱6,000) ÷ 12 = **₱4,666.67/month**\n• Total Payable = ₱56,000',
    keywords: ['amortization', 'monthly payment', 'compute', 'calculate', 'formula', 'how much pay'],
  },

  // ─── 4. LOANS (SC MEMBERS) ────────────────────────────────────
  {
    id: 17,
    category: 'Loans',
    question: 'How is the maximum loan amount calculated for SC members?',
    answer: 'For SC (Service Contract) members, the maximum loan is:\n\n**Max Loan = Monthly Salary × Contract Months**\n\nFor example, if your monthly salary is ₱18,000 and your contract is 6 months:\n• Max Loan = ₱18,000 × 6 = ₱108,000\n\nWith a board-approved extension, the formula becomes:\n**Extended Max = Monthly Salary × max(Contract Months, 12)**\n\nThe configured SC max loan cap is ₱50,000.',
    keywords: ['sc', 'maximum', 'loan amount', 'salary', 'contract', 'service contract', 'how much borrow'],
  },
  {
    id: 18,
    category: 'Loans',
    question: 'Why is my loan term limited as an SC member?',
    answer: 'SC member loan terms are **limited by your remaining contract duration**. The system only allows terms that fit within the time left on your contract.\n\nFor example, if you have 8 months remaining on your contract, you can only choose 3 or 6 months — not 12.\n\nIf no standard term fits but you have remaining months, the system offers your exact remaining months as the only option.\n\nYou can request a **term extension exemption** if you need a longer term (up to 12 months with board approval).',
    keywords: ['sc', 'term', 'limited', 'contract', 'remaining', 'months', 'extension', 'why short'],
  },
  {
    id: 19,
    category: 'Loans',
    question: 'Do SC members need a co-maker?',
    answer: 'Yes, **SC members are required to have a co-maker** for their loan application. The co-maker must be a **Permanent employee** with an active status in the system. The co-maker will receive an email to approve or decline the request before the loan proceeds to the approval workflow.',
    keywords: ['sc', 'co-maker', 'required', 'need', 'guarantor', 'permanent'],
  },

  // ─── 5. LOANS (PERMANENT MEMBERS) ─────────────────────────────
  {
    id: 20,
    category: 'Loans',
    question: 'What are the loan limits for Permanent members?',
    answer: 'Permanent members can apply for the following loan types with these maximum amounts:\n\n| Loan Type | Maximum Amount |\n|---|---|\n| Consolidated | ₱200,000 |\n| Multi-Purpose | ₱100,000 |\n| Emergency | ₱30,000 |\n| Hospitalization | ₱50,000 |\n\n**Note:** The actual maximum is the **lesser of** the configured limit or your **net take-home pay** from FMIS. If you need more than the limit, you can apply for an exemption.',
    keywords: ['permanent', 'limit', 'maximum', 'consolidated', 'multi-purpose', 'emergency', 'hospitalization', 'cap'],
  },

  // ─── 6. LOANS (NON-MEMBERS) ───────────────────────────────────
  {
    id: 21,
    category: 'Loans',
    question: 'What loans are available for Non-Members?',
    answer: 'Non-Members can apply for:\n• **Salary Loan**\n• **Multi-Purpose Loan**\n• **Emergency Loan**\n\nAll non-member loans have a maximum amount of **₱30,000** and available terms of **3, 6, 12, 18, or 24 months** at an interest rate of **2% per month**.',
    keywords: ['non-member', 'non member', 'available', 'loan', 'limit', '30000'],
  },

  // ─── 7. LOAN APPLICATION PROCESS ──────────────────────────────
  {
    id: 22,
    category: 'Loan Application',
    question: 'How do I apply for a loan?',
    answer: 'To apply for a loan:\n1. Log in to PMBF (web or mobile app)\n2. Go to **Loans** → **Apply for a Loan**\n3. Select your **loan type** and enter the **amount**, **term**, and **purpose**\n4. If you\'re an SC member, select a **co-maker** (Permanent employee)\n5. Review the computed interest and monthly amortization\n6. An **OTP** is sent to your email for verification\n7. Enter the OTP to confirm and submit your application\n\nYour loan will then enter the approval workflow.',
    keywords: ['apply', 'how to', 'application', 'process', 'steps', 'submit', 'new loan'],
  },
  {
    id: 23,
    category: 'Loan Application',
    question: 'Is OTP required when applying for a loan?',
    answer: 'Yes, by default an **OTP verification** is required when submitting a loan application. After filling out the loan details, you\'ll see a preview of your loan (interest rate, monthly amortization, total payable), and an OTP will be sent to your email. Enter the OTP to confirm the application.\n\nThis is a security measure to prevent unauthorized loan applications.',
    keywords: ['otp', 'loan', 'required', 'verification', 'confirm', 'submit'],
  },
  {
    id: 24,
    category: 'Loan Application',
    question: 'What are the eligibility requirements for a loan?',
    answer: 'To be eligible for a loan, you must meet these requirements:\n\n1. **Minimum take-home pay** — Your net take-home pay must be at least ₱5,000\n2. **No pending loans** — You cannot have any loan currently pending approval\n3. **No duplicate active loan** — You cannot have an active loan of the same type\n4. **Valid employment** — You must be an active employee in the HRIS\n\nIf you don\'t meet the minimum pay requirement, you can apply for an **exemption**.',
    keywords: ['eligibility', 'requirements', 'qualify', 'eligible', 'minimum pay', 'take home', 'can i apply'],
  },
  {
    id: 25,
    category: 'Loan Application',
    question: 'Can I cancel my loan application?',
    answer: 'Yes, you can cancel your loan application as long as it is still in one of these statuses:\n• **Pending** (awaiting Receiver review)\n• **Receiver Approved** (awaiting Committee)\n• **Committee Approved** (awaiting Chairperson)\n\nOnce approved by the Chairperson or released, the loan **cannot be cancelled**. Go to your loan details and click the **Cancel** button.',
    keywords: ['cancel', 'withdraw', 'stop', 'application', 'undo', 'revoke'],
  },

  // ─── 8. LOAN APPROVAL WORKFLOW ────────────────────────────────
  {
    id: 26,
    category: 'Approval Process',
    question: 'What is the loan approval process?',
    answer: 'PMBF uses a **3-level approval workflow**:\n\n1. **Receiver** — initial review and verification\n2. **Loan Committee** — assessment of loan terms and risk\n3. **Chairperson** — final approval\n\nAfter Chairperson approval, the loan is marked as approved and can be **released** by the Receiver or Admin.\n\n**Additional steps may apply:**\n• If SC member with co-maker → co-maker consent required first\n• If amount exceeds the type limit → admin pre-approval required',
    keywords: ['approval', 'process', 'workflow', 'steps', 'levels', 'receiver', 'committee', 'chairperson', 'how long'],
  },
  {
    id: 27,
    category: 'Approval Process',
    question: 'What are the different loan statuses?',
    answer: 'Your loan can have these statuses:\n\n| Status | Meaning |\n|---|---|\n| Co-Maker Pending | Waiting for co-maker to approve |\n| Co-Maker Declined | Co-maker refused — loan cannot proceed |\n| Admin Pending | Over-limit loan awaiting admin approval |\n| Pending | In Receiver\'s review queue |\n| Receiver Approved | Passed to Loan Committee |\n| Committee Approved | Passed to Chairperson |\n| Chairperson Approved | Fully approved, awaiting release |\n| Released | Funds have been disbursed |\n| Completed | Loan fully paid |\n| Disapproved | Rejected at any approval stage |\n| Cancelled | Cancelled by you or admin |',
    keywords: ['status', 'meaning', 'pending', 'approved', 'released', 'disapproved', 'cancelled', 'what does'],
  },
  {
    id: 28,
    category: 'Approval Process',
    question: 'My loan was disapproved. What can I do?',
    answer: 'If your loan was disapproved:\n• Check the **remarks** left by the approver for the reason\n• Address the issue mentioned in the remarks\n• You may **apply again** with adjusted terms (lower amount, different type, etc.)\n• If you believe the disapproval was in error, contact the PMBF office\n\nCommon reasons for disapproval: insufficient take-home pay, incomplete documentation, or exceeding borrowing limits.',
    keywords: ['disapproved', 'rejected', 'denied', 'why', 'reason', 'reapply', 'again'],
  },

  // ─── 9. CO-MAKER ─────────────────────────────────────────────
  {
    id: 29,
    category: 'Co-Maker',
    question: 'What is a co-maker?',
    answer: 'A co-maker is a **Permanent employee** who guarantees your loan. They agree to share responsibility for the loan repayment. Co-makers are required for **SC (Service Contract) member** loans.\n\nWhen you select a co-maker during your loan application, they will receive an **email notification** to either approve or decline the co-maker request.',
    keywords: ['co-maker', 'comaker', 'what', 'guarantor', 'cosigner', 'co signer'],
  },
  {
    id: 30,
    category: 'Co-Maker',
    question: 'Who can be a co-maker?',
    answer: 'To be eligible as a co-maker, a person must:\n• Be a **Permanent employee** (not SC or Non-Member)\n• Have an **active** status in the system\n• **Not** be the loan applicant themselves\n\nYou can search for available co-makers by name or Employee ID when applying for a loan.',
    keywords: ['co-maker', 'eligible', 'who can', 'requirements', 'permanent', 'qualifications'],
  },
  {
    id: 31,
    category: 'Co-Maker',
    question: 'I was asked to be a co-maker. How do I respond?',
    answer: 'When someone selects you as their co-maker, you\'ll receive an **email** with two options:\n• **Approve** — you agree to co-sign the loan\n• **Decline** — you refuse the request\n\nYou can also respond through the **PMBF system** under your pending co-maker requests. If you decline, the applicant\'s loan status changes to "Co-Maker Declined" and they\'ll need to find another co-maker or reapply.',
    keywords: ['co-maker', 'respond', 'approve', 'decline', 'email', 'asked', 'request', 'notification'],
  },
  {
    id: 32,
    category: 'Co-Maker',
    question: 'What happens if my co-maker declines?',
    answer: 'If your co-maker **declines** the request:\n• Your loan status changes to **"Co-Maker Declined"**\n• The loan does **not** proceed to the approval workflow\n• You will be notified via email\n• You will need to **apply for a new loan** and select a different co-maker',
    keywords: ['co-maker', 'declined', 'refused', 'what happens', 'rejected', 'new co-maker'],
  },

  // ─── 10. EXEMPTIONS ──────────────────────────────────────────
  {
    id: 33,
    category: 'Exemptions',
    question: 'What is an exemption request?',
    answer: 'An exemption request allows you to **override certain loan restrictions**. You can request exemptions for:\n\n• **Below Minimum Pay** — if your net take-home pay is below the ₱5,000 threshold\n• **Exceed Max Amount** — if you need to borrow more than your loan type\'s limit\n• **Extend Term** — if you\'re an SC member needing a term beyond your remaining contract\n\nExemption requests are reviewed and approved/rejected by an **Admin**.',
    keywords: ['exemption', 'override', 'exception', 'request', 'waiver', 'bypass', 'limit'],
  },
  {
    id: 34,
    category: 'Exemptions',
    question: 'How long is an approved exemption valid?',
    answer: 'An approved exemption is valid for **90 days** from the date of approval. After 90 days, the exemption expires and you would need to submit a new request if still needed.\n\nMake sure to apply for your loan within this validity period.',
    keywords: ['exemption', 'valid', 'how long', 'expire', 'duration', '90 days', 'validity'],
  },
  {
    id: 35,
    category: 'Exemptions',
    question: 'How do I request an exemption?',
    answer: 'To request an exemption:\n1. Go to **Exemptions** in the system\n2. Select the **type** of exemption you need\n3. Select the **loan type** it applies to\n4. Enter your **reason** (up to 2,000 characters)\n5. Verify with **OTP** if required\n6. Submit the request\n\nAll admins will be notified of your request. You\'ll receive a notification when it\'s approved or rejected.',
    keywords: ['request', 'exemption', 'how to', 'apply', 'submit', 'steps'],
  },

  // ─── 11. PAYMENTS ─────────────────────────────────────────────
  {
    id: 36,
    category: 'Payments',
    question: 'How do I make a loan payment?',
    answer: 'Loan payments are recorded by the **PMBF Admin/Receiver**. Payment methods include:\n• **Cash**\n• **Payroll Deduction**\n• **Bank Transfer**\n\nThe admin will record the payment amount, date, OR number, and payment method. You don\'t make payments directly through the app — payments are processed by the PMBF office and recorded in the system.',
    keywords: ['payment', 'pay', 'how to', 'make payment', 'cash', 'payroll', 'bank transfer'],
  },
  {
    id: 37,
    category: 'Payments',
    question: 'When is my loan payment due?',
    answer: 'Your first payment is due **1 month after your loan is released**. Subsequent payments are due every month after that.\n\n**Example:** If your loan was released on January 15, your payments are due:\n• 1st payment: February 15\n• 2nd payment: March 15\n• And so on...\n\nYou\'ll receive a notification **5 days before** your payment is due.',
    keywords: ['due', 'when', 'payment date', 'schedule', 'monthly', 'deadline', 'next payment'],
  },
  {
    id: 38,
    category: 'Payments',
    question: 'What happens if my payment is overdue?',
    answer: 'PMBF sends notifications at different stages:\n\n• **Due Soon** — 5 days before the due date\n• **Overdue** — immediately after the due date passes\n• **Severely Overdue** — 30 days past the due date\n\nPlease contact the PMBF office as soon as possible if you\'re unable to make a payment on time to discuss your options.',
    keywords: ['overdue', 'late', 'missed', 'penalty', 'not paid', 'behind', 'delinquent', 'past due'],
  },
  {
    id: 39,
    category: 'Payments',
    question: 'How do I check my remaining loan balance?',
    answer: 'To check your remaining balance:\n1. Go to **Loans** → select your active loan\n2. The loan detail page shows:\n   • **Total Payable** (principal + interest)\n   • **Total Paid** (sum of all payments made)\n   • **Remaining Balance** (total payable minus total paid)\n   • **Payment History** (all recorded payments)\n\nWhen your total payments equal or exceed the total payable, the loan is automatically marked as **Completed**.',
    keywords: ['balance', 'remaining', 'how much left', 'owe', 'total', 'paid', 'check'],
  },

  // ─── 12. SHARE CAPITAL ────────────────────────────────────────
  {
    id: 40,
    category: 'Share Capital',
    question: 'What is share capital?',
    answer: 'Share capital is a **monthly savings/investment contribution** tracked by PMBF. Each member has a monthly share amount recorded per month and year. It represents your equity or stake in the fund.\n\nShare capital is currently visible to **Permanent members only** by default, but this can be configured by the admin.',
    keywords: ['share capital', 'what', 'savings', 'investment', 'contribution', 'equity', 'stake'],
  },
  {
    id: 41,
    category: 'Share Capital',
    question: 'How do I update my share capital amount?',
    answer: 'To request a share capital update:\n1. Go to **Share Capital** in the system\n2. Click **Request Update**\n3. Enter your desired new monthly amount\n4. Provide a reason for the change\n5. Submit the request\n\nAn admin will review and approve/reject your request. If approved, the new amount takes effect **starting the following month**. You can only have **one pending request** at a time.',
    keywords: ['share', 'update', 'change', 'amount', 'increase', 'decrease', 'request', 'monthly'],
  },
  {
    id: 42,
    category: 'Share Capital',
    question: 'How do I view my share capital history?',
    answer: 'Go to **Share Capital** → **My Shares** to view:\n• Your **current monthly** share amount\n• **Monthly history** for the selected year\n• **Total shares** accumulated\n• Status of any **pending update requests**\n\nYou can filter by year to see historical data.',
    keywords: ['share', 'history', 'view', 'total', 'monthly', 'accumulated', 'records'],
  },

  // ─── 13. DEPENDENTS & BENEFITS ────────────────────────────────
  {
    id: 43,
    category: 'Dependents & Benefits',
    question: 'How do I add a dependent?',
    answer: 'To add a dependent:\n1. Go to **Profile** → **Dependents**\n2. Click **Add Dependent**\n3. Enter their details: first name, last name, relationship, birth date\n4. Select **coverage type**: Dental, Hospitalization, or Both\n5. Upload any required attachments\n6. Save\n\nYou can manage (view and remove) your dependents at any time.',
    keywords: ['dependent', 'add', 'family', 'spouse', 'child', 'beneficiary', 'register'],
  },
  {
    id: 44,
    category: 'Dependents & Benefits',
    question: 'What benefits are available?',
    answer: 'PMBF provides the following benefit coverage:\n• **Dental** — dental care coverage for members and dependents\n• **Hospitalization** — hospitalization coverage for members and dependents\n\nBenefits are linked to your membership and dependent registration. You can view your benefits summary under **Benefits** in the system.',
    keywords: ['benefits', 'available', 'dental', 'hospitalization', 'coverage', 'what', 'types'],
  },

  // ─── 14. CLAIMS ───────────────────────────────────────────────
  {
    id: 45,
    category: 'Claims',
    question: 'How do I file a claim?',
    answer: 'To file a claim:\n1. Go to **Claims** → **File a Claim**\n2. Select the **claim type**: Dental, Hospitalization, or Other\n3. Optionally link it to a **dependent**\n4. Enter a **description** and **amount**\n5. Upload supporting **documents/receipts**\n6. Submit\n\nYour claim will start with a **Pending** status and will be reviewed by the PMBF admin.',
    keywords: ['claim', 'file', 'submit', 'how to', 'dental', 'hospitalization', 'reimbursement', 'request'],
  },
  {
    id: 46,
    category: 'Claims',
    question: 'What are the claim statuses?',
    answer: 'Claims go through these statuses:\n• **Pending** — submitted, awaiting review\n• **Approved** — claim has been approved\n• **Disapproved** — claim was rejected\n• **Released** — payment/reimbursement has been processed\n\nCheck the remarks for any additional notes from the reviewer.',
    keywords: ['claim', 'status', 'pending', 'approved', 'disapproved', 'released', 'track'],
  },

  // ─── 15. MOBILE APP ───────────────────────────────────────────
  {
    id: 47,
    category: 'Mobile App',
    question: 'What can I do on the PMBF mobile app?',
    answer: 'The PMBF mobile app lets you:\n• **Apply for loans** and track their status\n• **View loan details** and payment history\n• **Approve/decline co-maker requests**\n• **Approve loans** (if you\'re a staff approver)\n• **Manage dependents** and file claims\n• **View benefits** and share capital\n• **Scan QR codes** for web login\n• **Receive push notifications** for loan updates and payment reminders',
    keywords: ['mobile', 'app', 'features', 'what can', 'android', 'phone', 'download'],
  },
  {
    id: 48,
    category: 'Mobile App',
    question: 'How do I set up my PIN on the mobile app?',
    answer: 'When you first log in to the mobile app, you\'ll be prompted to **set up a PIN** (4-6 digits). This PIN is stored locally on your device (hashed for security) and is used to quickly access the app on subsequent opens.\n\nIf your device supports it, you can also enable **biometric authentication** (fingerprint or face recognition) to skip PIN entry.',
    keywords: ['pin', 'setup', 'mobile', 'biometric', 'fingerprint', 'face', 'security', 'local'],
  },
  {
    id: 49,
    category: 'Mobile App',
    question: 'I\'m getting a "maintenance mode" message on the app',
    answer: 'When the app shows a maintenance message, it means the PMBF system is temporarily unavailable for updates or repairs. This is controlled by the admin.\n\nPlease wait and try again later. If the issue persists for an extended period, contact PMBF support at **pmbf.philrice@gmail.com**.',
    keywords: ['maintenance', 'mode', 'unavailable', 'down', 'not working', 'app', 'error'],
  },

  // ─── 16. ADMIN & STAFF ────────────────────────────────────────
  {
    id: 50,
    category: 'Admin & Staff',
    question: 'What are the different staff roles?',
    answer: 'PMBF has the following roles:\n\n| Role | Responsibilities |\n|---|---|\n| **Member** | Apply for loans, manage dependents/claims |\n| **Receiver** | First-level loan approval, release approved loans |\n| **Loan Committee** | Second-level loan approval |\n| **Chairperson** | Final loan approval |\n| **Admin** | Full system access: configurations, reports, payments, role assignment, exemptions |',
    keywords: ['roles', 'staff', 'admin', 'receiver', 'committee', 'chairperson', 'who approves', 'permissions'],
  },
  {
    id: 51,
    category: 'Admin & Staff',
    question: 'How do I generate a report?',
    answer: 'Admins can generate reports from **Admin** → **Reports**:\n• **Loan Report** — summary by type, status, amounts\n• **Payment Report** — collections by method, transaction count\n• **Member Report** — member count by employment type\n• **Share Capital Report** — total shares by member\n\nReports can be viewed as **JSON data** or downloaded as **CSV files** for Excel. **PDF reports** are also available for individual loans.',
    keywords: ['report', 'generate', 'csv', 'pdf', 'export', 'download', 'summary', 'excel'],
  },
  {
    id: 52,
    category: 'Admin & Staff',
    question: 'How do I record a payment as admin?',
    answer: 'To record a payment:\n1. Go to **Admin** → **Payments**\n2. Click **Record Payment**\n3. Select the **loan** (only released/approved loans shown)\n4. Enter the **amount**, **payment date**, and **OR number**\n5. Select the **payment method** (Cash, Payroll Deduction, or Bank Transfer)\n6. Optionally upload a **receipt image**\n7. Save\n\nIf the total payments reach the total payable amount, the loan is automatically marked as **Completed**.',
    keywords: ['record', 'payment', 'admin', 'how to', 'or number', 'receipt', 'amount'],
  },
]

/**
 * System prompt for Gemini AI — provides full context about PMBF
 * so that the chatbot can answer questions accurately.
 */
export const SYSTEM_PROMPT = `You are PMBF Assistant, the official AI help assistant for the PMBF (PhilRice Mutual Benefit Fund) system of PhilRice. You help members, staff, and admins with questions about loans, payments, benefits, share capital, claims, and system usage.

IMPORTANT RULES:
- Only answer questions related to PMBF. If asked about unrelated topics, politely redirect: "I can only help with PMBF-related questions. Is there something about your loans, payments, benefits, or account I can help with?"
- Be friendly, concise, and helpful. Use bullet points and formatting for clarity.
- If you're unsure about something, say so and suggest contacting PMBF support at pmbf.philrice@gmail.com.
- Use Philippine Peso (₱) for all currency references.
- Never make up information — stick to the knowledge base below.
- Always respond in a professional but approachable tone.
- You may respond in Filipino/Tagalog if the user writes in Filipino.

KNOWLEDGE BASE:

## About PMBF
PMBF stands for PhilRice Mutual Benefit Fund, a cooperative-style financial system for PhilRice employees. It manages loans, share capital, and member benefits (dental & hospitalization).

## Member Types
1. Permanent Employees — full access to all loan types (Consolidated, Multi-Purpose, Emergency, Hospitalization)
2. SC (Service Contract) — Salary Loan only, terms limited by contract duration, co-maker required
3. Non-Members — limited access (Salary Loan, Multi-Purpose, Emergency), max ₱30,000

## Loan Types & Limits
- Consolidated (Permanent only): up to ₱200,000
- Multi-Purpose (Permanent & Non-Member): up to ₱100,000 / ₱30,000
- Emergency (Permanent & Non-Member): up to ₱30,000
- Hospitalization (Permanent only): up to ₱50,000
- Salary Loan (SC & Non-Member): SC max = monthly salary × contract months, max ₱50,000; Non-Member max = ₱30,000
- Permanent loan limits are also capped by net take-home pay (whichever is lower)
- Minimum loan amount: ₱1,000

## Interest Rates (Flat, Monthly)
- SC Members: 1.50% per month
- Permanent Members: 1.00% per month
- Non-Members: 2.00% per month
- Formula: Total Interest = Principal × Monthly Rate × Term Months
- Monthly Amortization = (Principal + Total Interest) ÷ Term Months

## Loan Terms
- Permanent: 3, 6, 12, 18, 24, 36, 48, 60 months
- SC: 3, 6, 12 months (limited by remaining contract)
- Non-Member: 3, 6, 12, 18, 24 months
- Maximum: 60 months

## Eligibility Requirements
1. Minimum take-home pay: ₱5,000 (can be exempted)
2. No pending loan applications
3. No active loan of the same type
4. Active employee in HRIS

## Loan Application Process
1. Select loan type, amount, term, purpose
2. SC members: select a co-maker (must be Permanent employee)
3. Review computed interest and amortization
4. Verify with email OTP
5. Submit application

## Approval Workflow (3 levels)
1. Receiver → 2. Loan Committee → 3. Chairperson
- Additional pre-steps: Co-maker consent (SC) → Admin pre-approval (if over limit)
- After Chairperson approval, Receiver or Admin releases the loan
- Cancellation allowed while in Pending, Receiver Approved, or Committee Approved status

## Loan Statuses
co_maker_pending, co_maker_declined, admin_pending, pending, receiver_approved, committee_approved, chairperson_approved, released, completed, disapproved, cancelled

## Co-Maker Rules
- Required for SC members
- Must be a Permanent employee with active status
- Cannot be the applicant
- Notified via email to approve/decline
- If declined: loan becomes "co_maker_declined"

## Exemption System
Types: below_minimum_pay, exceed_max_amount, extend_term
- Requires admin approval
- Valid for 90 days after approval
- OTP verification may be required

## Payments
- Recorded by Admin/Receiver (not self-service)
- Methods: Cash, Payroll Deduction, Bank Transfer
- First payment due: 1 month after loan release
- Notifications: 5 days before due, on overdue, 30 days severely overdue
- Loan auto-completes when fully paid

## Share Capital
- Monthly savings tracked per member per month
- Members can request amount updates (admin approval needed)
- Approved updates take effect next month
- Visible to Permanent members by default

## Benefits & Claims
- Coverage: Dental, Hospitalization
- Dependents can be registered with coverage type
- Claims filed with supporting documents
- Claim statuses: Pending → Approved/Disapproved → Released

## Authentication
- Passwordless OTP login (email-based)
- QR code login for web (scan with mobile app)
- Trusted devices (30 days)
- PIN + Biometric on mobile app
- Single session enforcement
- OTP expires in 10 minutes, max 5 attempts, 15-minute lockout

## Mobile App Features
- Loan application & tracking
- Approval workflow (staff)
- Dependent management & claims
- QR scanner for web login
- Push notifications
- PIN & biometric security

## Staff Roles
- Member: apply loans, manage dependents/claims
- Receiver: 1st-level approval + loan release
- Loan Committee: 2nd-level approval
- Chairperson: final approval
- Admin: full system access (config, reports, payments, roles, exemptions)

## Support
Email: pmbf.philrice@gmail.com`
