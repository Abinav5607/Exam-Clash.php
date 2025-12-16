
<?php
//Copy the courses accordingly into midsem and endsem crs

$mid_slots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16]; //midsem slots
$end_slots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14]; //endsem slots
//use scan etc and then put the courses in here
$mid_crs = [1=>[],2=>[],3=>[],4=>[],5=>[],6=>[],7=>[],8=>[],9=>[],10=>[],11=>[],12=>[],13=>[],14=>[],15=>[],16=>[]];
$end_crs = [1=>[],2=>[],3=>[],4=>[],5=>[],6=>[],7=>[],8=>[],9=>[],10=>[],11=>[],12=>[],13=>[],14=>[]]; 

$semester = $_POST["semester"]; $year = $_POST["year"]; $time = $_POST["time"];

if (
    !isset($_FILES['timetable']) ||
    $_FILES['timetable']['error'] !== UPLOAD_ERR_OK
) {
    die("No file uploaded or upload error");
}

$filename = basename($_FILES['timetable']['name']);
$targetPath = __DIR__ . "/" . $filename;

if (!move_uploaded_file($_FILES['timetable']['tmp_name'], $targetPath)) {
    die("Failed to save file");
}

$making_url = "https://ims-dev.iiit.ac.in/exam_schedule_api.php?typ=getStudData&key=IMS&secret=ExamDegunGts&year=" . $year . "&semester=" . $semester;

$url = $making_url;
//$url = "https://ims-dev.iiit.ac.in/exam_schedule_api.php?typ=getStudData&key=IMS&secret=ExamDegunGts&year=2024-25&semester=Spring";
$response = file_get_contents($url);
$data = json_decode($response, true);


if (!isset($data['Applications']) || !is_array($data['Applications'])) {
    die("No Applications found");
}

function clearTable($conn, $table) {
    $sql = "TRUNCATE TABLE $table";
    if (!$conn->query($sql)) {
        die("Error clearing table: " . $conn->error);
    }
}

//Connect to mysql using mamp default settings
$conn = new mysqli("localhost", "root", "root", "", 8888);

//Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!$conn->query("DROP DATABASE IF EXISTS `crs_reg`")) {
    die("Drop failed: " . $conn->error);
}

/* Recreate empty database */
if (!$conn->query("CREATE DATABASE `crs_reg`")) {
    die("Create failed: " . $conn->error);
}

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
    die("Create failed: " . $conn->error);
}
//clearTable($conn, $table);

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

//Creating the tables for each course

function getNextCourseCode($applications, $currentKey) {
    $keys = array_keys($applications);
    $pos = array_search($currentKey, $keys);

    if ($pos === false || $pos + 1 >= count($keys)) {
        return null; // no next item
    }

    $nextKey = $keys[$pos + 1];
    return $applications[$nextKey]["coursecode"];
}

function getPrevCourseCode($applications, $currentKey) {
    $keys = array_keys($applications);
    $pos = array_search($currentKey, $keys);

    if ($pos === false || $pos - 1 < 0) {
        return null; // no previous item
    }

    $prevKey = $keys[$pos - 1];
    return $applications[$prevKey]["coursecode"];
}

// -------------------- REPLACE the old while($no_crs--) { ... } block with this --------------------

// Normalization helper: trim + uppercase + remove non-alphanumeric so tiny differences don't split groups
function normalizeCourseCode($code) {
    if ($code === null) return null;
    $s = strtoupper(trim((string)$code));
    // remove any characters that are not letters or digits (keeps letters+digits only)
    $s = preg_replace('/[^A-Z0-9]/', '', $s);
    return $s;
}

// Build contiguous groups by NORMALIZED coursecode (keeps original ordering)
$groups = [];
$current_group = [];
$prev_norm = null;

foreach ($applications as $key => $entry) {
    if (!isset($entry['coursecode'])) continue;
    $norm = normalizeCourseCode($entry['coursecode']);

    if ($prev_norm === null) {
        $current_group[] = $entry;
    } else {
        if ($norm === $prev_norm) {
            $current_group[] = $entry;
        } else {
            $groups[] = $current_group;
            $current_group = [$entry];
        }
    }
    $prev_norm = $norm;
}
if (!empty($current_group)) $groups[] = $current_group;

// Debug: show counts & sample
error_log("DEBUG: computed groups = " . count($groups));
for ($g = 0; $g < min(10, count($groups)); $g++) {
    $sample = $groups[$g][0]['coursecode'] ?? '(no code)';
}

