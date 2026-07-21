<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
use App\Services\GeminiService;
use App\Services\LoanChatService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ChatbotService $chatbotService,
        private GeminiService $geminiService,
        private LoanChatService $loanChatService,
    ) {}

    /**
     * POST /api/v1/chatbot/message
     *
     * Send a message to the AI chatbot.
     * Accepts: { message: string, history: [{ role, content }] }
     */
    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|string|in:user,model',
            'history.*.content' => 'required_with:history|string',
        ]);

        $userMessage = $request->input('message');
        $history = $request->input('history', []);
        $user = $request->user(); // nullable — works for both auth and guest

        // 0. Loan application intent — handled by rules, never the AI, so the
        //    figures always come from LoanService and the flow works with no
        //    Gemini token configured.
        $loanChat = $this->loanChatService->handle($userMessage, $user, $history);

        if ($loanChat !== null) {
            return $this->success([
                'reply' => $loanChat['reply'],
                'action' => $loanChat['action'],
                'smart_data' => $loanChat['slots'],
                'intent' => 'loan_application',
            ]);
        }

        // 1. Check for smart query (DB-backed answer)
        $smartQuery = $this->chatbotService->resolveSmartQuery($userMessage, $user);
        $smartContext = $smartQuery['context'] ?? null;

        // 2. Build conversation for Gemini
        $conversation = $history;
        $conversation[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        // 3. Get system prompt
        $systemPrompt = $this->buildSystemPrompt();

        // 4. Call Gemini
        $reply = $this->geminiService->chat($systemPrompt, $conversation, $smartContext);

        // 5. Fallback: if Gemini is unavailable, use local FAQ matching
        if ($reply === null) {
            $reply = $this->localFallback($userMessage, $smartContext);
        }

        return $this->success([
            'reply' => $reply,
            'smart_data' => $smartQuery['data'] ?? null,
            'intent' => $smartQuery['intent'] ?? null,
        ]);
    }

    /**
     * GET /api/v1/chatbot/suggestions
     *
     * Return suggested FAQ questions based on optional partial input.
     * Used for the ML-based auto-recommend feature.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = mb_strtolower(trim($request->input('q', '')));
        $user = $request->user();

        $suggestions = $this->getSuggestions($query, $user);

        return $this->success($suggestions);
    }

    /**
     * ML-like suggestion engine: scores FAQ items by keyword overlap,
     * user context relevance, and recency bias.
     */
    private function getSuggestions(string $query, $user = null): array
    {
        $faqs = $this->getFaqItems();
        $scored = [];

        // Tokenize user query
        $queryTokens = $this->tokenize($query);

        foreach ($faqs as $faq) {
            $score = 0;

            if (!empty($query)) {
                // Keyword match scoring
                foreach ($faq['keywords'] as $keyword) {
                    $kwTokens = $this->tokenize($keyword);
                    foreach ($queryTokens as $qt) {
                        foreach ($kwTokens as $kwt) {
                            if ($qt === $kwt) {
                                $score += 3; // exact token match
                            } elseif (str_contains($kwt, $qt) || str_contains($qt, $kwt)) {
                                $score += 2; // partial match
                            } elseif ($this->levenshteinSimilar($qt, $kwt)) {
                                $score += 1; // fuzzy match (typo-tolerant)
                            }
                        }
                    }
                }

                // Question text similarity
                $qTokens = $this->tokenize($faq['question']);
                foreach ($queryTokens as $qt) {
                    foreach ($qTokens as $fqt) {
                        if ($qt === $fqt) $score += 2;
                        elseif (str_contains($fqt, $qt)) $score += 1;
                    }
                }
            } else {
                // No query: rank by user-relevance
                $score = 1;
            }

            // User-context boost
            if ($user) {
                $empType = mb_strtolower($user->employment_type ?? '');
                $questionLower = mb_strtolower($faq['question']);
                $categoryLower = mb_strtolower($faq['category']);

                // Boost FAQs relevant to user's employment type
                if (str_contains($questionLower, $empType) || str_contains(implode(' ', $faq['keywords']), $empType)) {
                    $score += 2;
                }

                // Boost common categories
                if (in_array($categoryLower, ['loans', 'payments', 'loan application'])) {
                    $score += 1;
                }
            }

            if ($score > 0) {
                $scored[] = [
                    'id' => $faq['id'],
                    'question' => $faq['question'],
                    'category' => $faq['category'],
                    'score' => $score,
                ];
            }
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] - $a['score']);

        // Return top 4
        return array_slice($scored, 0, 4);
    }

    private function tokenize(string $text): array
    {
        $text = mb_strtolower(trim($text));
        // Remove punctuation
        $text = preg_replace('/[^\w\s₱]/u', '', $text);
        // Split by whitespace
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        // Remove stopwords
        $stopwords = ['the', 'is', 'a', 'an', 'in', 'to', 'for', 'of', 'and', 'or', 'my', 'i', 'me', 'do', 'does', 'what', 'how', 'can', 'ko', 'ang', 'ng', 'sa', 'na', 'ba', 'po', 'ano', 'paano'];
        return array_values(array_filter($tokens, fn($t) => strlen($t) > 1 && !in_array($t, $stopwords)));
    }

    private function levenshteinSimilar(string $a, string $b): bool
    {
        if (strlen($a) < 3 || strlen($b) < 3) return false;
        $distance = levenshtein($a, $b);
        $maxLen = max(strlen($a), strlen($b));
        return ($distance / $maxLen) <= 0.35; // 35% tolerance
    }

    private function getFaqItems(): array
    {
        return [
            ['id' => 1, 'category' => 'General', 'question' => 'What is PMBF?', 'keywords' => ['pmbf', 'about', 'provident', 'mutual', 'benefit']],
            ['id' => 2, 'category' => 'General', 'question' => 'Who can be a member of PMBF?', 'keywords' => ['member', 'join', 'eligible', 'types', 'permanent', 'sc']],
            ['id' => 3, 'category' => 'General', 'question' => 'What services does PMBF offer?', 'keywords' => ['services', 'offer', 'features', 'loan', 'share', 'benefit']],
            ['id' => 4, 'category' => 'General', 'question' => 'How can I contact PMBF support?', 'keywords' => ['contact', 'support', 'help', 'email', 'phone']],
            ['id' => 5, 'category' => 'Account & Login', 'question' => 'How do I register for a PMBF account?', 'keywords' => ['register', 'sign up', 'create account', 'employee id']],
            ['id' => 6, 'category' => 'Account & Login', 'question' => 'How do I log in to PMBF?', 'keywords' => ['login', 'sign in', 'otp', 'password', 'access']],
            ['id' => 7, 'category' => 'Account & Login', 'question' => 'What is a trusted device?', 'keywords' => ['trusted', 'device', 'remember', 'skip otp']],
            ['id' => 8, 'category' => 'Account & Login', 'question' => 'My OTP is not arriving. What should I do?', 'keywords' => ['otp', 'not arriving', 'email', 'spam', 'resend', 'expired']],
            ['id' => 9, 'category' => 'Account & Login', 'question' => 'What is QR code login?', 'keywords' => ['qr', 'code', 'scan', 'web', 'mobile']],
            ['id' => 10, 'category' => 'Account & Login', 'question' => 'Can I use multiple devices?', 'keywords' => ['multiple', 'devices', 'session', 'single', 'one device']],
            ['id' => 11, 'category' => 'Loans', 'question' => 'What types of loans does PMBF offer?', 'keywords' => ['loan types', 'kinds', 'available', 'consolidated', 'multi-purpose', 'emergency']],
            ['id' => 12, 'category' => 'Loans', 'question' => 'What are the interest rates?', 'keywords' => ['interest', 'rate', 'percent', 'monthly', 'flat']],
            ['id' => 13, 'category' => 'Loans', 'question' => 'What loan terms are available?', 'keywords' => ['term', 'months', 'duration', 'how long', 'repayment']],
            ['id' => 14, 'category' => 'Loans', 'question' => 'What is the minimum loan amount?', 'keywords' => ['minimum', 'amount', 'lowest', '1000']],
            ['id' => 15, 'category' => 'Loans', 'question' => 'Can I have multiple active loans?', 'keywords' => ['multiple', 'loans', 'active', 'two', 'same time', 'pending']],
            ['id' => 16, 'category' => 'Loans', 'question' => 'How is my monthly amortization calculated?', 'keywords' => ['amortization', 'monthly payment', 'compute', 'calculate', 'formula']],
            ['id' => 17, 'category' => 'Loans', 'question' => 'How is the maximum loan calculated for SC members?', 'keywords' => ['sc', 'maximum', 'salary', 'contract', 'service contract']],
            ['id' => 18, 'category' => 'Loans', 'question' => 'Why is my loan term limited as an SC member?', 'keywords' => ['sc', 'term', 'limited', 'contract', 'remaining']],
            ['id' => 19, 'category' => 'Loans', 'question' => 'Do SC members need a co-maker?', 'keywords' => ['sc', 'co-maker', 'required', 'guarantor']],
            ['id' => 20, 'category' => 'Loans', 'question' => 'What are the loan limits for Permanent members?', 'keywords' => ['permanent', 'limit', 'maximum', 'cap']],
            ['id' => 21, 'category' => 'Loans', 'question' => 'What loans are available for Non-Members?', 'keywords' => ['non-member', 'available', 'loan', 'limit']],
            ['id' => 22, 'category' => 'Loan Application', 'question' => 'How do I apply for a loan?', 'keywords' => ['apply', 'application', 'process', 'steps', 'new loan']],
            ['id' => 23, 'category' => 'Loan Application', 'question' => 'Is OTP required when applying for a loan?', 'keywords' => ['otp', 'loan', 'required', 'verification']],
            ['id' => 24, 'category' => 'Loan Application', 'question' => 'What are the eligibility requirements?', 'keywords' => ['eligibility', 'requirements', 'qualify', 'eligible', 'minimum pay']],
            ['id' => 25, 'category' => 'Loan Application', 'question' => 'Can I cancel my loan application?', 'keywords' => ['cancel', 'withdraw', 'stop', 'application']],
            ['id' => 26, 'category' => 'Approval Process', 'question' => 'What is the loan approval process?', 'keywords' => ['approval', 'process', 'workflow', 'levels', 'receiver', 'committee']],
            ['id' => 27, 'category' => 'Approval Process', 'question' => 'What are the different loan statuses?', 'keywords' => ['status', 'meaning', 'pending', 'approved', 'released']],
            ['id' => 28, 'category' => 'Approval Process', 'question' => 'My loan was disapproved. What can I do?', 'keywords' => ['disapproved', 'rejected', 'denied', 'reapply']],
            ['id' => 29, 'category' => 'Co-Maker', 'question' => 'What is a co-maker?', 'keywords' => ['co-maker', 'comaker', 'guarantor', 'cosigner']],
            ['id' => 30, 'category' => 'Co-Maker', 'question' => 'Who can be a co-maker?', 'keywords' => ['co-maker', 'eligible', 'who can', 'requirements', 'permanent']],
            ['id' => 31, 'category' => 'Co-Maker', 'question' => 'I was asked to be a co-maker. How do I respond?', 'keywords' => ['co-maker', 'respond', 'approve', 'decline', 'asked']],
            ['id' => 32, 'category' => 'Co-Maker', 'question' => 'What happens if my co-maker declines?', 'keywords' => ['co-maker', 'declined', 'refused', 'rejected']],
            ['id' => 33, 'category' => 'Exemptions', 'question' => 'What is an exemption request?', 'keywords' => ['exemption', 'override', 'exception', 'waiver']],
            ['id' => 34, 'category' => 'Exemptions', 'question' => 'How long is an approved exemption valid?', 'keywords' => ['exemption', 'valid', 'expire', '90 days']],
            ['id' => 35, 'category' => 'Exemptions', 'question' => 'How do I request an exemption?', 'keywords' => ['request', 'exemption', 'apply', 'submit']],
            ['id' => 36, 'category' => 'Payments', 'question' => 'How do I make a loan payment?', 'keywords' => ['payment', 'pay', 'cash', 'payroll', 'bank transfer']],
            ['id' => 37, 'category' => 'Payments', 'question' => 'When is my loan payment due?', 'keywords' => ['due', 'when', 'payment date', 'schedule', 'deadline']],
            ['id' => 38, 'category' => 'Payments', 'question' => 'What happens if my payment is overdue?', 'keywords' => ['overdue', 'late', 'missed', 'penalty', 'past due']],
            ['id' => 39, 'category' => 'Payments', 'question' => 'How do I check my remaining loan balance?', 'keywords' => ['balance', 'remaining', 'how much left', 'owe']],
            ['id' => 40, 'category' => 'Share Capital', 'question' => 'What is share capital?', 'keywords' => ['share capital', 'savings', 'investment', 'contribution']],
            ['id' => 41, 'category' => 'Share Capital', 'question' => 'How do I update my share capital amount?', 'keywords' => ['share', 'update', 'change', 'increase', 'decrease']],
            ['id' => 42, 'category' => 'Share Capital', 'question' => 'How do I view my share capital history?', 'keywords' => ['share', 'history', 'view', 'total', 'accumulated']],
            ['id' => 43, 'category' => 'Dependents & Benefits', 'question' => 'How do I add a dependent?', 'keywords' => ['dependent', 'add', 'family', 'spouse', 'child']],
            ['id' => 44, 'category' => 'Dependents & Benefits', 'question' => 'What benefits are available?', 'keywords' => ['benefits', 'dental', 'hospitalization', 'coverage']],
            ['id' => 45, 'category' => 'Claims', 'question' => 'How do I file a claim?', 'keywords' => ['claim', 'file', 'submit', 'dental', 'hospitalization']],
            ['id' => 46, 'category' => 'Claims', 'question' => 'What are the claim statuses?', 'keywords' => ['claim', 'status', 'pending', 'approved', 'released']],
            ['id' => 47, 'category' => 'Mobile App', 'question' => 'What can I do on the PMBF mobile app?', 'keywords' => ['mobile', 'app', 'features', 'android', 'phone']],
            ['id' => 48, 'category' => 'Mobile App', 'question' => 'How do I set up my PIN on the mobile app?', 'keywords' => ['pin', 'setup', 'mobile', 'biometric', 'fingerprint']],
            ['id' => 49, 'category' => 'Mobile App', 'question' => "I'm getting a maintenance mode message", 'keywords' => ['maintenance', 'mode', 'unavailable', 'down', 'not working']],
            ['id' => 50, 'category' => 'Admin & Staff', 'question' => 'What are the different staff roles?', 'keywords' => ['roles', 'staff', 'admin', 'receiver', 'committee', 'chairperson']],
            ['id' => 51, 'category' => 'Admin & Staff', 'question' => 'How do I generate a report?', 'keywords' => ['report', 'generate', 'csv', 'pdf', 'export']],
            ['id' => 52, 'category' => 'Admin & Staff', 'question' => 'How do I record a payment as admin?', 'keywords' => ['record', 'payment', 'admin', 'or number', 'receipt']],
        ];
    }

    /**
     * Local fallback when Gemini is unavailable.
     * Matches user message against FAQ answers using keyword scoring.
     */
    private function localFallback(string $message, ?string $smartContext): string
    {
        // If we have smart context (DB data), return it directly
        if ($smartContext) {
            return $smartContext;
        }

        // Match against FAQ answers
        $faqAnswers = $this->getFaqAnswers();
        $queryTokens = $this->tokenize($message);
        $bestScore = 0;
        $bestAnswer = null;

        foreach ($faqAnswers as $faq) {
            $score = 0;
            foreach ($faq['keywords'] as $keyword) {
                $kwTokens = $this->tokenize($keyword);
                foreach ($queryTokens as $qt) {
                    foreach ($kwTokens as $kwt) {
                        if ($qt === $kwt) $score += 3;
                        elseif (str_contains($kwt, $qt) || str_contains($qt, $kwt)) $score += 2;
                        elseif ($this->levenshteinSimilar($qt, $kwt)) $score += 1;
                    }
                }
            }
            // Also match against question text
            foreach ($queryTokens as $qt) {
                foreach ($this->tokenize($faq['question']) as $fqt) {
                    if ($qt === $fqt) $score += 2;
                    elseif (str_contains($fqt, $qt)) $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestAnswer = $faq['answer'];
            }
        }

        if ($bestAnswer && $bestScore >= 3) {
            return $bestAnswer;
        }

        return "I'm sorry, I couldn't find a specific answer to your question right now. Here are some things I can help with:\n\n"
            . "• **Loans** — types, interest rates, eligibility, application process\n"
            . "• **Payments** — due dates, balance, payment methods\n"
            . "• **Share Capital** — monthly savings, updates\n"
            . "• **Benefits & Claims** — dental, hospitalization coverage\n"
            . "• **Account** — login, OTP, trusted devices\n\n"
            . "Try asking a more specific question, or contact **pmbf.philrice@gmail.com** for support.";
    }

    private function getFaqAnswers(): array
    {
        return [
            ['question' => 'What is PMBF?', 'answer' => "PMBF stands for **PhilRice Mutual Benefit Fund**. It is a cooperative-style financial management system for PhilRice employees that provides loan services, share capital management, and member benefits including dental and hospitalization coverage.", 'keywords' => ['pmbf', 'about', 'provident', 'mutual', 'benefit', 'fund', 'philrice']],
            ['question' => 'Who can be a member?', 'answer' => "PMBF serves three types of members:\n• **Permanent employees** — full access to all loan types and benefits\n• **SC (Service Contract) employees** — eligible for Salary Loans with terms based on contract duration\n• **Non-Members** — limited loan access (Salary Loan, Multi-Purpose, Emergency) with a maximum of ₱30,000", 'keywords' => ['member', 'join', 'eligible', 'types', 'permanent', 'sc', 'non-member']],
            ['question' => 'What services does PMBF offer?', 'answer' => "PMBF offers:\n• **Loan facilities** — multiple loan types with competitive interest rates\n• **Share capital** — monthly savings/investment\n• **Benefits** — dental and hospitalization coverage\n• **Claims** — file claims for covered expenses", 'keywords' => ['services', 'offer', 'features']],
            ['question' => 'How to contact support?', 'answer' => "You can reach PMBF support at **pmbf.philrice@gmail.com**. You can also visit the PMBF office during business hours.", 'keywords' => ['contact', 'support', 'help', 'email', 'phone']],
            ['question' => 'How to register?', 'answer' => "To register:\n1. Enter your **Employee ID**\n2. The system verifies against HRIS\n3. An **OTP** is sent to your email\n4. Enter the OTP to complete registration\n\nNo password needed — PMBF uses passwordless authentication.", 'keywords' => ['register', 'sign up', 'create account', 'employee id']],
            ['question' => 'How to log in?', 'answer' => "PMBF uses **passwordless OTP login**:\n1. Enter your **Employee ID**\n2. An OTP is sent to your email\n3. Enter the OTP to log in\n\nIf your device is **trusted**, you skip the OTP step.", 'keywords' => ['login', 'sign in', 'log in', 'otp', 'password', 'access']],
            ['question' => 'What is a trusted device?', 'answer' => "A trusted device is remembered for **30 days** so you can log in without an OTP. You can view and revoke trusted devices from your profile.", 'keywords' => ['trusted', 'device', 'remember', 'skip otp']],
            ['question' => 'OTP not arriving?', 'answer' => "If your OTP isn't arriving:\n• Check your **spam/junk folder**\n• Make sure your email is correct in HRIS\n• Use the **\"Resend OTP\"** button\n• OTPs expire after **10 minutes**\n• After **5 failed attempts**, you're locked out for **15 minutes**", 'keywords' => ['otp', 'not arriving', 'email', 'spam', 'resend', 'expired', 'locked']],
            ['question' => 'What loan types are available?', 'answer' => "**Permanent Members:**\n• Consolidated — up to ₱200,000\n• Multi-Purpose — up to ₱100,000\n• Emergency — up to ₱30,000\n• Hospitalization — up to ₱50,000\n\n**SC Members:** Salary Loan only\n\n**Non-Members:** Salary Loan, Multi-Purpose, Emergency — up to ₱30,000 each", 'keywords' => ['loan types', 'kinds', 'available', 'consolidated', 'multi-purpose', 'emergency', 'hospitalization', 'salary loan']],
            ['question' => 'What are the interest rates?', 'answer' => "PMBF uses **flat interest rates**:\n• **SC Members:** 1.50% per month\n• **Permanent Members:** 1.00% per month\n• **Non-Members:** 2.00% per month\n\nFormula: Total Interest = Principal × Rate × Term Months", 'keywords' => ['interest', 'rate', 'percent', 'monthly', 'flat', 'how much']],
            ['question' => 'What loan terms are available?', 'answer' => "• **Permanent:** 3, 6, 12, 18, 24, 36, 48, 60 months\n• **SC:** 3, 6, 12 months (limited by contract)\n• **Non-Member:** 3, 6, 12, 18, 24 months\n\nMaximum: **60 months**", 'keywords' => ['term', 'months', 'duration', 'how long', 'repayment']],
            ['question' => 'How is amortization calculated?', 'answer' => "**Flat Interest Formula:**\n• Total Interest = Loan Amount × Monthly Rate × Term\n• Monthly Amortization = (Loan Amount + Total Interest) ÷ Term\n\n**Example:** ₱50,000 at 1% for 12 months:\n• Interest = ₱6,000\n• Monthly = ₱4,666.67\n• Total Payable = ₱56,000", 'keywords' => ['amortization', 'monthly payment', 'compute', 'calculate', 'formula']],
            ['question' => 'How to apply for a loan?', 'answer' => "1. Go to **Loans → Apply for a Loan**\n2. Select loan type, amount, term, and purpose\n3. SC members: select a **co-maker**\n4. Review the computed interest\n5. Verify with **email OTP**\n6. Submit your application", 'keywords' => ['apply', 'application', 'process', 'steps', 'new loan']],
            ['question' => 'Eligibility requirements?', 'answer' => "To be eligible:\n1. **Minimum take-home pay:** ₱5,000\n2. **No pending loans** awaiting approval\n3. **No active loan** of the same type\n4. **Active employee** in HRIS\n\nIf you don't meet minimum pay, apply for an **exemption**.", 'keywords' => ['eligibility', 'requirements', 'qualify', 'eligible', 'minimum pay', 'can i apply']],
            ['question' => 'What is the approval process?', 'answer' => "**3-level approval:**\n1. **Receiver** — initial review\n2. **Loan Committee** — assessment\n3. **Chairperson** — final approval\n\nAfter approval, Receiver or Admin **releases** the loan.\n\nExtra steps if applicable: co-maker consent (SC), admin pre-approval (over limit).", 'keywords' => ['approval', 'process', 'workflow', 'levels', 'receiver', 'committee', 'chairperson']],
            ['question' => 'What is a co-maker?', 'answer' => "A co-maker is a **Permanent employee** who guarantees your loan. Required for **SC members**. They receive an email to approve or decline the request.", 'keywords' => ['co-maker', 'comaker', 'guarantor', 'cosigner']],
            ['question' => 'What is an exemption?', 'answer' => "Exemptions override loan restrictions:\n• **Below Minimum Pay** — if take-home < ₱5,000\n• **Exceed Max Amount** — borrow more than the limit\n• **Extend Term** — SC term beyond contract\n\nApproved by admin, valid for **90 days**.", 'keywords' => ['exemption', 'override', 'exception', 'waiver', 'bypass']],
            ['question' => 'How to make a payment?', 'answer' => "Payments are recorded by **PMBF Admin/Receiver**:\n• **Cash**\n• **Payroll Deduction**\n• **Bank Transfer**\n\nYou don't pay directly through the app — visit the PMBF office.", 'keywords' => ['payment', 'pay', 'cash', 'payroll', 'bank transfer']],
            ['question' => 'When is payment due?', 'answer' => "First payment: **1 month after loan release**. Subsequent: every month.\n\nYou'll get a notification **5 days before** the due date. After 30 days overdue, you receive a severely overdue alert.", 'keywords' => ['due', 'when', 'payment date', 'schedule', 'deadline', 'next payment']],
            ['question' => 'What is share capital?', 'answer' => "Share capital is a **monthly savings contribution** tracked by PMBF. You can request to update your amount (admin approval needed). Approved updates take effect **next month**.", 'keywords' => ['share capital', 'savings', 'investment', 'contribution']],
            ['question' => 'What benefits are available?', 'answer' => "PMBF provides:\n• **Dental** coverage\n• **Hospitalization** coverage\n\nBenefits cover members and registered dependents.", 'keywords' => ['benefits', 'dental', 'hospitalization', 'coverage']],
            ['question' => 'How to file a claim?', 'answer' => "1. Go to **Claims → File a Claim**\n2. Select type: Dental, Hospitalization, or Other\n3. Link to a dependent (optional)\n4. Enter description and amount\n5. Upload supporting documents\n6. Submit\n\nStatus: Pending → Approved/Disapproved → Released", 'keywords' => ['claim', 'file', 'submit', 'dental', 'hospitalization', 'reimbursement']],
            ['question' => 'Can I cancel my loan?', 'answer' => "Yes, you can cancel while in **Pending**, **Receiver Approved**, or **Committee Approved** status. Once approved by the Chairperson or released, it **cannot be cancelled**.", 'keywords' => ['cancel', 'withdraw', 'stop', 'undo']],
            ['question' => 'Loan was disapproved?', 'answer' => "If disapproved:\n• Check the **remarks** for the reason\n• Address the issue\n• **Apply again** with adjusted terms\n\nCommon reasons: insufficient take-home pay, exceeding limits.", 'keywords' => ['disapproved', 'rejected', 'denied', 'reapply']],
            ['question' => 'Staff roles?', 'answer' => "• **Member** — apply loans, manage dependents/claims\n• **Receiver** — 1st-level approval + loan release\n• **Loan Committee** — 2nd-level approval\n• **Chairperson** — final approval\n• **Admin** — full system access", 'keywords' => ['roles', 'staff', 'admin', 'receiver', 'committee', 'chairperson']],
            ['question' => 'Mobile app features?', 'answer' => "The PMBF mobile app lets you:\n• Apply for loans and track status\n• Approve/decline co-maker requests\n• Manage dependents and file claims\n• Scan QR codes for web login\n• Receive push notifications\n• Use PIN and biometric security", 'keywords' => ['mobile', 'app', 'features', 'android', 'phone']],
        ];
    }

    private function buildSystemPrompt(): string
    {
        // Use the same system prompt from the knowledge base
        // This is inlined here to avoid a JS import
        return "You are PMBF Assistant, the official AI help assistant for the PMBF (PhilRice Mutual Benefit Fund) system of PhilRice. You help members, staff, and admins with questions about loans, payments, benefits, share capital, claims, and system usage.

IMPORTANT RULES:
- Only answer questions related to PMBF. If asked about unrelated topics, politely redirect: \"I can only help with PMBF-related questions. Is there something about your loans, payments, benefits, or account I can help with?\"
- Be friendly, concise, and helpful. Use bullet points and formatting for clarity.
- If you're unsure about something, say so and suggest contacting PMBF support at pmbf.philrice@gmail.com.
- Use Philippine Peso (₱) for all currency references.
- Never make up information — stick to the knowledge base below.
- Always respond in a professional but approachable tone.
- You may respond in Filipino/Tagalog if the user writes in Filipino.
- When the message includes [SYSTEM DATA], incorporate that live data naturally into your response — it comes from real-time database queries and is always accurate.
- Keep responses concise — ideally under 200 words unless the user asks for detail.

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
- If declined: loan becomes \"co_maker_declined\"

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
Email: pmbf.philrice@gmail.com";
    }
}
