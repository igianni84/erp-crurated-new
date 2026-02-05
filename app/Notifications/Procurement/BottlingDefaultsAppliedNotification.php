<?php

namespace App\Notifications\Procurement;

use App\Models\Procurement\BottlingInstruction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to Ops when bottling defaults are automatically applied
 * due to deadline expiry.
 */
class BottlingDefaultsAppliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public BottlingInstruction $instruction
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bottling Defaults Applied')
            ->line('Bottling defaults have been automatically applied to instruction '.$this->instruction->id)
            ->line('Product: '.$this->instruction->getProductLabel())
            ->line('Deadline: '.$this->instruction->bottling_deadline->format('Y-m-d'))
            ->line('Default rule applied: '.($this->instruction->default_bottling_rule ?? 'None specified'))
            ->action('View Instruction', url('/admin/procurement/bottling-instructions/'.$this->instruction->id));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bottling_defaults_applied',
            'instruction_id' => $this->instruction->id,
            'product_label' => $this->instruction->getProductLabel(),
            'deadline' => $this->instruction->bottling_deadline->format('Y-m-d'),
            'default_rule' => $this->instruction->default_bottling_rule,
            'message' => 'Bottling defaults applied to '.$this->instruction->getProductLabel().' (deadline: '.$this->instruction->bottling_deadline->format('Y-m-d').')',
        ];
    }
}
