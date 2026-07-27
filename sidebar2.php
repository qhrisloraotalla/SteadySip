<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="functions.js"></script>
    <link rel="icon" type="image/png" href="4031logo.png">
    <link rel="stylesheet" href="sidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>

    <form action="verification.php" method="post">

        <div class="sidebar">
            <a href="homepage.php" class="sidebar-item">
                <img src="emg/homelogo.png" alt="Home">
                <span>Homepage</span>
            </a>
            <a href="checkinventory.php" class="sidebar-item">
                <img src="emg/invlogo.png" alt="Inventory">
                <span>Manage Inventory</span>
            </a>
            <a href="POS2.php" class="sidebar-item">
                <img src="emg/saleslogo.png" alt="Point of Sales">
                <span>Point of Sales</span>
            </a>
            <a href="saleQueueTab.php" class="sidebar-item">
                <img src="emg/saleslogo.png" alt="Sale History">
                <span>Sale History</span>
            </a>
            <a href="menuItems.php" class="sidebar-item">
                <img src="emg/menulogo.png" alt="Menu">
                <span>Menu Item</span>
            </a>
            <a href="auditlog.php" class="sidebar-item">
                <img src="emg/auditlogo.png" alt="Audit">
                <span>Check Audit Log</span>
            </a>
            <a href="logout.php" class="sidebar-item logout">
                <img src="emg/logoutlogo.png" alt="Logout">
                <span>Log Out</span>
            </a>

            <img src="emg/4031logo.png" alt="Forty 31 Logo" class="sidebar-logo">
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
