<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../Database/db_connect.php';

$error = '';
$success = '';
$duplicate = false;
$duplicate_filename = '';
$has_errors = false;
$error_details = [];
$processed = 0;
$inserted = 0;
$skipped = 0;

// Enable exception mode for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $file_name = $_FILES['csv_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validate file extension
    if ($file_ext != 'csv') {
        $error = "Only CSV files are allowed.";
    } elseif ($_FILES['csv_file']['size'] > 2 * 1024 * 1024) {
        $error = "File size exceeds 2MB limit.";
    } else {
        // 1️⃣ Check if file name already exists in excel_list
        $check_file = $conn->prepare("SELECT excel_id FROM excel_list WHERE excel_name = ?");
        $check_file->bind_param("s", $file_name);
        $check_file->execute();
        if ($check_file->get_result()->num_rows > 0) {
            $duplicate = true;
            $duplicate_filename = $file_name;
        } else {
            // 2️⃣ Process the CSV
            if (($handle = fopen($file, "r")) !== FALSE) {
                // Skip header
                $header = fgetcsv($handle, 1000, ",");
                
                $processed = 0;
                $inserted = 0;
                $skipped = 0;
                $error_details = [];
                $valid_rows = []; // store valid rows for insertion after all validation

                // Start transaction
                $conn->begin_transaction();

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $processed++;
                    $data = array_pad($data, 7, '');
                    list($id, $name, $batch, $faculty, $semester, $phone, $email) = $data;
                    $id = trim($id);
                    $name = trim($name);
                    $batch = trim($batch);
                    $faculty = trim($faculty);
                    $semester_val = trim($semester);
                    $phone = trim($phone);
                    $email = trim($email);

                    // Validate
                    $row_error = false;
                    $error_messages = [];

                    if (empty($id)) {
                        $row_error = true;
                        $error_messages[] = "Student ID is empty.";
                    } elseif (!is_numeric($id) || $id <= 0) {
                        $row_error = true;
                        $error_messages[] = "Student ID must be a positive number.";
                    }

                    if (empty($name)) {
                        $row_error = true;
                        $error_messages[] = "Name is empty.";
                    }
                    if (empty($batch)) {
                        $row_error = true;
                        $error_messages[] = "Batch is empty.";
                    }
                    if (empty($faculty)) {
                        $row_error = true;
                        $error_messages[] = "Faculty is empty.";
                    }
                    if (empty($email)) {
                        $row_error = true;
                        $error_messages[] = "Email is empty.";
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $row_error = true;
                        $error_messages[] = "Invalid email format.";
                    }

                    if (!is_numeric($semester_val) || $semester_val < 1 || $semester_val > 8) {
                        $row_error = true;
                        $error_messages[] = "Semester must be between 1 and 8.";
                    }

                    // Check for duplicate student_id (against DB)
                    if (!$row_error) {
                        $check_stmt = $conn->prepare("SELECT student_id FROM student WHERE student_id = ?");
                        $check_stmt->bind_param("i", $id);
                        $check_stmt->execute();
                        if ($check_stmt->get_result()->num_rows > 0) {
                            $row_error = true;
                            $error_messages[] = "Student ID already exists in database.";
                        }
                    }

                    if ($row_error) {
                        $skipped++;
                        $error_details[] = "Row $processed: " . implode("; ", $error_messages);
                    } else {
                        // Store valid row for later insertion
                        $valid_rows[] = [
                            'id' => $id,
                            'name' => $name,
                            'batch' => $batch,
                            'faculty' => $faculty,
                            'semester' => (int)$semester_val,
                            'phone' => $phone,
                            'email' => $email
                        ];
                    }
                }
                fclose($handle);

                // 3️⃣ If any errors, rollback and set flag
                if (!empty($error_details)) {
                    $conn->rollback();
                    $has_errors = true;
                } else {
                    // Insert all valid rows
                    try {
                        foreach ($valid_rows as $row) {
                            $insert_stmt = $conn->prepare("INSERT INTO student (student_id, student_name, student_batch, student_faculty, student_semester, student_phone, student_email) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $insert_stmt->bind_param("isssiss", $row['id'], $row['name'], $row['batch'], $row['faculty'], $row['semester'], $row['phone'], $row['email']);
                            $insert_stmt->execute();
                            $inserted++;
                        }
                        // Commit transaction
                        $conn->commit();
                        // Log file in excel_list
                        $insert_log = $conn->prepare("INSERT INTO excel_list (excel_name, excel_date) VALUES (?, NOW())");
                        $insert_log->bind_param("s", $file_name);
                        $insert_log->execute();
                        $success = true;
                    } catch (mysqli_sql_exception $e) {
                        $conn->rollback();
                        $has_errors = true;
                        $error_details[] = "Database error during insert: " . $e->getMessage();
                    }
                }
            } else {
                $error = "Could not open the uploaded file.";
            }
        }
    }
}

// ---- AJAX / popup-modal response path ----
// The "Import Students" popup form (see Admin/footer.php) submits here via
// fetch() with an "ajax" flag so the result can be shown inside the modal
// instead of navigating to a full page.
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    if ($duplicate) {
        echo json_encode([
            'status' => 'duplicate',
            'message' => "The file \"$duplicate_filename\" has already been imported."
        ]);
    } elseif ($has_errors) {
        echo json_encode([
            'status' => 'error',
            'processed' => $processed,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $error_details
        ]);
    } elseif ($success) {
        echo json_encode([
            'status' => 'success',
            'inserted' => $inserted,
            'file' => $file_name ?? ''
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'processed' => 0, 'inserted' => 0, 'skipped' => 0,
            'errors' => [$error ?: 'No file was uploaded.']
        ]);
    }
    exit();
}

// Non-AJAX fallback (e.g. JS disabled): the "Import Students" upload
// now happens through the popup modal defined in Admin/footer.php,
// so a direct/plain POST here just bounces back to the students list.
header("Location: home.php?section=students");
exit();
