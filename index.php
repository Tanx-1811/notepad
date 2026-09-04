<?php
session_start();

include 'config.php';

$request_uri = trim($_SERVER['REQUEST_URI'], '/');
$parts = explode('/', $request_uri);

$identifier = end($parts);
$identifier = mysqli_real_escape_string($conn, $identifier);

$stmt = mysqli_prepare($conn, "SELECT passwords FROM notes WHERE identifier = ?");
mysqli_stmt_bind_param($stmt, "s", $identifier);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row && !empty($row['passwords'])) {
    if (!isset($_COOKIE['authenticated']) || $_COOKIE['authenticated'] !== $identifier) {
        $_SESSION['identifier'] = $identifier;
        header("Location: http://rionotes.com/login.php/$identifier");
        exit();
    } else {
        // Update cookie to extend its expiration
        setcookie("authenticated", $identifier, time() + (86400 * 30), "/"); // 86400 = 1 day, so this sets the cookie for 30 days

    }
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rio Notes</title>
    <meta name="description" content="A simple and efficient online notepad application.">
    <meta name="keywords" content="rionotes, online notes, note-taking">
    <meta name="author" content="Jet-Tan">

    <link rel="shortcut icon" type="image/x-icon" href="logo.png" />
    <link rel="stylesheet" type="text/css" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            function generateRandomIdentifier() {
                return Math.random().toString(36).substring(2, 10);
            }

            function updateIdentifier(currentIdentifier, newIdentifier) {
                fetch('connect.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'change_url',
                        identifier: currentIdentifier,
                        newIdentifier: newIdentifier
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Server Response:', data);
                        if (data.success) {
                            var updatedIdentifier = newIdentifier || currentIdentifier;
                            history.pushState({}, '', window.location.origin + '/' + updatedIdentifier);
                            window.location.reload();
                        } else {
                            console.error('Error:', data.message);
                            if (data.message === "New identifier already exists.") {
                                alert('The new identifier already exists. Please choose a different one.');
                            }
                            showStatusIcon(false);
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        showStatusIcon(false);
                    });
            }

            function showStatusIcon(success) {
                var iconElement = document.getElementById('status-icon');
                if (success) {
                    iconElement.innerHTML = '<i class="fas fa-check-circle success-icon"></i>';
                } else {
                    iconElement.innerHTML = '<i class="fas fa-times-circle failure-icon" title="Failed to save"></i>';
                }
            }

            function loadContent(identifier) {
                fetch('connect.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'load',
                        identifier: identifier
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Load Content Response:', data);
                        if (data.success) {
                            document.getElementById('contents').value = data.content;
                        } else {
                            console.error('Error:', data.message);
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                    });
            }

            function checkPasswordStatus(identifier) {
                fetch('connect.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'load',
                        identifier: identifier
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Load Password Status Response:', data);
                        if (data.success) {
                            document.querySelector('.add-password').textContent = data.passwords ? 'Remove password' : 'Add password';
                        } else {
                            console.error('Error:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }

            var initialIdentifier = generateRandomIdentifier();
            var currentIdentifier = window.location.pathname.split('/').pop();
            if (!currentIdentifier || currentIdentifier.length === 0) {
                currentIdentifier = initialIdentifier;
                window.history.replaceState({}, '', window.location.origin + '/' + initialIdentifier);
            }
            document.getElementById("edit-url").textContent = window.location.origin + '/' + currentIdentifier;

            loadContent(currentIdentifier);
            checkPasswordStatus(currentIdentifier);

            var textarea = document.getElementById('contents');
            textarea.addEventListener('blur', function () {
                var content = textarea.value.trim();
                fetch('connect.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'update',
                        identifier: currentIdentifier,
                        content: content
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Server Response:', data);
                        showStatusIcon(!!data.success);
                        if (!data.success) {
                            console.error('Error:', data.message);
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        showStatusIcon(false);
                    });
            });

            document.getElementById('update-identifier-form').addEventListener('submit', function (event) {
                event.preventDefault();
                var newIdentifier = document.getElementById('new-identifier-input').value.trim();
                if (newIdentifier && newIdentifier !== currentIdentifier) {
                    updateIdentifier(currentIdentifier, newIdentifier);
                } else {
                    console.error('Invalid input or same identifier.');
                }
            });

            var popover = document.getElementById('popover-content');
            var changeUrlButton = document.getElementById('change-url-button');
            changeUrlButton.addEventListener('click', function () {
                var buttonRect = changeUrlButton.getBoundingClientRect();
                popover.style.top = (buttonRect.bottom + window.scrollY) + 'px';
                popover.style.left = buttonRect.left + 'px';
                popover.style.display = (popover.style.display === 'block' ? 'none' : 'block');
            });
            document.addEventListener('click', function (event) {
                if (!popover.contains(event.target) && event.target !== changeUrlButton) {
                    popover.style.display = 'none';
                }
            });

            document.querySelector('.add-password').addEventListener('click', function () {
                if (this.textContent === 'Add password') {
                    var newPassword = prompt('Enter password:');
                    if (newPassword !== null) {
                        fetch('connect.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'add_password',
                                identifier: currentIdentifier,
                                passwords: newPassword
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                console.log('Add Password Response:', data);
                                if (data.success) {
                                    alert('Password added successfully!');
                                    checkPasswordStatus(currentIdentifier);
                                } else {
                                    console.error('Error:', data.message);
                                    alert('Failed to add password: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while adding password.');
                            });
                    }
                } else if (this.textContent === 'Remove password') {
                    var confirmation = confirm('Are you sure you want to remove the password?');
                    if (confirmation) {
                        fetch('connect.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'remove_password',
                                identifier: currentIdentifier
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                console.log('Remove Password Response:', data);
                                if (data.success) {
                                    alert('Password removed successfully!');
                                    checkPasswordStatus(currentIdentifier);
                                } else {
                                    console.error('Error:', data.message);
                                    alert('Failed to remove password: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while removing password.');
                            });
                    }
                }
            });

            var shareButton = document.getElementById('share-url');
            var shareTooltip = new bootstrap.Tooltip(shareButton);
            shareButton.addEventListener('click', function () {
                var editUrl = document.getElementById('edit-url').textContent;
                var copy = navigator.clipboard && navigator.clipboard.writeText
                    ? navigator.clipboard.writeText(editUrl)
                    : Promise.reject(new Error('Clipboard API unavailable'));

                copy.catch(function () {
                    var tempInput = document.createElement('input');
                    document.body.appendChild(tempInput);
                    tempInput.value = editUrl;
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                }).finally(function () {
                    shareButton.setAttribute('data-bs-original-title', 'Copied!');
                    shareTooltip.show();
                    setTimeout(function () {
                        shareTooltip.hide();
                        shareButton.setAttribute('data-bs-original-title', 'Click to copy');
                    }, 1000);
                });
            });
        });
    </script>

</head>


<body>
    <div class="container">
        <h3>RIONOTES</h3>
        <div class="content">
            <div>
                <a href="/" target="_blank" class="new-note btn btn-primary">New note</a>
                <button class="share-url btn btn-primary ms-2" id="share-url" data-bs-toggle="tooltip"
                    data-bs-placement="top" title="Click to copy">Copy url</button>
                <div hidden>
                    <strong>Edit url:</strong>
                    <span id="edit-url">https://example.com/edit/12345</span>
                </div>

            </div>

            <div class="text-main">
                <textarea id="contents" class="form-control" rows="15" spellcheck="false"></textarea>
                <span id="status-icon" class="status-icon"></span>
            </div>
            <div style="display: flex;">
                <form id="update-identifier-form">
                    <div class="popover" id="popover-content">
                        <input type="text" id="new-identifier-input" class="form-control"
                            placeholder="Enter new identifier">
                        <button type="submit" class="btn btn-primary mt-2">Save</button>
                    </div>
                    <button type="button" id="change-url-button" class="change-url btn btn-warning">Change Url</button>
                </form>
                <?php if ($row && !empty($row['passwords'])) : ?>
                    <div class="add-password btn btn-warning ms-2">Remove password</div>
                <?php else : ?>
                    <div class="add-password btn btn-warning ms-2">Add password</div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="footer-container">
            &copy; <?php echo date('Y'); ?> Rio Notes &mdash; quick, shareable notes.
        </footer>
    </div>

</body>

</html>
