<?php
/* Template Name: Chat Template */
get_header(); ?>

<div id="chat-box">
	<div id="messages"></div>
	<label for="message-input"></label><input type="text" id="message-input" placeholder="Type a message...">
	<button id="send-btn">Send</button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const database = firebase.database();
        const messagesRef = database.ref('messages');

        document.getElementById('send-btn').addEventListener('click', function () {
            const message = document.getElementById('message-input').value;
            if (message) {
                messagesRef.push().set({
                    message: message,
                    timestamp: Date.now()
                });
                document.getElementById('message-input').value = '';
            }
        });

        messagesRef.on('child_added', function (snapshot) {
            const message = snapshot.val().message;
            const messageElement = document.createElement('div');
            messageElement.textContent = message;
            document.getElementById('messages').appendChild(messageElement);
        });
    });
</script>

<?php get_footer(); ?>
