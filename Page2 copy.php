<?php

if (!isset($_POST['semester'])) {
    die("Semester not selected.");
}
$semester = $_POST["semester"];

if (!isset($_POST['year'])) {
    die("Year not selected.");
}
$year = $_POST["year"];

if (!isset($_POST['time'])) {
    die("Exam time not selected.");
}
$time = $_POST["time"];

if (!isset($_FILES['slots']) || $_FILES['slots']['error'] !== UPLOAD_ERR_OK) {
    die("No file uploaded or upload error");
}
//Max file upload size 10 Kb
$maxSize = 10 * 1024;
if ($_FILES['slots']['size'] > $maxSize) {
    die("Upload failed: the file exceeds the allowed size limit.");
}
//Prevent executable files from being submitted
$blocked_exts = ['php', 'phtml', 'phar', 'py', 'pl', 'cgi', 'sh', 'rb', 'exe', 'bat', 'cmd', 'c'];
$ext = pathinfo($_FILES['slots']['name'], PATHINFO_EXTENSION);
if (in_array($ext, $blocked_exts)) {die("Executable files are not allowed.");}

// Saving the file in uploads directory
// get extension safely (no leading dot bugs)
$ext = '';
if (isset($_FILES['slots']['name'])) {
    $ext = pathinfo($_FILES['slots']['name'], PATHINFO_EXTENSION);
}
// build filename (guaranteed valid)
$filename = "slots" . ($ext !== '' ? "." . $ext : "");

// uploads directory
$targetDir  = __DIR__ . "/uploads";
$targetPath = $targetDir . "/" . $filename;

// ensure uploads directory exists
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// sanity check
if (!is_uploaded_file($_FILES['slots']['tmp_name'])) {
    die("Invalid upload");
}

// move file
if (!move_uploaded_file($_FILES['slots']['tmp_name'], $targetPath)) {
    die("Failed to save file");
}
/* ---------- END UPLOAD ---------- */
// Read file
$data = file_get_contents($targetPath);
$clean = preg_replace('/^slots\s*=\s/','',$data);
//Add closing brace only if it is missing
if (substr($clean, -1) !== '}') {$clean .= '}';}
$slots = json_decode($clean, true);

if ($slots === null) {
    if (file_exists($targetPath)) {unlink($targetPath);}
    die("JSON error: " . json_last_error_msg());
}

//Getting link/api to extract json from
$making_url = "redacted" . $year . "&semester=" . $semester;
$url = $making_url;
$response = file_get_contents($url);

//Decoding the json file
$data = json_decode($response, true);

if (!isset($data['Applications']) || !is_array($data['Applications'])) {
    die("No Applications found");
    if (file_exists($targetPath)) {unlink($targetPath);}
}

//Connect to mysql default settings
$conn = new mysqli("127.0.0.1", "root", "", "crs_reg", 3306);


//Check connection
if ($conn->connect_error) {
    if (file_exists($targetPath)) {unlink($targetPath);}
    die("Connection failed: " . $conn->connect_error);
}

//Creates a new database which removes (deletes old database) old data and leaves no confusion
if (!$conn->query("DROP DATABASE IF EXISTS `crs_reg`")) {
    if (file_exists($targetPath)) {unlink($targetPath);}
    die("Drop failed: " . $conn->error);
}
if (!$conn->query("CREATE DATABASE `crs_reg`")) {
    if (file_exists($targetPath)) {unlink($targetPath);}
    die("Create failed: " . $conn->error);
}

//Select databse
$sql = "USE `crs_reg`;";
$conn->query($sql);

//Clear before entry
$table = "crs_list";
if (!$conn->query("CREATE TABLE crs_list(
    id INT(5) AUTO_INCREMENT PRIMARY KEY,
    rollnumber INT(11),
    coursecode VARCHAR(8),
    coursename VARCHAR(255)
    )")) {
    if (file_exists($targetPath)) {unlink($targetPath);}
    die("Create failed: " . $conn->error);
}

//Extract Applications array
$applications = $data["Applications"];

//No of entries in applications
$count = count($applications);

//prepare sql insert
$stmt = $conn->prepare(
    "INSERT INTO crs_list (rollnumber, coursecode, coursename)
    VALUES (?, ?, ?)"
);
if (!$stmt){
    if (file_exists($targetPath)) {unlink($targetPath);}
    die("Prepare failed: " . $conn->error);
}

//Loop through array and insert rows
foreach ($applications as $entry){
    $stmt->bind_param(
        "sss",
        $entry["rollnumber"],
        $entry["coursecode"],
        $entry["coursename"]
    );
    $stmt->execute();
}

//Cleanup
$stmt->close();

//Count the total number of courses
$no_crs = 0; $present_crs_code = null;
foreach ($applications as $entry) {
    if ($entry["coursecode"] !== $present_crs_code) {
        $no_crs++;
        $present_crs_code = $entry["coursecode"];
    }
}
// -------------------- Table creation in database modified--------------------
$crs_codes = array_values(array_unique(array_column($applications, 'coursecode')));

