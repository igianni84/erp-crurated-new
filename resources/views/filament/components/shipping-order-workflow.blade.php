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
    <div class="py-4 px-4 {{ ($isOnHold || $isCancelled) ? 'opacity-40' : '' }}">
        {{-- Circles + Lines row (vertically centered) --}}
        <div class="flex items-center">
            @foreach($workflowSteps as $index => $step)
                @php
                    $isCompleted = $index < $currentIndex;
                    $isCurrent = $index === $currentIndex && !$isCancelled;

                    if ($isCompleted) {
                        $circleClass = 'bg-success-500 border-success-500 text-white';
                    } elseif ($isCurrent) {
                        $circleClass = 'bg-info-500 border-info-500 text-white';
                    } else {
                        $circleClass = 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500';
                    }
                @endphp

                @if($index > 0)
                    <div class="flex-1 h-0.5 {{ ($index <= $currentIndex) ? 'bg-success-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                @endif

                <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 shrink-0 {{ $circleClass }}">
                    @if($isCompleted)
                        <x-heroicon-s-check class="h-5 w-5" />
                    @else
                        <span class="text-sm font-medium">{{ $index + 1 }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Labels row --}}
        <div class="flex items-start mt-2">
            @foreach($workflowSteps as $index => $step)
                @php
                    $isCompleted = $index < $currentIndex;
                    $isCurrent = $index === $currentIndex && !$isCancelled;

                    if ($isCompleted) {
                        $labelClass = 'text-success-700 dark:text-success-400';
                    } elseif ($isCurrent) {
                        $labelClass = 'text-info-700 dark:text-info-400 font-semibold';
                    } else {
                        $labelClass = 'text-gray-500 dark:text-gray-500';
                    }
                @endphp

                @if($index > 0)
                    <div class="flex-1"></div>
                @endif

                {{-- Fixed-width wrapper matching circle (w-10), label overflows centered --}}
                <div class="relative shrink-0 w-10 flex justify-center">
                    <span class="absolute text-xs {{ $labelClass }} whitespace-nowrap">
                        {{ $step->label() }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</div>
