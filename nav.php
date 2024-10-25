<!-- nav.php -->
<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">EventPulse</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="#">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Saved Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="friends.php">Friends</a>
                </li>
            </ul>
            <!-- Only Logout Button -->
            <div class="d-flex align-items-center">
                <form class="d-flex" role="button">
                    <button class="btn btn-outline-danger" type="button" onclick="logout()">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<script>
function logout() {
    const token = localStorage.getItem("token");
    if (token) {
        fetch('logout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'token=' + encodeURIComponent(token),
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                localStorage.removeItem("token");
                window.location.href = "index.html";
            } else {
                console.error('Logout failed:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    } else {
        window.location.href = "index.html";
    }
}

window.onload = function() {
    const token = localStorage.getItem("token");
    if (!token) {
        window.location.href = "index.html";
    } else {
        fetch('home.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'token=' + encodeURIComponent(token),
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "fail") {
                document.getElementById("content").innerHTML = "<h1>Error: " + data.message + "</h1>";
            } else {
                // no welcome message displayed in the navbar
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.body.innerHTML = "<h1>Error: unable to verify token<h1>";
        });
    }
}
</script>

