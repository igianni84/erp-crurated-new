<x-filament-panels::page>
    <div class="flex h-[calc(100vh-12rem)] gap-4">
        {{-- Sidebar: Conversations --}}
        <div class="w-[280px] flex-shrink-0 fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-col">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3">
                <h3 class="fi-section-header-heading text-sm font-semibold">
                    Conversations
                </h3>
            </div>
            <div class="flex-1 overflow-y-auto p-2">
                <p class="text-xs text-gray-400 px-2 py-4 text-center">No conversations yet</p>
            </div>
            <div class="border-t border-gray-200 dark:border-white/10 p-2">
                <button class="w-full rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors">
                    New Conversation
                </button>
            </div>
        </div>

        {{-- Main Chat Area --}}
        <div class="flex-1 fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-col">
            {{-- Chat Header --}}
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex items-center gap-3">
                <x-heroicon-o-chat-bubble-left-right class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                <h3 class="fi-section-header-heading text-sm font-semibold">
                    ERP Assistant
                </h3>
            </div>

            {{-- Messages Area --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4" id="ai-messages">
                <div class="flex justify-center py-12">
                    <div class="text-center">
                        <x-heroicon-o-chat-bubble-left-right class="h-8 w-8 text-gray-300 mx-auto mb-3" />
                        <p class="text-sm text-gray-500">Ask me anything about your ERP data.</p>
                        <p class="text-xs text-gray-400 mt-1">I can help with customers, invoices, allocations, inventory, and more.</p>
                    </div>
                </div>
            </div>

            {{-- Input Area --}}
            <div class="border-t border-gray-200 dark:border-white/10 p-4">
                <div class="flex gap-2">
                    <input
                        type="text"
                        placeholder="Type your question..."
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none"
                        id="ai-input"
                    />
                    <button
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors flex items-center gap-2"
                        id="ai-send"
                    >
                        <x-heroicon-o-paper-airplane class="h-4 w-4" />
                        Send
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
