<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .ai-prose h1 { font-size: 1.25rem; font-weight: 700; margin: 0.75rem 0 0.5rem; }
        .ai-prose h2 { font-size: 1.125rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
        .ai-prose h3 { font-size: 1rem; font-weight: 600; margin: 0.5rem 0 0.25rem; }
        .ai-prose p { margin: 0.25rem 0; }
        .ai-prose strong { font-weight: 600; }
        .ai-prose em { font-style: italic; }
        .ai-prose code { font-family: ui-monospace, monospace; font-size: 0.8125rem; background: rgb(243 244 246); padding: 0.125rem 0.375rem; border-radius: 0.25rem; }
        .dark .ai-prose code { background: rgb(55 65 81); }
        .ai-prose pre { margin: 0.5rem 0; border-radius: 0.5rem; overflow-x: auto; }
        .ai-prose pre code { display: block; padding: 0.75rem 1rem; background: rgb(31 41 55); color: rgb(229 231 235); font-size: 0.8125rem; line-height: 1.5; }
        .dark .ai-prose pre code { background: rgb(17 24 39); }
        .ai-prose ul { list-style-type: disc; padding-left: 1.25rem; margin: 0.25rem 0; }
        .ai-prose ol { list-style-type: decimal; padding-left: 1.25rem; margin: 0.25rem 0; }
        .ai-prose li { margin: 0.125rem 0; }
        .ai-prose table { width: 100%; border-collapse: collapse; margin: 0.5rem 0; font-size: 0.8125rem; }
        .ai-prose th { text-align: left; font-weight: 600; padding: 0.5rem 0.75rem; border-bottom: 2px solid rgb(209 213 219); background: rgb(249 250 251); }
        .dark .ai-prose th { border-bottom-color: rgb(75 85 99); background: rgb(31 41 55); }
        .ai-prose td { padding: 0.375rem 0.75rem; border-bottom: 1px solid rgb(229 231 235); }
        .dark .ai-prose td { border-bottom-color: rgb(55 65 81); }
        .ai-prose tr:hover td { background: rgb(249 250 251); }
        .dark .ai-prose tr:hover td { background: rgb(31 41 55); }
        .ai-prose a { color: rgb(79 70 229); text-decoration: underline; }
        .ai-prose a:hover { color: rgb(67 56 202); }
        .dark .ai-prose a { color: rgb(165 180 252); }
        .ai-prose blockquote { border-left: 3px solid rgb(209 213 219); padding-left: 0.75rem; margin: 0.5rem 0; color: rgb(107 114 128); }
        .dark .ai-prose blockquote { border-left-color: rgb(75 85 99); color: rgb(156 163 175); }
    </style>
    <div
        x-data="aiChat()"
        class="flex h-[calc(100vh-12rem)] gap-4"
    >
        {{-- Sidebar: Conversations --}}
        <div class="w-[280px] flex-shrink-0 fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-col">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3">
                <h3 class="fi-section-header-heading text-sm font-semibold">
                    Conversations
                </h3>
            </div>
            <div class="flex-1 overflow-y-auto p-2">
                <template x-if="conversations.length === 0">
                    <p class="text-xs text-gray-400 px-2 py-4 text-center">No conversations yet</p>
                </template>
                <template x-for="conv in conversations" :key="conv.id">
                    <button
                        @click="loadConversation(conv.id)"
                        :class="activeConversationId === conv.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                        class="w-full text-left rounded-lg px-3 py-2 text-sm transition-colors mb-1"
                    >
                        <span x-text="conv.title" class="block truncate text-gray-700 dark:text-gray-300"></span>
                        <span x-text="conv.updated_at" class="block text-xs text-gray-400 mt-0.5"></span>
                    </button>
                </template>
            </div>
            <div class="border-t border-gray-200 dark:border-white/10 p-2">
                <button
                    @click="newConversation()"
                    class="w-full rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors"
                >
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
                <template x-if="sending">
                    <span class="ml-auto text-xs text-gray-400 flex items-center gap-1">
                        <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Thinking...
                    </span>
                </template>
            </div>

            {{-- Messages Area --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="messagesContainer">
                <template x-if="messages.length === 0">
                    <div class="flex justify-center py-12">
                        <div class="text-center">
                            <x-heroicon-o-chat-bubble-left-right class="h-8 w-8 text-gray-300 mx-auto mb-3" />
                            <p class="text-sm text-gray-500">Ask me anything about your ERP data.</p>
                            <p class="text-xs text-gray-400 mt-1">I can help with customers, invoices, allocations, inventory, and more.</p>
                        </div>
                    </div>
                </template>

                <template x-for="(msg, index) in messages" :key="index">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div
                            :class="{
                                'bg-primary-50 dark:bg-primary-900/20 text-gray-900 dark:text-gray-100': msg.role === 'user',
                                'bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10 text-gray-900 dark:text-gray-100': msg.role === 'assistant' && !msg.isError,
                                'bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800 text-red-800 dark:text-red-200': msg.isError
                            }"
                            class="rounded-xl px-4 py-3 max-w-[80%] text-sm leading-relaxed"
                        >
                            <div :class="msg.role === 'assistant' && !msg.isError ? 'ai-prose' : ''" x-html="msg.html || msg.content"></div>
                            <template x-if="msg.showReload">
                                <button
                                    @click="window.location.reload()"
                                    class="mt-2 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700 transition-colors"
                                >
                                    Reload Page
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Typing indicator --}}
                <template x-if="sending && !receivedFirstChunk">
                    <div class="flex justify-start">
                        <div class="bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl px-4 py-3">
                            <div class="flex items-center gap-1">
                                <span class="h-2 w-2 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="h-2 w-2 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="h-2 w-2 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Input Area --}}
            <div class="border-t border-gray-200 dark:border-white/10 p-4">
                <div class="flex gap-2 items-end">
                    <textarea
                        x-model="input"
                        @keydown.enter.prevent="if (!$event.shiftKey) sendMessage()"
                        @input="autoResize($el)"
                        placeholder="Ask anything about Crurated ERP..."
                        :disabled="sending"
                        rows="1"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none resize-none max-h-32 disabled:opacity-50"
                        x-ref="chatInput"
                    ></textarea>
                    <button
                        @click="sendMessage()"
                        :disabled="sending || !input.trim()"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <x-heroicon-o-paper-airplane class="h-4 w-4" />
                        Send
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function aiChat() {
            return {
                messages: [],
                conversations: [],
                activeConversationId: null,
                input: '',
                sending: false,
                receivedFirstChunk: false,
                markedInstance: null,

                init() {
                    if (typeof marked !== 'undefined') {
                        this.markedInstance = new marked.Marked({
                            breaks: true,
                            gfm: true,
                        });
                    }
                },

                renderMarkdown(text) {
                    if (!text || !this.markedInstance) return text || '';
                    try {
                        return this.markedInstance.parse(text);
                    } catch (e) {
                        return text;
                    }
                },

                async sendMessage() {
                    const text = this.input.trim();
                    if (!text || this.sending) return;

                    this.input = '';
                    this.sending = true;
                    this.receivedFirstChunk = false;

                    // Add user message
                    this.messages.push({ role: 'user', content: text });
                    this.scrollToBottom();

                    // Add placeholder for AI response
                    const aiIndex = this.messages.length;
                    this.messages.push({ role: 'assistant', content: '', html: '', isError: false, showReload: false });

                    try {
                        const response = await fetch('/admin/ai/chat', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'Accept': 'text/event-stream',
                            },
                            body: JSON.stringify({
                                message: text,
                                conversation_id: this.activeConversationId,
                            }),
                        });

                        if (!response.ok) {
                            this.receivedFirstChunk = true;
                            const msg = this.messages[aiIndex];
                            msg.isError = true;

                            if (response.status === 419) {
                                msg.content = 'Session expired. Please reload the page.';
                                msg.html = 'Session expired. Please reload the page.';
                                msg.showReload = true;
                            } else if (response.status === 429) {
                                const err = await response.json().catch(() => ({}));
                                msg.content = err.message || 'Rate limit exceeded. Please try again later.';
                                msg.html = msg.content;
                            } else {
                                const err = await response.json().catch(() => ({}));
                                msg.content = err.message || 'Something went wrong. Please try again.';
                                msg.html = msg.content;
                            }

                            this.sending = false;
                            this.scrollToBottom();
                            return;
                        }

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();
                        let buffer = '';

                        while (true) {
                            const { done, value } = await reader.read();
                            if (done) break;

                            buffer += decoder.decode(value, { stream: true });
                            const lines = buffer.split('\n');
                            buffer = lines.pop();

                            for (const line of lines) {
                                if (line.startsWith('data: ')) {
                                    const data = line.slice(6);
                                    if (data === '[DONE]') continue;

                                    try {
                                        const parsed = JSON.parse(data);
                                        if (parsed.content) {
                                            if (!this.receivedFirstChunk) {
                                                this.receivedFirstChunk = true;
                                            }
                                            this.messages[aiIndex].content += parsed.content;
                                            this.messages[aiIndex].html = this.renderMarkdown(this.messages[aiIndex].content);
                                        }
                                        if (parsed.conversation_id) {
                                            this.activeConversationId = parsed.conversation_id;
                                        }
                                    } catch (e) {
                                        // Skip malformed JSON
                                    }
                                }
                            }
                            this.scrollToBottom();
                        }
                    } catch (e) {
                        this.receivedFirstChunk = true;
                        this.messages[aiIndex].content = 'Connection error. Please try again.';
                        this.messages[aiIndex].html = 'Connection error. Please try again.';
                        this.messages[aiIndex].isError = true;
                    }

                    this.sending = false;
                    this.scrollToBottom();
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const container = this.$refs.messagesContainer;
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                        }
                    });
                },

                autoResize(el) {
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 128) + 'px';
                },

                newConversation() {
                    this.messages = [];
                    this.activeConversationId = null;
                    this.input = '';
                    this.$refs.chatInput?.focus();
                },

                loadConversation(id) {
                    this.activeConversationId = id;
                    // Conversation loading will be implemented in US-039
                },
            };
        }
    </script>
</x-filament-panels::page>
