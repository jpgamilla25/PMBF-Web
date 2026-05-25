<?php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Loan $loan,
        public string $alertType, // 'due_soon', 'overdue', 'severely_overdue'
        public string $dueDate,
        public int $daysOverdue = 0,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $titles = [
            'due_soon' => 'Payment Due Soon',
            'overdue' => 'Payment Overdue',
            'severely_overdue' => 'Payment Severely Overdue',
        ];

        $messages = [
            'due_soon' => "Your {$this->loan->loan_type} payment of ₱" . number_format($this->loan->monthly_amortization, 2) . " is due on {$this->dueDate}.",
            'overdue' => "Your {$this->loan->loan_type} payment is overdue by {$this->daysOverdue} day(s). Please settle your balance.",
            'severely_overdue' => "Your {$this->loan->loan_type} payment is {$this->daysOverdue} days overdue. Please settle immediately to avoid penalties.",
        ];

        $icons = [
            'due_soon' => 'bi-clock',
            'overdue' => 'bi-exclamation-triangle',
            'severely_overdue' => 'bi-exclamation-octagon',
        ];

        return [
            'title' => $titles[$this->alertType] ?? 'Payment Alert',
            'message' => $messages[$this->alertType] ?? '',
            'icon' => $icons[$this->alertType] ?? 'bi-bell',
            'url' => '/loans/' . $this->loan->id,
            'loan_id' => $this->loan->id,
            'loan_type' => $this->loan->loan_type,
            'alert_type' => $this->alertType,
            'due_date' => $this->dueDate,
            'amount_due' => $this->loan->monthly_amortization,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->alertType) {
            'due_soon' => 'PMBF — Your Loan Payment Is Due Soon',
            'overdue' => 'PMBF — Your Loan Payment Is Overdue',
            'severely_overdue' => 'PMBF — URGENT: Loan Payment Severely Overdue',
            default => 'PMBF — Payment Alert',
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->full_name . ',');

        if ($this->alertType === 'due_soon') {
            $mail->line("This is a reminder that your **{$this->loan->loan_type}** monthly payment of **₱" . number_format($this->loan->monthly_amortization, 2) . "** is due on **{$this->dueDate}**.");
        } elseif ($this->alertType === 'overdue') {
            $mail->line("Your **{$this->loan->loan_type}** payment was due on **{$this->dueDate}** and is now **{$this->daysOverdue} day(s) overdue**.")
                ->line('Please settle your payment as soon as possible.');
        } else {
            $mail->line("⚠️ **URGENT:** Your **{$this->loan->loan_type}** payment is **{$this->daysOverdue} days overdue**.")
                ->line('Please contact the PMBF office immediately to avoid further penalties.');
        }

        $mail->line('**Loan Details:**')
            ->line("Amount: ₱" . number_format($this->loan->amount, 2))
            ->line("Monthly Amortization: ₱" . number_format($this->loan->monthly_amortization, 2))
            ->line("Remaining Balance: ₱" . number_format($this->loan->remaining_balance, 2))
            ->salutation('PMBF Financial Management System');

        return $mail;
    }
}
