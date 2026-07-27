<?php
session_start();
include "Procedures.php";

$proc = new Procedures();
$conn = $proc->getConnection();

if (isset($_POST['username']) && isset($_POST['password'])) {

    function validate($data): string {
        return htmlspecialchars(stripslashes(trim($data)));
    }

    $username = validate($_POST['username']);
    $password = validate($_POST['password']);

    if (empty($username)) {
        header("Location: loginpage.php?error=User Name is required");
        exit();
    } else if (empty($password)) {
        header("Location: loginpage.php?error=Password is required");
        exit();
    } else {

        // SQL query
        $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND password=? LIMIT 1");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            // Sessions
            $_SESSION['username'] = $row['username'];
            $_SESSION['name']     = $row['name'];
            $_SESSION['id']       = $row['id'];
            $_SESSION['role']     = $row['role'];

            // Audit log
            $stmtLog = $conn->prepare(
                "INSERT INTO audit_log (user_id, action, personnel, created_at) 
                VALUES (?, 'login', ?, NOW())"
            );
            $stmtLog->bind_param("is", $_SESSION['id'], $_SESSION['name']);
            $stmtLog->execute();
            $stmtLog->close();

            $conn->close();

            // Redirect by role
            if ($row['role'] === 'Cashier') {
                header("Location: cs_POS.php");
            } else {
                header("Location: homepage.php");
            }
            exit();

        } else {
            header("Location: loginpage.php?error=Incorrect username or password");
            exit();
        }
    }

} else {
    header("Location: loginpage.php");
    exit();
}
?>
