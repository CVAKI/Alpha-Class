document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const input = document.getElementById('user-input');
    const userText = input.value.trim();
    
    if (userText === "") return;
    
    // Add user message with animation
    addMessage("user", userText);
    
    // Clear input and disable form
    input.value = "";
    disableInput();
    
    // Show typing indicator
    showTypingIndicator();
    
    // Send request to backend
    fetch("../backend/chatbot.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "message=" + encodeURIComponent(userText)
    })
    .then(response => response.text())
    .then(data => {
        // Hide typing indicator and add bot response
        hideTypingIndicator();
        addMessage("bot", data);
        enableInput();
    })
    .catch(error => {
        console.error('Error:', error);
        hideTypingIndicator();
        addMessage("bot", "Sorry, I'm having trouble connecting. Please try again.");
        enableInput();
    });
});

function addMessage(sender, text) {
    const chatBox = document.getElementById("chat-box");
    const msg = document.createElement("div");
    
    msg.classList.add("msg", sender);
    msg.textContent = text;
    
    // Add timestamp
    const time = getCurrentTime();
    msg.setAttribute('data-time', time);
    
    chatBox.appendChild(msg);
    chatBox.scrollTop = chatBox.scrollHeight;
}

function getCurrentTime() {
    return new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function showTypingIndicator() {
    const typingIndicator = document.getElementById('typing-indicator');
    const chatBox = document.getElementById('chat-box');
    
    typingIndicator.style.display = 'block';
    chatBox.appendChild(typingIndicator);
    chatBox.scrollTop = chatBox.scrollHeight;
}

function hideTypingIndicator() {
    const typingIndicator = document.getElementById('typing-indicator');
    typingIndicator.style.display = 'none';
}

function disableInput() {
    const userInput = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');
    
    userInput.disabled = true;
    sendButton.disabled = true;
}

function enableInput() {
    const userInput = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');
    
    userInput.disabled = false;
    sendButton.disabled = false;
    userInput.focus();
}

// Auto-resize input field
document.getElementById('user-input').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Send message on Enter, new line on Shift+Enter
document.getElementById('user-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('chat-form').dispatchEvent(new Event('submit'));
    }
});

// Focus input on load
window.addEventListener('load', function() {
    document.getElementById('user-input').focus();
});

// Remove welcome message after first user message
let isFirstMessage = true;
const originalAddMessage = addMessage;

addMessage = function(sender, text) {
    if (sender === 'user' && isFirstMessage) {
        const welcomeMessage = document.querySelector('.welcome-message');
        if (welcomeMessage) {
            welcomeMessage.style.animation = 'fadeOut 0.3s ease-out forwards';
            setTimeout(() => {
                welcomeMessage.remove();
            }, 300);
        }
        isFirstMessage = false;
    }
    originalAddMessage(sender, text);
};

// Add fadeOut animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-10px);
        }
    }
`;
document.head.appendChild(style);