// If you intended to create $no_crs tables but grouping gave a different count, fix $no_crs:
if ($no_crs !== count($groups)) {
    echo "NOTE: \$no_crs was $no_crs but groups count is " . count($groups) . ". Using groups count for table creation.<br>";
    $no_crs = count($groups); // use groups count so table-per-group matches actual grouping
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Helper to safely quote identifier (table name)
function safeIdentifier($conn, $name) {
    $name = str_replace("`", "", $name);
    return "`" . $conn->real_escape_string($name) . "`";
}

// Create / truncate / insert for each group
$created = [];
$failedCreates = [];
$failedInserts = 0;

for ($idx = 1; $idx <= $no_crs; $idx++) {
    // determine coursecode of this group
    $sample = $groups[$idx-1][0];  
    $raw_code = $sample["coursecode"];

    // turn course code into a safe SQL table name
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '_', strtoupper($raw_code));

    // escape identifier
    $tn = safeIdentifier($conn, $table_name);


    // CREATE TABLE immediately
    $create_sql = "CREATE TABLE IF NOT EXISTS {$tn} (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        rollnumber VARCHAR(32),
        coursecode VARCHAR(64),
        coursename VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($create_sql)) {
        $failedCreates[$table_name] = $conn->error;
        error_log("Create failed for {$table_name}: " . $conn->error);
        echo "Create failed for {$table_name}: " . htmlspecialchars($conn->error) . "<br>";
        // skip to next index; do not attempt to truncate/insert into a non-created table
        continue;
    }

    // TRUNCATE table (use your clearTable function or direct query)
    // clearTable expects a raw identifier, so pass table name without backticks and let it build SQL, or modify clearTable accordingly.
    // We'll call TRUNCATE directly with backticked name to be safe:
    if (!$conn->query("TRUNCATE TABLE {$tn}")) {
        error_log("Truncate failed for {$table_name}: " . $conn->error);
        echo "Truncate failed for {$table_name}: " . htmlspecialchars($conn->error) . "<br>";
        // continue — we'll still try to insert
    }

    // Prepare insert for this table
    $insert_sql = "INSERT INTO {$tn} (rollnumber, coursecode, coursename) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        error_log("Prepare failed for {$table_name}: " . $conn->error);
        echo "Prepare failed for {$table_name}: " . htmlspecialchars($conn->error) . "<br>";
        continue;
    }

    // Determine which group to fill: groups are zero-indexed, table idx = 1..N -> group index = idx-1
    $groupIndex = $idx - 1;
    if (!isset($groups[$groupIndex])) {
        // no group to fill (shouldn't happen if we kept counts aligned)
        $stmt->close();
        continue;
    }

    foreach ($groups[$groupIndex] as $entry) {
        $roll = isset($entry['rollnumber']) ? $entry['rollnumber'] : '';
        $code = isset($entry['coursecode']) ? $entry['coursecode'] : '';
        $cname = isset($entry['coursename']) ? $entry['coursename'] : '';

        $stmt->bind_param("sss", $roll, $code, $cname);
        if (!$stmt->execute()) {
            $failedInserts++;
            error_log("Insert failed into {$table_name}: " . $stmt->error);
        }
    }

    $stmt->close();
    $created[] = $table_name;
    
}

// -------------------- end replacement block --------------------

// Read file
$raw = file_get_contents("slots");

$clean = preg_replace('/^slots\s*=\s/','',$raw);

$slots = json_decode($clean, true);
if ($slots === null) {
    die("JSON error: " . json_last_error_msg());
}

for($i=1; $i<=16; $i++){
    $mid_varname = "mid_slot".$i;
    $end_varname = "end_slot".$i;

    $$mid_varname = isset($slots[(string)$i]) ? $slots[(string)$i] : [];
    $$end_varname = isset($slots[(string)$i]) ? $slots[(string)$i] : [];
}

//-----------
//FOR SLOT 15
//-----------
$not_placed_15 = [];   // moved OUTSIDE

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