for ($i = 0; $i < count($crs_codes); $i++) {
    // Replace dot and sanitize anything unsafe
    $table = preg_replace('/[^a-zA-Z0-9_]/', '_', $crs_codes[$i]);

    $sql = "CREATE TABLE IF NOT EXISTS `$table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rollnumber VARCHAR(32),
        coursecode VARCHAR(64),
        coursename VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql);
}
foreach ($applications as $entry) {

    // Sanitize table name (identifiers cannot be bound)
    $table = preg_replace('/[^a-zA-Z0-9_]/', '_', $entry['coursecode']);

    $sql = "
        INSERT INTO `$table` (rollnumber, coursecode, coursename)
        VALUES (?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sss",
        $entry['rollnumber'],
        $entry['coursecode'],
        $entry['coursename']
    );
    $stmt->execute();
}
$stmt->close();

for($i=1; $i<=16; $i++){
    $slot_name = "slot".$i;
    $$slot_name = isset($slots[(string)$i]) ? $slots[(string)$i] : [];
}

//---Checking to see if there is a mismatch in slots uploaded and database from api----//
$tableNames = [];
foreach ($slots as $slot) {
    foreach ($slot as $course) {
        $table = str_replace('.', '_', $course);
        $tableNames[] = $table;
    }
}
$tableNames = array_unique($tableNames);

$missingCourses = [];
foreach ($tableNames as $table) {
    /*
    // safety check for table name
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        if (file_exists($targetPath)) {unlink($targetPath);}
        die("Invalid table name detected: " . htmlspecialchars($table));
    }
    */

    $sql = "
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = 'crs_reg'
          AND table_name = '$table'
    ";

    $result = $conn->query($sql);
    if (!$result) {
        if (file_exists($targetPath)) {unlink($targetPath);}
        die("Error checking table existence");
    }

    $count = $result->fetch_row()[0];
    if ($count == 0) {
        // convert back to course code format for display
        $missingCourses[] = str_replace('_', '.', $table);
    }
}
// Die with output if any table is missing
if (!empty($missingCourses)) {
    $message = "The following courses are present in slots but do not exist in the database:<br><br>";
    foreach ($missingCourses as $course) {
        $message .= htmlspecialchars($course) . " ";
    }
    if (file_exists($targetPath)) {unlink($targetPath);}
    die($message);
}

$noClashFlag = 1;   // 1 = no clashes, 0 = at least one clash exists

echo "<table border='2' cellpadding='12' cellspacing='0' style='width:100%; border-collapse:collapse; text-align:center;'>";

/* -------- Row 1 : Slot headers -------- */
echo "<tr>";
for ($i = 1; $i <= 16; $i++) {
    echo "<th>Slot $i</th>";
}
echo "</tr>";

/* -------- Row 2 : Clash results -------- */
echo "<tr>";

for ($i = 1; $i <= 16; $i++) {

    echo "<td valign='top'>";

    $slotKey = (string)$i;

    if (!isset($slots[$slotKey]) || count($slots[$slotKey]) < 2) {
        echo "<b>0</b>";
        echo "</td>";
        continue;
    }

    $courses = $slots[$slotKey];
    $slotClashes = [];

    // pairwise comparison of courses inside the slot
    for ($a = 0; $a < count($courses); $a++) {
        for ($b = $a + 1; $b < count($courses); $b++) {

            $course1 = $courses[$a];
            $course2 = $courses[$b];

            $t1 = preg_replace('/[^a-zA-Z0-9_]/', '_', $course1);
            $t2 = preg_replace('/[^a-zA-Z0-9_]/', '_', $course2);

            // get all clashing rollnumbers
            $sql = "
                SELECT t1.rollnumber
                FROM `$t1` t1
                INNER JOIN `$t2` t2
                    ON t1.rollnumber = t2.rollnumber
            ";

            $res = $conn->query($sql);
            if (!$res || $res->num_rows === 0) {
                continue;
            }

            $noClashFlag = 0;   // clash detected anywhere

            $rolls = [];
            while ($row = $res->fetch_assoc()) {
                $rolls[] = htmlspecialchars($row['rollnumber']);
            }

            $count = count($rolls);

            $slotClashes[] =
                htmlspecialchars($course1) . " & " . htmlspecialchars($course2) .
                " - " . $count .
                " -> (" . implode(", ", $rolls) . ")";
        }
    }

    if (empty($slotClashes)) {
        echo "<b>0</b>";
    } else {
        echo implode("<br>", $slotClashes);
    }

    echo "</td>";
}

echo "</tr>";
echo "</table>";
$conn->close();
//go back to page 1 or go forward to page 3
?>
<html>
<head>
    <style>
        .big-btn {
            font-size: 18px;
            padding: 12px 32px;
            border-radius: 999px;
            cursor: pointer;
            background-color: blue;
            color: white;
            border: none;
            margin-top: 20px;
        }

        .big-btn:hover {
            background-color: darkblue;
        }
    </style>
</head>
</html>
<?php
if (file_exists($targetPath)) {unlink($targetPath);}
//go to page 3 if no clashes
if ($noClashFlag):
?>
    <form action="Page3.php" method="post">
        
        <input type="hidden" name="time" value="<?php echo htmlspecialchars($time); ?>">
        <input type="hidden" name="year" value="<?php echo htmlspecialchars($year); ?>">
        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
        <input type="hidden" name="slots_json" value='<?= htmlspecialchars(json_encode($slots), ENT_QUOTES) ?>'>

        <button class="big-btn">Generate Timetable</button>
    </form>
<?php
//else go back to page 1
else: ?>
    <form action="Page1.php" method="post" enctype="multipart/form-data">
        <button class="big-btn">Return to Upload Page</button>
    </form>
<?php endif;
?>

