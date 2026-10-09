<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Acme support chat</title>
</head>
<body>
    <p><input id="token" placeholder="API token" size="40"></p>
    <p><input id="message" placeholder="Ask about your orders"
              size="40"> <button id="send">Send</button></p>
    <pre id="reply"></pre>

    <script>
    let conversationId = null;

    document.getElementById('send').onclick = async () => {
        const reply = document.getElementById('reply');
        reply.textContent = '';

        const response = await fetch('/api/support/chat/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'Authorization': 'Bearer '
                    + document.getElementById('token').value,
            },
            body: JSON.stringify({
                message: document.getElementById('message').value,
                conversation_id: conversationId,
            }),
        });

        if (!response.ok) {
            reply.textContent = 'Error ' + response.status;
            return;
        }

        conversationId = response.headers.get('X-Conversation-Id');

        const reader = response.body
            .pipeThrough(new TextDecoderStream())
            .getReader();
        let buffer = '';

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += value;

            // Server-sent events end with a blank line.
            const events = buffer.split('\n\n');
            buffer = events.pop();

            for (const event of events) {
                const data = event.replace(/^data: /, '');
                if (data === '[DONE]') continue;

                const payload = JSON.parse(data);
                if (payload.type === 'text_delta') {
                    reply.textContent += payload.delta;
                }
                if (payload.type === 'error') {
                    reply.textContent += '\n[stream failed]';
                }
            }
        }
    };
    </script>
</body>
</html>
