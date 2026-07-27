<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: loginpage.php");
    exit();
}

include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();

$proc->updateSuppliesCurrentStock($conn);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="functions.js"></script>
    <link rel="icon" type="image/png" href="4031logo.png">
    <link rel="stylesheet" href="cs_sidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>

    <form action="verification.php" method="post">

    <div class="sidebar">

        <div class="sidebar-header">
            <div class="sidebar-name"><?php echo $_SESSION['name']; ?></div>

        </div>

        <div class="sidebar-menu">
            <a href="cs_POS.php" class="sidebar-item">
                <img src="emg/pos.png">
                <span>Point of Sales</span>
            </a>

            <a href="cs_saleQueueTab.php" class="sidebar-item">
                <img src="emg/saleslogo.png">
                <span>Sale History</span>
            </a>

            <a href="logout.php" class="sidebar-item">
                <img src="emg/logoutlogo.png    ">
                <span>Log Out</span>
            </a>
        </div>

        <div class ="sidebar-footer">
            <img src="emg/4031logo.png" class="sidebar-logo">
        </div>
    </div>



    </form>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">

    const sidebar = document.querySelector('.sidebar');

    function toggleSidebar() {
        sidebar.classList.toggle('minimized');
    }

    // Example: trigger on some button click
    // document.getElementById('toggle-btn').addEventListener('click', toggleSidebar);


    </script>
</body>
</html>
