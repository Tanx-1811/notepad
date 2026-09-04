<?php
session_start();
include 'config.php';
header('Content-Type: application/json');
$postData = file_get_contents('php://input');
$data = json_decode($postData, true);

function isValidIdentifier($identifier)
{
    return is_string($identifier) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $identifier) === 1;
}

function identifierExists($conn, $identifier)
{
    $stmt = mysqli_prepare($conn, "SELECT 1 FROM notes WHERE identifier = ?");
    mysqli_stmt_bind_param($stmt, "s", $identifier);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

if (isset($data['action'])) {
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    $date_time = date('Y-m-d H:i:s');
    $time = time();

    if ($data['action'] === 'load' && isset($data['identifier'])) {
        $identifier = $data['identifier'];

        $stmt = mysqli_prepare($conn, "SELECT content, created_at, time_create, passwords FROM notes WHERE identifier = ?");
        mysqli_stmt_bind_param($stmt, "s", $identifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row) {
            echo json_encode(array("success" => true, "content" => html_entity_decode($row['content']), "created_at" => $row['created_at'], "time_create" => $row['time_create'], "has_password" => !empty($row['passwords'])));
        } else {
            echo json_encode(array("success" => false, "message" => "No content found for the given identifier."));
        }
    } elseif ($data['action'] === 'update' && isset($data['identifier'])) {
        $currentIdentifier = $data['identifier'];
        $content = isset($data['content']) ? $data['content'] : '';
        $newIdentifier = isset($data['newIdentifier']) ? $data['newIdentifier'] : $currentIdentifier;

        if (empty($content)) {
            echo json_encode(array("success" => false, "message" => "Content cannot be empty."));
            exit;
        }

        $max_content_length = 200000;
        if (mb_strlen($content) > $max_content_length) {
            echo json_encode(array("success" => false, "message" => "Content exceeds the maximum length of $max_content_length characters."));
            exit;
        }

        if ($currentIdentifier === '') {
            echo json_encode(array("success" => false, "message" => "Current identifier is empty. Cannot update."));
            exit;
        }

        if (!isValidIdentifier($newIdentifier)) {
            echo json_encode(array("success" => false, "message" => "Identifier may only contain letters, numbers, hyphens, and underscores (max 64 characters)."));
            exit;
        }

        if ($newIdentifier !== $currentIdentifier && identifierExists($conn, $newIdentifier)) {
            echo json_encode(array("success" => false, "message" => "New identifier already exists."));
            exit;
        }

        $content = htmlentities($content);

        if (identifierExists($conn, $currentIdentifier)) {
            $stmt = mysqli_prepare($conn, "UPDATE notes SET content = ?, identifier = ?, created_at = ? WHERE identifier = ?");
            mysqli_stmt_bind_param($stmt, "ssss", $content, $newIdentifier, $date_time, $currentIdentifier);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO notes (identifier, content, created_at, time_create) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssss", $newIdentifier, $content, $date_time, $time);
        }

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(array("success" => true, "identifier" => $newIdentifier));
        } else {
            echo json_encode(array("success" => false, "message" => "Failed to save note: " . mysqli_stmt_error($stmt)));
        }
        mysqli_stmt_close($stmt);
    } elseif ($data['action'] === 'add_password' && isset($data['identifier']) && isset($data['passwords'])) {
        $identifier = $data['identifier'];
        $passwords = $data['passwords'];

        if (empty($passwords)) {
            echo json_encode(array("success" => false, "message" => "Password cannot be empty."));
            exit;
        }

        $hashed_password = password_hash($passwords, PASSWORD_DEFAULT);

        if (identifierExists($conn, $identifier)) {
            $stmt = mysqli_prepare($conn, "UPDATE notes SET passwords = ?, created_at = ? WHERE identifier = ?");
            mysqli_stmt_bind_param($stmt, "sss", $hashed_password, $date_time, $identifier);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO notes (identifier, passwords, created_at, time_create) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssss", $identifier, $hashed_password, $date_time, $time);
        }

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(array("success" => true));
        } else {
            echo json_encode(array("success" => false, "message" => "Failed to add password: " . mysqli_stmt_error($stmt)));
        }
        mysqli_stmt_close($stmt);
    } elseif ($data['action'] === 'remove_password' && isset($data['identifier'])) {
        $identifier = $data['identifier'];

        $stmt = mysqli_prepare($conn, "UPDATE notes SET passwords = NULL WHERE identifier = ?");
        mysqli_stmt_bind_param($stmt, "s", $identifier);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(array("success" => true));
        } else {
            echo json_encode(array("success" => false, "message" => "Failed to remove password: " . mysqli_stmt_error($stmt)));
        }
        mysqli_stmt_close($stmt);
    } elseif ($data['action'] === 'change_url' && isset($data['identifier']) && isset($data['newIdentifier'])) {
        $currentIdentifier = $data['identifier'];
        $newIdentifier = $data['newIdentifier'];

        if ($newIdentifier === '') {
            echo json_encode(array("success" => false, "message" => "New identifier cannot be empty."));
            exit;
        }

        if (!isValidIdentifier($newIdentifier)) {
            echo json_encode(array("success" => false, "message" => "Identifier may only contain letters, numbers, hyphens, and underscores (max 64 characters)."));
            exit;
        }

        if ($newIdentifier !== $currentIdentifier && identifierExists($conn, $newIdentifier)) {
            echo json_encode(array("success" => false, "message" => "New identifier already exists."));
            exit;
        }

        $stmt = mysqli_prepare($conn, "UPDATE notes SET identifier = ? WHERE identifier = ?");
        mysqli_stmt_bind_param($stmt, "ss", $newIdentifier, $currentIdentifier);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(array("success" => true, "identifier" => $newIdentifier));
        } else {
            echo json_encode(array("success" => false, "message" => "Failed to change identifier: " . mysqli_stmt_error($stmt)));
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(array("success" => false, "message" => "Invalid action or missing parameters."));
    }
} else {
    echo json_encode(array("success" => false, "message" => "Action not specified."));
}

mysqli_close($conn);
