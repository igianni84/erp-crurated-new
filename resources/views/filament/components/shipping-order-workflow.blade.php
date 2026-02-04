@php
    use App\Enums\Fulfillment\ShippingOrderStatus;

    $record = $getRecord();
    $currentStatus = $record->status;

    // Define workflow steps (main happy path)
    $workflowSteps = [
        ShippingOrderStatus::Draft,
        ShippingOrderStatus::Planned,
        ShippingOrderStatus::Picking,
        ShippingOrderStatus::Shipped,
        ShippingOrderStatus::Completed,
    ];

    // Map status to step index (for completed/current determination)
    $statusToIndex = [
        ShippingOrderStatus::Draft->value => 0,
        ShippingOrderStatus::Planned->value => 1,
        ShippingOrderStatus::Picking->value => 2,
        ShippingOrderStatus::Shipped->value => 3,
        ShippingOrderStatus::Completed->value => 4,
    ];

    $currentIndex = $statusToIndex[$currentStatus->value] ?? -1;
    $isOnHold = $currentStatus === ShippingOrderStatus::OnHold;
    $isCancelled = $currentStatus === ShippingOrderStatus::Cancelled;

    // For OnHold, check previous_status to determine where in workflow we were
    if ($isOnHold && $record->previous_status !== null) {
        $currentIndex = $statusToIndex[$record->previous_status->value] ?? 0;
    }
@endphp

<div class="relative">
    {{-- On Hold or Cancelled Overlay --}}
    @if($isOnHold)
        <div class="absolute inset-0 bg-danger-100/80 dark:bg-danger-900/50 rounded-lg flex items-center justify-center z-10">
            <div class="flex items-center gap-2 text-danger-700 dark:text-danger-300 font-semibold">
                <x-heroicon-o-pause-circle class="h-6 w-6" />
                <span>ON HOLD</span>
            </div>
        </div>
    @elseif($isCancelled)
        <div class="absolute inset-0 bg-gray-100/80 dark:bg-gray-900/50 rounded-lg flex items-center justify-center z-10">
            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400 font-semibold">
                <x-heroicon-o-x-circle class="h-6 w-6" />
                <span>CANCELLED</span>
            </div>
        </div>
    @endif

    {{-- Workflow Stepper --}}
    <div class="flex items-center justify-between py-4 px-2 {{ ($isOnHold || $isCancelled) ? 'opacity-40' : '' }}">
        @foreach($workflowSteps as $index => $step)
            @php
                $isCompleted = $index < $currentIndex;
                $isCurrent = $index === $currentIndex && !$isCancelled;
                $isFuture = $index > $currentIndex;

                // Determine colors
                if ($isCompleted) {
                    $circleClass = 'bg-success-500 border-success-500 text-white';
                    $lineClass = 'bg-success-500';
                    $labelClass = 'text-success-700 dark:text-success-400';
                } elseif ($isCurrent) {
                    $circleClass = 'bg-info-500 border-info-500 text-white';
                    $lineClass = 'bg-gray-200 dark:bg-gray-700';
                    $labelClass = 'text-info-700 dark:text-info-400 font-semibold';
                } else {
                    $circleClass = 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500';
                    $lineClass = 'bg-gray-200 dark:bg-gray-700';
                    $labelClass = 'text-gray-500 dark:text-gray-500';
                }
            @endphp

            <div class="flex flex-col items-center relative {{ $index < count($workflowSteps) - 1 ? 'flex-1' : '' }}">
                {{-- Step Circle --}}
                <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{ $circleClass }} z-10">
                    @if($isCompleted)
                        <x-heroicon-s-check class="h-5 w-5" />
                    @else
                        <span class="text-sm font-medium">{{ $index + 1 }}</span>
                    @endif
                </div>

                {{-- Step Label --}}
                <span class="mt-2 text-xs {{ $labelClass }} text-center whitespace-nowrap">
                    {{ $step->label() }}
                </span>

                {{-- Connecting Line (not for last item) --}}
                @if($index < count($workflowSteps) - 1)
                    <div class="absolute top-5 left-1/2 w-full h-0.5 {{ $isCompleted ? 'bg-success-500' : 'bg-gray-200 dark:bg-gray-700' }}" style="transform: translateX(50%);"></div>
                @endif
            </div>
        @endforeach
    </div>
</div>