for ($i = 0; $i < count($end_slot15); $i++) {

    // course from slot 15
    $slot_15_crs = preg_replace('/\./', '_', $end_slot15[$i]);

    // students in this course
    $slot_15_crs_stud = [];
    $sql = "SELECT rollnumber FROM $slot_15_crs";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $slot_15_crs_stud[] = $row["rollnumber"];
        }
    }

    // try placing into slots 1–14
    for ($j = 1; $j <= 14; $j++) {

        $slot_num = "end_slot{$j}";
        $clash = 0;

        for ($k = 0; $k < count($$slot_num); $k++) {

            $crs_code = $$slot_num[$k];
            $present_crs_stud = [];

            $sql = "SELECT rollnumber FROM $crs_code";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $present_crs_stud[] = $row["rollnumber"];
                }
            }

            // check ANY student overlap
            for ($c1 = 0; $c1 < count($slot_15_crs_stud); $c1++) {
                for ($c2 = 0; $c2 < count($present_crs_stud); $c2++) {
                    if ($slot_15_crs_stud[$c1] === $present_crs_stud[$c2]) {
                        $clash = 1;
                        break 2;   // break both loops immediately
                    }
                }
            }

            if ($clash) break;
        }

        // if no clash, place course and stop checking further slots
        if (!$clash) {
            $$slot_num[] = $slot_15_crs;
            break;
        }

        // if reached last slot and still clash
        if ($j === 14) {
            $not_placed_15[] = $slot_15_crs;
        }
    }
}


//-----------
//FOR SLOT 16
//-----------
$not_placed_16 = [];   // moved OUTSIDE

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

for ($i = 0; $i < count($end_slot16); $i++) {

    // course from slot 16
    $slot_16_crs = preg_replace('/\./', '_', $end_slot16[$i]);

    // students in this course
    $slot_16_crs_stud = [];
    $sql = "SELECT rollnumber FROM $slot_16_crs";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $slot_16_crs_stud[] = $row["rollnumber"];
        }
    }

    // try placing into slots 1–14
    for ($j = 1; $j <= 14; $j++) {

        $slot_num = "end_slot{$j}";
        $clash = 0;

        for ($k = 0; $k < count($$slot_num); $k++) {

            $crs_code = $$slot_num[$k];
            $present_crs_stud = [];

            $sql = "SELECT rollnumber FROM $crs_code";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $present_crs_stud[] = $row["rollnumber"];
                }
            }

            // check ANY student overlap
            for ($c1 = 0; $c1 < count($slot_16_crs_stud); $c1++) {
                for ($c2 = 0; $c2 < count($present_crs_stud); $c2++) {
                    if ($slot_16_crs_stud[$c1] === $present_crs_stud[$c2]) {
                        $clash = 1;
                        break 2;
                    }
                }
            }

            if ($clash) break;
        }

        // if no clash, place course and stop checking further slots
        if (!$clash) {
            $$slot_num[] = $slot_16_crs;
            break;
        }

        // if reached last slot and still clash
        if ($j === 14) {
            $not_placed_16[] = $slot_16_crs;
        }
    }
}




echo "Midsem Slots"; echo "<br>";
echo "Slot1: "; print_r($mid_slot1); echo "<br>";
echo "Slot2: "; print_r($mid_slot2); echo "<br>";
echo "Slot3: "; print_r($mid_slot3); echo "<br>";
echo "Slot4: "; print_r($mid_slot4); echo "<br>";
echo "Slot5: "; print_r($mid_slot5); echo "<br>";
echo "Slot6: "; print_r($mid_slot6); echo "<br>";
echo "Slot7: "; print_r($mid_slot7); echo "<br>";
echo "Slot8: "; print_r($mid_slot8); echo "<br>";
echo "Slot9: "; print_r($mid_slot9); echo "<br>";
echo "Slot10: "; print_r($mid_slot10); echo "<br>";
echo "Slot11: "; print_r($mid_slot11); echo "<br>";
echo "Slot12: "; print_r($mid_slot12); echo "<br>";
echo "Slot13: "; print_r($mid_slot13);echo "<br>";
echo "Slot14: "; print_r($mid_slot14); echo "<br>";
echo "Slot15: "; print_r($mid_slot15); echo "<br>";
echo "Slot16: "; print_r($mid_slot16); echo "<br>";
echo "Endsem Slots"; echo "<br>";
echo "Slot1: "; print_r($end_slot1); echo "<br>";
echo "Slot2: "; print_r($end_slot2); echo "<br>";
echo "Slot3: "; print_r($end_slot3); echo "<br>";
echo "Slot4: "; print_r($end_slot4); echo "<br>";
echo "Slot5: "; print_r($end_slot5); echo "<br>";
echo "Slot6: "; print_r($end_slot6); echo "<br>";
echo "Slot7: "; print_r($end_slot7); echo "<br>";
echo "Slot8: "; print_r($end_slot8); echo "<br>";
echo "Slot9: "; print_r($end_slot9); echo "<br>";
echo "Slot10: "; print_r($end_slot10); echo "<br>";
echo "Slot11: "; print_r($end_slot11); echo "<br>";
echo "Slot12: "; print_r($end_slot12); echo "<br>";
echo "Slot13: "; print_r($end_slot13); echo "<br>";
echo "Slot14: "; print_r($end_slot14); echo "<br>";
echo "Slot15: "; print_r($end_slot15); echo "<br>";
echo "Slot16: "; print_r($end_slot16); echo "<br>";
echo "<br>";
echo "Unplaced courses from slot 15 (endsem): "; print_r($not_placed_15); echo "<br>";
echo "Unplaced courses from slot 16 (endsem): "; print_r($not_placed_16); echo "<br>";
echo "<br>";

