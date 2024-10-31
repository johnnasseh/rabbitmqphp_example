<?php require('nav.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Details</title>
</head>
<body>
    <h1><?php echo htmlspecialchars($eventDetails['title']); ?></h1>
    <p><?php echo htmlspecialchars($eventDetails['description']); ?></p>
    <h2>Comments</h2>
    <div id="comments">
        <?php foreach ($comments as $comment): ?>
            <p><strong><?php echo htmlspecialchars($comment['username']); ?></strong>: <?php echo htmlspecialchars($comment['comment']); ?></p>
        <?php endforeach; ?>
    </div>
    <form id="commentForm">
        <textarea id="commentText" placeholder="Leave a comment"></textarea>
        <button type="button" onclick="addComment()">Add Comment</button>
    </form>

<script>
        window.onload = function() {
            const token = localStorage.getItem("token");
            const eventId = new URLSearchParams(window.location.search).get("event_id");
	    if (!token) {
                alert("You must be logged in to view this page.");
                window.location.href = "index.html";
                return;
	    }

	    if (!eventId) {
		    alert("Event ID is missing.");
		    window.location.href = "likes.html";
		    return;
	    }

            fetchEventDetails(token, eventId);
        };

        function fetchEventDetails(token, eventId) {
            fetch('event_sender.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `token=${encodeURIComponent(token)}&event_id=${encodeURIComponent(eventId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success" && data.event) {
		    displayEventDetails(data.event);
		    displayComments(data.comments);
                } else {
                    alert("Failed to load event details: " + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
	}

	function displayEventDetails(event) {
    document.getElementById("eventTitle").innerText = event.title;
    document.getElementById("eventDescription").innerText = event.description;
}

        function displayComments(comments) {
            const commentsDiv = document.getElementById("comments");
            commentsDiv.innerHTML = '';
            comments.forEach(comment => {
                commentsDiv.innerHTML += `<p><strong>${comment.username}</strong>: ${comment.comment}</p>`;
            });
        }

    function addComment() {
        const token = localStorage.getItem("token");
        const eventId = new URLSearchParams(window.location.search).get("event_id");
        const comment = document.getElementById("commentText").value;
        fetch('add_comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `token=${encodeURIComponent(token)}&event_id=${encodeURIComponent(eventId)}&comment=${encodeURIComponent(comment)}`
	})
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                location.reload();
            } else {
                alert("Failed to add comment: " + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    } 
  </script>
</body>
</html>

