<?php
require_once 'header.php';
?>
<h1>Welcome, <?php echo $_SESSION['username']; ?>!</h1>
<div class="card-container">
    <?php if ($role == 'employee') { ?>
        <a href="timesheet.php" class="card">Submit Timesheet</a>
    <?php } else { ?><?php
        require_once 'dashboard.php';?>
        <?php } ?>
    <!-- <a href="logout.php" class="card logout">Logout</a> -->
</div>
<?php
require_once 'footer.php';
?>
