(() => {
    const widget = document.querySelector('[data-ai-chat]');
    if (!widget) return;

    const toggle = widget.querySelector('[data-chat-toggle]');
    const panel = widget.querySelector('[data-chat-panel]');
    const close = widget.querySelector('[data-chat-close]');
    const clear = widget.querySelector('[data-chat-clear]');
    const form = widget.querySelector('[data-chat-form]');
    const input = widget.querySelector('[data-chat-input]');
    const messages = widget.querySelector('[data-chat-messages]');
    const submit = form.querySelector('button[type="submit"]');
    const storageKey = 'leadpilot-ai-chat-history';
    let history = [];
    const trimMessage = (message, max = 1200) => String(message || '').length > max ? `${String(message || '').slice(0, max)}...` : String(message || '');
    const sanitizeHistory = (items) => items.slice(-8).map((message) => ({role: message.role, content: trimMessage(message.content)}));

    const loadHistory = () => {
        try {
            const saved = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
            history = Array.isArray(saved) ? sanitizeHistory(saved) : [];
        } catch {
            history = [];
        }
    };

    const saveHistory = () => sessionStorage.setItem(storageKey, JSON.stringify(sanitizeHistory(history)));

    const addMessage = (role, content, persist = true) => {
        const bubble = document.createElement('div');
        bubble.className = `ai-chat-message ${role}`;
        bubble.textContent = content;
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
        if (persist) {
            history.push({role, content: trimMessage(content)});
            history = sanitizeHistory(history);
            saveHistory();
        }
        return bubble;
    };

    const render = () => {
        messages.innerHTML = '';
        if (!history.length) {
            addMessage('assistant', 'Hi! I can explain LeadPilot and guide you through campaigns, leads, the pipeline, settings, and AI tools.', false);
        } else {
            history.forEach((message) => addMessage(message.role, message.content, false));
        }
    };

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) {
            input.focus();
            messages.scrollTop = messages.scrollHeight;
        }
    };

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false));
    clear.addEventListener('click', () => {
        history = [];
        sessionStorage.removeItem(storageKey);
        render();
        input.focus();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = input.value.trim();
        if (!question || submit.disabled) return;

        const requestHistory = sanitizeHistory(history);
        addMessage('user', question);
        input.value = '';
        submit.disabled = true;
        input.disabled = true;
        const typing = addMessage('assistant', 'Thinking...', false);
        typing.classList.add('typing');

        try {
            const response = await fetch(widget.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    message: question,
                    history: requestHistory,
                    current_page: `${document.title} (${location.pathname})`,
                }),
            });
            const data = await response.json();
            typing.remove();
            if (!response.ok) throw new Error(data.message || 'The assistant is unavailable right now.');
            addMessage('assistant', data.message);
        } catch (error) {
            typing.remove();
            addMessage('assistant', error.message || 'The assistant is unavailable right now.', false);
        } finally {
            submit.disabled = false;
            input.disabled = false;
            input.focus();
        }
    });

    loadHistory();
    render();
})();
