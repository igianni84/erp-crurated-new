<div x-data="aiChatSlideOver()">
    {{-- Chat Icon Button --}}
    <button
        @click="toggle()"
        class="relative flex items-center justify-center rounded-full p-2 text-gray-400 hover:text-primary-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
        title="AI Assistant"
    >
        <x-heroicon-o-chat-bubble-left-right class="h-5 w-5" />
    </button>

    {{-- Slide-over Panel --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed inset-y-0 right-0 z-50 w-[420px] max-w-full bg-white dark:bg-gray-900 shadow-2xl flex flex-col ring-1 ring-gray-950/5 dark:ring-white/10"
            @keydown.escape.window="open = false"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-white/10">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-chat-bubble-left-right class="h-5 w-5 text-primary-600" />
                    <span class="text-sm font-semibold">AI Assistant</span>
                </div>
                <div class="flex items-center gap-1">
                    <a
                        :href="conversationId ? '/admin/ai-assistant?conversation=' + conversationId : '/admin/ai-assistant'"
                        class="rounded p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        title="Open full view"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                    <button @click="open = false" class="rounded p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="slideMessages">
                <template x-if="messages.length === 0">
                    <div class="text-center py-8">
                        <p class="text-sm text-gray-500">Ask me anything about ERP data.</p>
                    </div>
                </template>
                <template x-for="(msg, i) in messages" :key="i">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div
                            :class="{
                                'bg-primary-50 dark:bg-primary-900/20': msg.role === 'user',
                                'bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10': msg.role === 'assistant' && !msg.isError,
                                'bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800 text-red-800 dark:text-red-200': msg.isError
                            }"
                            class="rounded-xl px-3 py-2 max-w-[85%] text-sm leading-relaxed"
                        >
                            <div :class="msg.role === 'assistant' && !msg.isError ? 'ai-prose' : ''" x-html="msg.html || msg.content"></div>
                        </div>
                    </div>
                </template>
                <template x-if="sending && !receivedFirstChunk">
                    <div class="flex justify-start">
                        <div class="bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl px-3 py-2">
                            <div class="flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Input --}}
            <div class="border-t border-gray-200 dark:border-white/10 p-3">
                <div class="flex gap-2 items-end">
                    <textarea
                        x-model="input"
                        @keydown.enter.prevent="if (!$event.shiftKey) sendMessage()"
                        placeholder="Ask anything..."
                        :disabled="sending"
                        rows="1"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none resize-none max-h-24 disabled:opacity-50"
                    ></textarea>
                    <button
                        @click="sendMessage()"
                        :disabled="sending || !input.trim()"
                        class="rounded-lg bg-primary-600 p-2 text-white hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Backdrop --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="open = false"
            class="fixed inset-0 z-40 bg-black/20"
        ></div>
    </template>
</div>

<script>
    function aiChatSlideOver() {
        return {
            open: false,
            messages: [],
            conversationId: null,
            input: '',
            sending: false,
            receivedFirstChunk: false,
            markedInstance: null,

            init() {
                if (typeof marked !== 'undefined') {
                    this.markedInstance = new marked.Marked({ breaks: true, gfm: true });
                }
                const saved = localStorage.getItem('ai_chat_state');
                if (saved) {
                    try {
                        const data = JSON.parse(saved);
                        this.messages = data.messages || [];
                        this.conversationId = data.conversationId || null;
                    } catch (e) {}
                }
            },

            renderMarkdown(text) {
                if (!text || !this.markedInstance) return text || '';
                try { return this.markedInstance.parse(text); } catch (e) { return text; }
            },

            csrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.content || '';
            },

            persistState() {
                try {
                    localStorage.setItem('ai_chat_state', JSON.stringify({
                        messages: this.messages.slice(-20),
                        conversationId: this.conversationId,
                    }));
                } catch (e) {}
            },

            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.$nextTick(() => this.scrollToBottom());
                }
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.slideMessages;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },

            async sendMessage() {
                const text = this.input.trim();
                if (!text || this.sending) return;

                this.input = '';
                this.sending = true;
                this.receivedFirstChunk = false;

                this.messages.push({ role: 'user', content: text });
                this.scrollToBottom();

                const aiIndex = this.messages.length;
                this.messages.push({ role: 'assistant', content: '', html: '', isError: false });

                try {
                    const response = await fetch('/admin/ai/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken(),
                            'Accept': 'text/event-stream',
                        },
                        body: JSON.stringify({
                            message: text,
                            conversation_id: this.conversationId,
                        }),
                    });

                    if (!response.ok) {
                        this.receivedFirstChunk = true;
                        this.messages[aiIndex].isError = true;
                        if (response.status === 419) {
                            this.messages[aiIndex].content = 'Session expired. Please reload.';
                            this.messages[aiIndex].html = this.messages[aiIndex].content;
                        } else {
                            const err = await response.json().catch(() => ({}));
                            this.messages[aiIndex].content = err.message || 'Something went wrong.';
                            this.messages[aiIndex].html = this.messages[aiIndex].content;
                        }
                        this.sending = false;
                        this.persistState();
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
                                        if (!this.receivedFirstChunk) this.receivedFirstChunk = true;
                                        this.messages[aiIndex].content += parsed.content;
                                        this.messages[aiIndex].html = this.renderMarkdown(this.messages[aiIndex].content);
                                    }
                                    if (parsed.conversation_id) {
                                        this.conversationId = parsed.conversation_id;
                                    }
                                } catch (e) {}
                            }
                        }
                        this.scrollToBottom();
                    }
                } catch (e) {
                    this.receivedFirstChunk = true;
                    this.messages[aiIndex].content = 'Connection error.';
                    this.messages[aiIndex].html = 'Connection error.';
                    this.messages[aiIndex].isError = true;
                }

                this.sending = false;
                this.scrollToBottom();
                this.persistState();
            },
        };
    }
</script>