//----Check for number of clashes in midsem------//
//Here check 1,2; 2,3; 3,4; 5,6; 6,7; 7,8; 9,10; 10,11; 11,12; 13,14; 14,15; 15,16;
//of the format mid_slot1...
$checks = 1; echo "Consecutive Exams in midsems:"; echo "<br>";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

for($checks = 1; $checks <=16; $checks++){
    if($checks%4===0) continue;
    $consecutive = 0; $crs1 = []; $crs2 = []; 

    $slot_num = "mid_slot" . $checks;
    for($i = 0; $i<count($$slot_num); $i++){
        $course_code = $$slot_num[$i];
        $course_code = str_replace('.', '_', $course_code);
        $sql = "SELECT rollnumber FROM $course_code";
        $result = $conn->query($sql);

        while ($row = $result->fetch_assoc()) {
            $crs1[] = $row['rollnumber'];
        }

    }

    $slot_num = "mid_slot" . ($checks+1);
    for($i = 0; $i<count($$slot_num); $i++){
        $course_code = $$slot_num[$i];
        $course_code = str_replace('.', '_', $course_code);
        $sql = "SELECT rollnumber FROM $course_code";
        $result = $conn->query($sql);

        while ($row = $result->fetch_assoc()) {
            $crs2[] = $row['rollnumber'];
        }
    }
    $consecutive = count(array_intersect($crs1, $crs2));


    echo "Slots " . $checks . " and " . ($checks+1) . " have " . $consecutive . " consecutive exams."; echo "<br>";
}

echo "<br>";


//----Check for number of clashes in endsem------//
//Here check 1,2; 3,4; 5,6; 7,8; 9,10; 11,12; 13,14;
//of the format end_slot1...
$checks = 1; echo "Consecutive Exams in endsems:"; echo "<br>";

for($checks = 1; $checks <=14; $checks+=2){
    $consecutive = 0; $crs1 = []; $crs2 = []; 

    $slot_num = "end_slot" . $checks;
    for($i = 0; $i<count($$slot_num); $i++){
        $course_code = $$slot_num[$i];
        $course_code = str_replace('.', '_', $course_code);
        $sql = "SELECT rollnumber FROM $course_code";
        $result = $conn->query($sql);

        while ($row = $result->fetch_assoc()) {
            $crs1[] = $row['rollnumber'];
        }

    }

    $slot_num = "end_slot" . ($checks+1);
    for($i = 0; $i<count($$slot_num); $i++){
        $course_code = $$slot_num[$i];
        $course_code = str_replace('.', '_', $course_code);
        $sql = "SELECT rollnumber FROM $course_code";
        $result = $conn->query($sql);

        while ($row = $result->fetch_assoc()) {
            $crs2[] = $row['rollnumber'];
        }
    }
    $consecutive = count(array_intersect($crs1, $crs2));


    echo "Slots " . $checks . " and " . ($checks+1) . " have " . $consecutive . " consecutive exams."; echo "<br>";
}

$conn->close();


//databases are created successfully.
//now take the timetable input
//classify them into midsem and endsem
//write them into slots 1-16 for each as 2 arrays
//then write slots for midsem as it is
//after that use database to check for consecutive exams
//copy one course into array. then compare against the next entire slot
//after that echo the number of clashes
//take one course from slot 15/16 into an array
//compare it against 1-14 and add there
//after that copy one course from slot 1 to array compare to slot 2 and so on
//then echo number of consecutive exams




?>



