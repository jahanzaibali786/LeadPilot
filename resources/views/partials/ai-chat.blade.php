<div class="ai-chat-widget" data-ai-chat data-endpoint="{{ route('ai.chat') }}">
    <section class="ai-chat-panel" data-chat-panel hidden aria-label="LeadPilot AI Guide">
        <header class="ai-chat-head">
            <div class="ai-chat-title">
                <span class="ai-chat-mark"><i class="bi bi-stars"></i></span>
                <div><strong>LeadPilot Guide</strong><small>System help and guidance</small></div>
            </div>
            <div class="d-flex gap-1">
                <button type="button" data-chat-clear title="Clear chat" aria-label="Clear chat"><i class="bi bi-trash3"></i></button>
                <button type="button" data-chat-close title="Close assistant" aria-label="Close assistant"><i class="bi bi-x-lg"></i></button>
            </div>
        </header>
        <div class="ai-chat-messages" data-chat-messages aria-live="polite"></div>
        <form class="ai-chat-form" data-chat-form>
            <label class="visually-hidden" for="ai-chat-input">Ask about LeadPilot</label>
            <textarea id="ai-chat-input" data-chat-input rows="2" maxlength="1500" placeholder="Ask how LeadPilot works..." required></textarea>
            <button class="ai-chat-send" type="submit" aria-label="Send message" title="Send message"><i class="bi bi-send-fill"></i></button>
        </form>
        <p class="ai-chat-note">Guidance only. The assistant does not change your data.</p>
    </section>
    <button class="ai-chat-toggle" type="button" data-chat-toggle aria-expanded="false" title="Ask LeadPilot Guide">
        <span class="ai-chat-toggle-icon"><i class="bi bi-stars"></i></span>
        <span class="ai-chat-toggle-copy"><strong>Ask AI</strong><small>LeadPilot Guide</small></span>
    </button>
</div>
