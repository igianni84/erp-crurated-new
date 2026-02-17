<?php

namespace App\Exceptions;

use App\Enums\Customer\MembershipStatus;
use Exception;
use Throwable;

/**
 * Exception thrown when an invalid membership status transition is attempted.
 *
 * This exception provides user-friendly error messages that can be displayed
 * directly in the UI to help users understand why a transition failed.
 */
class InvalidMembershipTransitionException extends Exception
{
    /**
     * The current membership status.
     */
    public readonly MembershipStatus $fromStatus;

    /**
     * The target status that was attempted.
     */
    public readonly MembershipStatus $toStatus;

    /**
     * Create a new InvalidMembershipTransitionException.
     */
    public function __construct(
        MembershipStatus $fromStatus,
        MembershipStatus $toStatus,
        ?Throwable $previous = null
    ) {
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;

        $message = $this->buildUserFriendlyMessage();

        parent::__construct($message, 0, $previous);
    }

    /**
     * Build a user-friendly error message explaining why the transition is invalid.
     */
    protected function buildUserFriendlyMessage(): string
    {
        $validTransitions = $this->fromStatus->validTransitions();

        if (empty($validTransitions)) {
            return "Membership in '{$this->fromStatus->label()}' status cannot be changed to any other status.";
        }

        $validLabels = array_map(fn (MembershipStatus $s) => $s->label(), $validTransitions);

        return "Cannot change membership status from '{$this->fromStatus->label()}' to '{$this->toStatus->label()}'. "
            .'Allowed transitions: '.implode(', ', $validLabels).'.';
    }

    /**
     * Get a user-friendly message for display in the UI.
     */
    public function getUserMessage(): string
    {
        return $this->getMessage();
    }
}
