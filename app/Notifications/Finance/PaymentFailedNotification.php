<?php

namespace App\Notifications\Finance;

use App\Events\Finance\StripePaymentFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification: PaymentFailedNotification
 *
 * Sent to internal finance staff when a Stripe payment fails.
 * Used for follow-up with customers to resolve payment issues.
 */
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public StripePaymentFailed $event
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->event->payment;
        $customer = $payment->customer;

        $mail = (new MailMessage)
            ->subject('[Finance Alert] Payment Failed - Follow-up Required')
            ->greeting('Payment Failure Alert')
            ->line('A Stripe payment has failed and requires follow-up.')
            ->line('');

        // Payment details
        $mail->line('**Payment Details:**')
            ->line("- Reference: {$payment->payment_reference}")
            ->line("- Amount: {$payment->currency} ".number_format((float) $payment->amount, 2))
            ->line("- Stripe Payment Intent: {$this->event->paymentIntentId}");

        // Customer info
        if ($customer !== null) {
            $mail->line('')
                ->line('**Customer:**')
                ->line("- Name: {$customer->name}")
                ->line("- Email: {$customer->email}");
        } else {
            $mail->line('')
                ->line('**Customer:** Unable to identify');
        }

        // Failure details
        $mail->line('')
            ->line('**Failure Reason:**')
            ->line("- Code: {$this->event->failureCode}")
            ->line("- Message: {$this->event->failureMessage}");

        // Action items
        $mail->line('')
            ->line('**Recommended Actions:**')
            ->line('1. Review the payment details in the Finance admin panel')
            ->line('2. Contact the customer to resolve the payment issue')
            ->line('3. Check if the invoice requires manual payment recording');

        // Link to admin (if configured)
        $appUrl = config('app.url');
        $adminUrl = (is_string($appUrl) ? $appUrl : '').'/admin/finance/payments/'.$payment->id;
        $mail->action('View Payment Details', $adminUrl);

        $mail->line('')
            ->line('This is an automated notification from the ERP Finance module.');

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->event->getFailureDetails();
    }
}
