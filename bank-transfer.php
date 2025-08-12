<?php
require_once 'header.php';
require_once 'db.php';

// Check if the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$report_data = [];
$message = '';
$is_error = false;
$form_data = $_POST;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $report_month = $_POST['report_month'];
        $report_year = $_POST['report_year'];

        // Fetch all salary data for the selected period
        $stmt = $conn->prepare(
            "SELECT e.Name, e.bankAccNumber, e.IFSC_code, s.EMPID, s.NET_Payable 
             FROM salarycal_table s
             JOIN employeebasicdetails e ON s.EMPID = e.EMPID
             WHERE s.Shift_Month = ? AND s.shift_year = ?
             ORDER BY e.Name ASC"
        );
        $stmt->bind_param("ii", $report_month, $report_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()){
            $report_data[] = $row;
        }

        if (empty($report_data) && isset($_POST['view_report'])) {
            $message = "No records found for the selected period.";
            $is_error = true;
        }

        // Handle the Export request
        if (isset($_POST['export_csv']) && !empty($report_data)) {
            $filename = "bank_transfer_" . $report_year . "_" . str_pad($report_month, 2, '0', STR_PAD_LEFT) . ".csv";
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Add header row
            fputcsv($output, ['Employee Name', 'Account Number', 'IFSC Code', 'Amount']);
            
            // Add data rows
            foreach ($report_data as $row) {
                fputcsv($output, [
                    $row['Name'],
                    $row['bankAccNumber'],
                    $row['IFSC_code'],
                    $row['NET_Payable']
                ]);
            }
            
            fclose($output);
            exit();
        }

    } catch (Exception $e) {
        $message = "An error occurred: " . $e->getMessage();
        $is_error = true;
    }
}
?>
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
                    <th>EMPID</th>
                    <th>Name</th>
                    <th>Account Number</th>
                    <th>IFSC Code</th>
                    <th>Net Payable</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['EMPID']); ?></td>
                        <td><?php echo htmlspecialchars($record['Name']); ?></td>
                        <td><?php echo htmlspecialchars($record['bankAccNumber']); ?></td>
                        <td><?php echo htmlspecialchars($record['IFSC_code']); ?></td>
                        <td><?php echo number_format($record['NET_Payable'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
require_once 'footer.php';
?>
