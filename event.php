<?php require('nav.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

</head>
<body>
    <div class="container mt-5">
        <h1 id="eventTitle"><?php echo htmlspecialchars($eventDetails['title']); ?></h1>
        <p id="eventDescription"><?php echo htmlspecialchars($eventDetails['description']); ?></p>
        <h2>Comments</h2>
        <div id="comments">
            <?php foreach ($comments as $comment): ?>
                <p><strong><?php echo htmlspecialchars($comment['username']); ?></strong>: <?php echo htmlspecialchars($comment['comment']); ?></p>
            <?php endforeach; ?>
        </div>
        <form id="commentForm">
            <textarea id="commentText" name="comment" placeholder="Leave a comment" required class="form-control mb-2"></textarea>

            <button type="button" class="btn btn-primary" onclick="addComment()">Add Comment</button>

        </form>
    </div>
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
	    console.log("Attempting to add comment with the following data:");
    console.log("Token:", token);
    console.log("Event ID:", eventId);
    console.log("Comment:", comment);
   
/*       const formData = new FormData();
    formData.append("token", token);
    formData.append("event_id", eventId);
    formData.append("comment", comment); */
    const body = `token=${encodeURIComponent(token)}&event_id=${encodeURIComponent(eventId)}&comment=${encodeURIComponent(comment)}`;

   
    fetch('add_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: body
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"> </script>
</body>
</html>

