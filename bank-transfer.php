<?php
session_start();
require 'db.php';

// Check if the user is an admin
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// =================================================================================
// ADD YOUR COMPANY'S DEBIT ACCOUNT NUMBER HERE
// =================================================================================
$debitAccount = '123456789012'; // !! IMPORTANT: CHANGE THIS to your company's account number

// --- HANDLE CSV EXPORT FIRST ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['export_csv'])) {
    try {
        $report_month = $_POST['report_month'];
        $report_year = $_POST['report_year'];

        // NOTE: Please verify these column names (Address1, etc.) match your database table.
        $stmt = $conn->prepare(
           "SELECT 
                e.Name, e.bankAccNumber, e.IFSC_code, s.EMPID, s.NET_Payable,
                e.permanentaddress, e.temporaryaddress
             FROM SalaryCal_Table s
             JOIN EmployeeBasicDetails e ON s.EMPID = e.EMPID
             WHERE s.Shift_Month = ? AND s.shift_year = ?
             ORDER BY e.Name ASC"
        );
        $stmt->bind_param("ii", $report_month, $report_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report_data = [];
        while($row = $result->fetch_assoc()){
            $report_data[] = $row;
        }

        if (!empty($report_data)) {
            $filename = "bank_transfer_" . $report_year . "_" . str_pad($report_month, 2, '0', STR_PAD_LEFT) . ".csv";
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
             fputcsv($output, [
                'S.N o.', 'Debit Account No.', 'Amount', 'Beneficiary IFSC', 'Transaction', 
                'Beneficiary Account', 'Beneficiary Name', 'Beneficiary Address 1', 
                'Beneficiary Address 2','Payment Details 1', 
                'Payment Details 2', 'Charge Debit Account No.', 'SMSEmail', 'Mobile no / Email ID'
            ]);
            
            $serialNumber = 1;
            $paymentDetails1 = "SAL " . strtoupper(date('F', mktime(0, 0, 0, $report_month, 1))) . " " . $report_year;

            foreach ($report_data as $row) {
                fputcsv($output, [
                    $serialNumber++,                  // S.N o.
                    $debitAccount,                    // Debit Account No.
                    $row['NET_Payable'],              // Amount
                    $row['IFSC_code'],                // Beneficiary IFSC
                    'NEFT',                           // Transaction (Static Value)
                    $row['bankAccNumber'],            // Beneficiary Account
                    $row['Name'],                     // Beneficiary Name
                    $row['permanentaddress'] ?? '',           // Beneficiary Address 1
                    $row['temporaryaddress'] ?? '',           // Beneficiary Address 2
                    $paymentDetails1,                 // Payment Details 1
                    '',                               // Payment Details 2 (Empty)
                    $debitAccount,                    // Charge Debit Account No.
                    '',                               // SMSEmail (Empty as requested)
                    ''                                // Mobile no / Email ID (Empty as requested)
                ]);
            }
            
            fclose($output);
            exit(); 
        } else {
            header("Location: bank_transfer.php?error=No data to export for the selected period.");
            exit();
        }

    } catch (Exception $e) {
        header("Location: bank_transfer.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}


// --- REGULAR PAGE LOGIC (VIEW REPORT) ---
require_once 'header.php'; 

$report_data = [];
$message = '';
$is_error = false;
$form_data = $_POST;

if(isset($_GET['error'])) {
    $message = $_GET['error'];
    $is_error = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['view_report'])) {
    try {
        $report_month = $_POST['report_month'];
        $report_year = $_POST['report_year'];

        // CHANGED: The query now fetches Address1 for the on-screen view.
        $stmt = $conn->prepare(
            "SELECT 
                e.Name, e.bankAccNumber, e.IFSC_code, s.EMPID, s.NET_Payable, e.permanentaddress
             FROM SalaryCal_Table s
             JOIN EmployeeBasicDetails e ON s.EMPID = e.EMPID
             WHERE s.Shift_Month = ? AND s.shift_year = ?
             ORDER BY e.Name ASC"
        );
        $stmt->bind_param("ii", $report_month, $report_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()){
            $report_data[] = $row;
        }

        if (empty($report_data)) {
            $message = "No records found for the selected period.";
            $is_error = true;
        }

    } catch (Exception $e) {
        $message = "An error occurred: " . $e->getMessage();
        $is_error = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Bank Transfer File</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Bank Transfer Report</h2>
        
        <?php if ($message): ?>
            <p class="message <?php if ($is_error) echo 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php endif; ?>

        <form action="" method="post" class="payroll-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="report_month">Select Month:</label>
                    <select name="report_month" id="report_month" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo (isset($form_data['report_month']) && $form_data['report_month'] == $m) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="report_year">Select Year:</label>
                    <select name="report_year" id="report_year" required>
                        <?php 
                        $current_year = date('Y');
                        for ($y = $current_year + 1; $y >= $current_year - 5; $y--): 
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo (isset($form_data['report_year']) && $form_data['report_year'] == $y) ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="actions-container">
                <button type="submit" name="view_report">View Report</button>
                <button type="submit" name="export_csv">Export to Excel</button>
            </div>
        </form>

        <?php if (!empty($report_data)): ?>
            <div class="report-table-container">
                <h3>Salary Report for <?php echo date('F', mktime(0,0,0,$_POST['report_month'])) . ' ' . $_POST['report_year']; ?></h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>EMPID</th>
                            <th>Beneficiary Name</th>
                            <th>Amount</th>
                            <th>Transaction</th>
                            <th>Payment Details</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sn = 1;
                        // ADDED: Calculate payment details for the view
                        $paymentDetailsView = "SAL " . strtoupper(date('F', mktime(0, 0, 0, $_POST['report_month'], 1))) . " " . $_POST['report_year'];
                        foreach ($report_data as $record): 
                        ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td><?php echo htmlspecialchars($record['EMPID']); ?></td>
                                <td><?php echo htmlspecialchars($record['Name']); ?></td>
                                <td><?php echo number_format($record['NET_Payable'], 2); ?></td>
                                <td>NEFT</td>
                                <td><?php echo htmlspecialchars($paymentDetailsView); ?></td>
                                <td><?php echo htmlspecialchars($record['permanentaddress'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>