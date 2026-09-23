<?php
$baseDir = __DIR__;
// --------------------------------------------------
// ALLOW:
//   1) POST from Page2.php  (slots_json present)
//   2) GET  from Page3.php
// OTHERWISE:
//   Redirect to Page1.php
// --------------------------------------------------
session_start();

$method = $_SERVER['REQUEST_METHOD'];

/* ---------- CASE 1: POST FROM Page2.php ---------- */
if ($method === 'POST' && isset($_POST['slots_json'])) {

    if (!isset($_POST['semester'], $_POST['year'], $_POST['time'])) {
        die("Missing required POST data.");
    }

    $semester = $_POST['semester'];
    $year     = $_POST['year'];
    $time     = $_POST['time'];
    $cc       = $_POST['cc'] ?? null;

    $slots = json_decode($_POST['slots_json'], true);
    if (!is_array($slots)) {
        die("Invalid slot data.");
    }

    // Mark Page3 as legitimately entered
    $_SESSION['page3_allowed'] = true;
    $_SESSION['slots']    = $slots;
    $_SESSION['semester'] = $semester;
    $_SESSION['year']     = $year;
    $_SESSION['time']     = $time;
    $_SESSION['cc']       = $cc;
}

/* ---------- CASE 2: GET FROM Page3 (RELOAD / NAVIGATION) ---------- */
elseif ($method === 'GET' && isset($_SESSION['page3_allowed'])) {

    $slots    = $_SESSION['slots'];
    $semester = $_SESSION['semester'];
    $year     = $_SESSION['year'];
    $time     = $_SESSION['time'];
    $cc       = $_SESSION['cc'] ?? null;
}

/* ---------- INVALID ACCESS ---------- */
else {
    header("Location: Page1.php");
    exit;
}

//Connect to mysql default settings
$conn = new mysqli("127.0.0.1", "root", "", "crs_reg", 3306);

//Check connection
if ($conn->connect_error) {
    if (file_exists($targetPath)) {unlink($targetPath);}
    die("Connection failed: " . $conn->connect_error);
}
//Select databse
$sql = "USE `crs_reg`;";
$conn->query($sql);

// create mid_slotX and end_slotX
for ($i = 1; $i <= 16; $i++) {
    $midVar = "mid_slot" . $i;
    $endVar = "end_slot" . $i;

    $$midVar = isset($slots[(string)$i]) ? $slots[(string)$i] : [];
    $$endVar = isset($slots[(string)$i]) ? $slots[(string)$i] : [];
}

//-----------
//FOR PLACING COURSES FROM SLOT 15 
//-----------
$not_placed_15 = [];

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
            $crs_code = str_replace('.', '_', $crs_code);

            $sql = "SELECT rollnumber FROM $crs_code";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $present_crs_stud[] = $row["rollnumber"];
                }
            }

            // check ANY student overlap using array_intersect
            if (!empty(array_intersect($slot_15_crs_stud, $present_crs_stud))) {
                $clash = 1;
                break;
            }

            if ($clash) break;
        }

        //if no clash, place course and stop checking further slots
        if (!$clash) {
            $slot_15_crs = preg_replace('/\_/', '.', $end_slot15[$i]);
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
//FOR PLACING COURSES FROM SLOT 16
//-----------
$not_placed_16 = [];

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
            $crs_code = str_replace('.', '_', $crs_code);
            $sql = "SELECT rollnumber FROM $crs_code";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $present_crs_stud[] = $row["rollnumber"];
                }
            }

            // check ANY student overlap using array_intersect
            if (!empty(array_intersect($slot_16_crs_stud, $present_crs_stud))) {
                $clash = 1;
                break;
            }

            if ($clash) break;
        }

        // if no clash, place course and stop checking further slots
        if (!$clash) {
            $slot_16_crs = preg_replace('/\./', '_', $end_slot16[$i]);
            $$slot_num[] = $slot_16_crs;
            break;
        }

        // if reached last slot and still clash
        if ($j === 14) {
            $not_placed_16[] = $slot_16_crs;
        }
    }
}
$unique_courses = [];
$sql = "SELECT DISTINCT coursecode, coursename FROM crs_list";
$result = $conn->query($sql);

if (!$result) {
    if (file_exists($targetPath)) {unlink($targetPath);}
    die("Failed to fetch course list");
}

while ($row = $result->fetch_assoc()) {
    $code = $row['coursecode'];
    $name = $row['coursename'];

    $unique_courses[$code] = $name;
}
//---CSS for the tables below---//
?>
<html>
<head>
    <style>
        .slot-table {
            margin: 0 auto;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }

        .slot-table th,
        .slot-table td {
            border: 2px solid #000000ff;
            padding: 12px;
            text-align: center;
            vertical-align: center;
        }

        .slot-title {
            font-weight: bold;
            margin-bottom: 8px;
            display: block;
        }
        .highlight {
            color: red;
            font-size: 20px;
            text-decoration: underline;
            text-decoration-color: black;
        }
        table, th, td {
            border: 2px solid black;
        }


    </style>
</head>
<body>

<?php

//Printing the unplaced courses
if($time==="Endsem"){
    if(count($not_placed_15)){
        echo "<br>";
        echo "Unplaced courses from slot 15 are: ";
        for($i = 0; $i < count($not_placed_15); $i++){
            echo $not_placed_15[$i] . " ";
        }
        echo "<br>";
    }
    if(count($not_placed_16)){
        echo "<br>";
        echo "Unplaced courses from slot 16 are: ";
        for($i = 0; $i < count($not_placed_16); $i++){
            echo $not_placed_16[$i] . " ";
        }
        echo "<br>";
    }
}

//Function for day slot
function slot_to_day_slot(int $slot, string $time): array{

    $time = strtolower($time);

    if ($time === 'midsem') {
        $day  = intdiv($slot - 1, 4) + 1;
        $pos  = (($slot - 1) % 4) + 1;
    }
    elseif ($time === 'endsem') {
        $day  = intdiv($slot - 1, 2) + 1;
        $pos  = (($slot - 1) % 2) + 1;
    }
    else {
        return [];
    }
    return [
        'day'  => $day,
        'slot' => $pos
    ];
}


if ($time === "Midsem") {

    // Will store clashes: [1 => clashes between 1&2, 2 => clashes between 2&3, ...]
    $midsem_consecutive = [];

    for ($checks = 1; $checks <= 16; $checks++) {

        if ($checks % 4 === 0) continue;  // skip breaks

        $crs1 = [];
        $crs2 = [];

        // ---------- SLOT checks ----------
        $slot_num = "mid_slot" . $checks;

        foreach ($$slot_num as $course_code) {
            $table = str_replace('.', '_', $course_code);
            $res = $conn->query("SELECT rollnumber FROM `$table`");

            while ($row = $res->fetch_assoc()) {
                $crs1[] = $row['rollnumber'];
            }
        }

        // ---------- SLOT checks+1 ----------
        $slot_num = "mid_slot" . ($checks + 1);

        foreach ($$slot_num as $course_code) {
            $table = str_replace('.', '_', $course_code);
            $res = $conn->query("SELECT rollnumber FROM `$table`");

            while ($row = $res->fetch_assoc()) {
                $crs2[] = $row['rollnumber'];
            }
        }

        // Count consecutive clashes
        $midsem_consecutive[$checks] = count(
            array_intersect($crs1, $crs2)
        );
    }
}
if ($time === "Endsem") {

    // Will store clashes: [1 => clashes between 1&2, 3 => clashes between 3&4, ...]
    $endsem_consecutive = [];

    for ($checks = 1; $checks <= 14; $checks += 2) {

        $crs1 = [];
        $crs2 = [];

        // ---------- SLOT checks ----------
        $slot_num = "end_slot" . $checks;

        foreach ($$slot_num as $course_code) {
            $table = str_replace('.', '_', $course_code);
            $res = $conn->query("SELECT rollnumber FROM `$table`");

            while ($row = $res->fetch_assoc()) {
                $crs1[] = $row['rollnumber'];
            }
        }

        // ---------- SLOT checks+1 ----------
        $slot_num = "end_slot" . ($checks + 1);

        foreach ($$slot_num as $course_code) {
            $table = str_replace('.', '_', $course_code);
            $res = $conn->query("SELECT rollnumber FROM `$table`");

            while ($row = $res->fetch_assoc()) {
                $crs2[] = $row['rollnumber'];
            }
        }

        // Count consecutive clashes
        $endsem_consecutive[$checks] = count(
            array_intersect($crs1, $crs2)
        );
    }
}
// ------ Slot clashes already existing in the current slots ------ //

$maxSlots   = ($time === "Midsem") ? 16 : 14;
$slotPrefix = ($time === "Midsem") ? 'mid_slot' : 'end_slot';

// Will store clashes per slot
// Format:
// $slot_clashes['mid_slot1'] = [
//     ['course1'=>..., 'course2'=>..., 'clashes'=>N],
//     ...
// ];
$slot_clashes = [];

for ($checks = 1; $checks <= $maxSlots; $checks++) {

    $slot_num = $slotPrefix . $checks;
    $slot_clashes[$slot_num] = [];

    // Skip empty or single-course slots
    if (!isset($$slot_num) || count($$slot_num) < 2) {
        continue;
    }

    // Pairwise course comparisons inside the slot
    for ($i = 0; $i < count($$slot_num); $i++) {

        // ---------- COURSE 1 ----------
        $course1 = $$slot_num[$i];
        $table1  = str_replace('.', '_', $course1);

        $crs1 = [];
        $res1 = $conn->query("SELECT rollnumber FROM `$table1`");

        while ($row = $res1->fetch_assoc()) {
            $crs1[] = $row['rollnumber'];
        }

        // ---------- COURSE 2 ----------
        for ($j = $i + 1; $j < count($$slot_num); $j++) {

            $course2 = $$slot_num[$j];
            $table2  = str_replace('.', '_', $course2);

            $crs2 = [];
            $res2 = $conn->query("SELECT rollnumber FROM `$table2`");

            while ($row = $res2->fetch_assoc()) {
                $crs2[] = $row['rollnumber'];
            }

            // ---------- CLASH COMPUTATION ----------
            $clashCount = count(array_intersect($crs1, $crs2));

            if ($clashCount > 0) {
                $slot_clashes[$slot_num][] = [
                    'course1' => $course1,
                    'course2' => $course2,
                    'clashes' => $clashCount
                ];
            }
        }
    }
}

//Naming Function
function slot_label(int $n): string
{
    if ($n >= 1 && $n <= 6) {
        return 'A' . $n;
    }
    if ($n >= 7 && $n <= 12) {
        return 'B' . ($n - 6);
    }
    if ($n >= 13 && $n <= 18) {
        return 'C' . ($n - 12);
    }
    return '';
}



if ($time === "Midsem") {

    //Build students per OLD slot
    $slot_students = [];

    for ($s = 1; $s <= 16; $s++) {

        $students = []; $courses  = ${"mid_slot" . $s};

        foreach ($courses as $course) {

            $table = str_replace('.', '_', $course);
            $sql   = "SELECT rollnumber FROM `$table`";
            $res   = $conn->query($sql);

            while ($row = $res->fetch_assoc()) {
                $students[] = $row['rollnumber'];
            }
        }

        $slot_students[$s] = array_unique($students);
    }

    //Clash count function
    function clash_count($a, $b) {
        return count(array_intersect($a, $b));
    }

    /* Define important slot pairs
    (1&2, 2&3, 3&4, skip, repeat)*/
    $important_pairs = [];

    for ($i = 1; $i <= 16; $i++) {
        if ($i % 4 === 0) continue;
        $important_pairs[] = [$i, $i + 1];
    }

    //Total cost of a mapping
    function total_cost($mapping, $pairs, $students) {

        $cost = 0;
        foreach ($pairs as [$a, $b]) {
            $cost += clash_count($students[$mapping[$a]],$students[$mapping[$b]]);
        }
        return $cost;
    }

    //NON-CIRCULAR optimization (16C2)
    // Initial identity mapping: new_slot => old_slot
    $mapping = [];
    for ($i = 1; $i <= 16; $i++) {
        $mapping[$i] = $i;
    }

    $bestCost = total_cost($mapping, $important_pairs, $slot_students);
    $improved = true;

    while ($improved) {

        $improved = false;

        for ($i = 1; $i <= 16; $i++) {
            for ($j = $i + 1; $j <= 16; $j++) {

                $trial = $mapping;
                [$trial[$i], $trial[$j]] = [$trial[$j], $trial[$i]];

                $cost = total_cost($trial, $important_pairs, $slot_students);

                if ($cost < $bestCost) {
                    $mapping   = $trial;
                    $bestCost  = $cost;
                    $improved  = true;
                }
            }
        }
    }

    //==================
    //New slots mapping
    //==================

    echo "<h3 style='text-align:center;'>Prescribed slots for least consecutive exams: </h3>";
    echo "<table style='border-collapse: collapse; width:100%; text-align:center; table-layout: fixed;'>";

    /* -------- ROW 1: NEW SLOTS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black; font-size:20px;'>Placement</th>";

    for ($new = 1; $new <= 16; $new++) {
        echo "<th style='border:2px solid black; font-size:20px;'>D" . slot_to_day_slot($new, $time)['day'] . "S" . slot_to_day_slot($new, $time)['slot'] . "</th>";
    }

    echo "</tr>";


    /* -------- ROW 2: OLD SLOTS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black; font-size: 20px;'>Slot</th>";

    for ($new = 1; $new <= 16; $new++) {
       echo "<td style='border:2px solid black; font-weight:bold; color:red; font-size:20px;'>" . slot_label($mapping[$new]) . "</td>";

    }
    echo "</tr>";
    echo "</table><br>";

    ///====================================
    //Print final table for exam scheduling
    ///====================================

    echo "<h3>After Slot Rearrangement:</h3>";
    echo "<table class='slot-table'>";

    // Header row with times
    echo "<tr>";
    echo "<th style='font-size: 20px; font-weight: bold;'>8:30-10:00</th>";
    echo "<th style='font-size: 20px; font-weight: bold;'>11:30-1:00</th>";
    echo "<th style='font-size: 20px; font-weight: bold;'>2:00-3:30</th>";
    echo "<th style='font-size: 20px; font-weight: bold;'>4:30-6:00</th>";
    echo "</tr>";

    // 16 slots, 4 per row (NEW slot positions)
    for ($new = 1; $new <= 16; $new++) {

        if (($new - 1) % 4 === 0) {
            echo "<tr>";
        }

        $old = $mapping[$new];                 // OLD slot number
        $slot_var = "mid_slot" . $old;         // OLD slot data

        // Show NEW(OLD)
        echo "<td>";
        echo "<span class='slot-title highlight'>(Day " . slot_to_day_slot($new, $time)['day'] . " Slot " . slot_to_day_slot($new, $time)['slot'] . ") (" . slot_label($old) . ")</span><br>";


        // Print courses of OLD slot in NEW position
        for ($i = 0; $i < count($$slot_var); $i++) {
            $code = $$slot_var[$i];

            echo "<span style='font-size:20px; font-weight:bold; color:red;'> | </span>";
            echo htmlspecialchars($unique_courses[$code]) . "<b> (" . htmlspecialchars($code) . ")</b>";
            echo "<span style='font-size:20px; font-weight:bold; color:red;'> | </span>";
        }
        echo "</td>";

        if ($new % 4 === 0) {
            echo "</tr>";
        }
    }
    echo "</table><br>";

    ///======================================
    //Print final table for consecutive exams
    ///======================================

    echo "<h3>Shifted slots (For least consecutive exams)</h3>";
    echo "<table cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%; text-align:center; border:2px solid black;'>";

    /* -------- ROW 1: SLOT PAIRS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black;'>Slots</th>";

    for ($i = 1; $i <= 16; $i++) {
        if ($i % 4 === 0) continue;
        echo "<th style='border:2px solid black;'>D" . slot_to_day_slot($i, $time)['day'] . "S" . slot_to_day_slot($i, $time)['slot'] . " (" . slot_label($mapping[$i]) . ") & D" . slot_to_day_slot($i + 1, $time)['day'] . "S" . slot_to_day_slot($i + 1, $time)['slot'] . " (" . slot_label($mapping[$i + 1]) . ")</th>";
    }
    echo "</tr>";

    /* -------- ROW 2: CLASH COUNTS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black;'>Clashes</th>";

    for ($i = 1; $i <= 16; $i++) {
        if ($i % 4 === 0) continue;

        $cnt = clash_count($slot_students[$mapping[$i]],$slot_students[$mapping[$i + 1]]);
        echo "<td style='border:2px solid black; color:red; font-weight:bold;'>$cnt</td>";
    }
    echo "</tr>";
    echo "</table><br>";
}

if ($time === "Endsem") {

    //Build students per OLD slot
    $slot_students = [];

    for ($s = 1; $s <= 14; $s++) {

        $students = []; $courses  = ${"end_slot" . $s};

        foreach ($courses as $course) {

            $table = str_replace('.', '_', $course);
            $sql   = "SELECT rollnumber FROM `$table`";
            $res   = $conn->query($sql);

            while ($row = $res->fetch_assoc()) {
                $students[] = $row['rollnumber'];
            }
        }

        $slot_students[$s] = array_unique($students);
    }

    //Clash count function
    function clash_count($a, $b) {
        return count(array_intersect($a, $b));
    }

    /*Important pairs for ENDSEM
    (1&2, 3&4, 5&6, ...)*/
    $important_pairs = [];
    for ($i = 1; $i <= 14; $i += 2) {
        $important_pairs[] = [$i, $i + 1];
    }

    //Total cost function
    function total_cost($mapping, $pairs, $students) {

        $cost = 0;
        foreach ($pairs as [$a, $b]) {
            $cost += clash_count($students[$mapping[$a]],$students[$mapping[$b]]);
        }
        return $cost;
    }

    //NON-CIRCULAR optimization (14C2)
    // Initial identity mapping: new_slot => old_slot
    $mapping = [];
    for ($i = 1; $i <= 14; $i++) {
        $mapping[$i] = $i;
    }

    $bestCost = total_cost($mapping, $important_pairs, $slot_students);
    $improved = true;

    while ($improved) {

        $improved = false;

        for ($i = 1; $i <= 14; $i++) {
            for ($j = $i + 1; $j <= 14; $j++) {

                $trial = $mapping;
                [$trial[$i], $trial[$j]] = [$trial[$j], $trial[$i]];

                $cost = total_cost($trial, $important_pairs, $slot_students);

                if ($cost < $bestCost) {
                    $mapping   = $trial;
                    $bestCost  = $cost;
                    $improved  = true;
                }
            }
        }
    }

    //==================
    // New slots mapping
    //==================

    echo "<h3 style='text-align:center;'>Prescribed slots for least consecutive exams: </h3>";
    echo "<table style='border-collapse: collapse; width:100%; text-align:center; table-layout: fixed;'>";

    /* -------- ROW 1: NEW SLOTS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black; font-size:20px;'>Placement</th>";

    for ($new = 1; $new <= 14; $new++) {
        echo "<th style='border:2px solid black; font-size:20px;'>D" . slot_to_day_slot($new, $time)['day'] . "S" . slot_to_day_slot($new, $time)['slot'] . "</th>";
    }
    echo "</tr>";



    /* -------- ROW 2: OLD SLOTS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black; font-size: 20px;'>Slot</th>";

    for ($new = 1; $new <= 14; $new++) {
        echo "<td style='border:2px solid black; font-weight:bold; color:red; font-size:20px;'>" . slot_label($mapping[$new]) . "</td>";
    }
    echo "</tr>";
    echo "</table><br>";

    ///====================================
    //Print final table for exam scheduling
    ///====================================

    echo "<h3>After Slot Rearrangement:</h3>";
    echo "<table class='slot-table'>";

    // Header
    echo "<tr>";
    echo "<th style='width:30%; font-size: 20px; font-weight: bold;'>Time</th>";
    echo "<th style='font-size: 20px; font-weight: bold;'>Courses</th>";
    echo "</tr>";

    // 14 slots
    for ($new = 1; $new <= 14; $new++) {

        echo "<tr>";

        $old = $mapping[$new];
        $slot_var = "end_slot" . $old;

        if ($new % 2 === 1) {
            echo "<td style='font-size:20px; font-weight:bold;'><strong>9:00-12:00<br><br>(Day " . slot_to_day_slot($new, $time)['day'] . " Slot " . slot_to_day_slot($new, $time)['slot'] . ")</strong></td>";
        }
        else {
            echo "<td style='font-size:20px; font-weight:bold;'><strong>3:00-6:00<br><br>(Day " . slot_to_day_slot($new, $time)['day'] . " Slot " . slot_to_day_slot($new, $time)['slot'] . ")</strong></td>";
        }

        // Print OLD slot courses in NEW position
        echo "<td>";
        echo "<span class='slot-title highlight'>(Day " . slot_to_day_slot($new, $time)['day'] . " Slot " . slot_to_day_slot($new, $time)['slot'] . ") (" . slot_label($old) . ")</span><br>";

        for ($i = 0; $i < count($$slot_var); $i++) {
            $code = $$slot_var[$i];

            echo "<span style='font-size:20px; font-weight:bold; color:red;'> | </span>";
            echo htmlspecialchars($unique_courses[$code]) . "<b> (" . htmlspecialchars($code) . ")</b>";
            echo "<span style='font-size:20px; font-weight:bold; color:red;'> | </span>";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    ///======================================
    //Print final table for consecutive exams
    ///======================================

    echo "<h3>Shifted slots (For least consecutive exams)</h3>";

    echo "<table cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%; text-align:center; border:2px solid black;'>";

    /* -------- ROW 1: SLOT PAIRS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black;'>Slots</th>";

    for ($i = 1; $i <= 14; $i += 2) {
        echo "<th style='border:2px solid black;'>D" . slot_to_day_slot($i, $time)['day'] . "S" . slot_to_day_slot($i, $time)['slot'] . " (" . slot_label($mapping[$i]) . ") & D" . slot_to_day_slot($i + 1, $time)['day'] . "S" . slot_to_day_slot($i + 1, $time)['slot'] . " (" . slot_label($mapping[$i + 1]) . ")</th>";
    }
    echo "</tr>";


    /* -------- ROW 2: CLASH COUNTS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black;'>Clashes</th>";

    for ($i = 1; $i <= 14; $i += 2) {

        $cnt = clash_count($slot_students[$mapping[$i]],$slot_students[$mapping[$i + 1]]);
        echo "<td style='border:2px solid black; color:red; font-weight:bold;'>$cnt</td>";
    }
    echo "</tr>";
    echo "</table><br>";
}

// ========================================================
// Clash-free slots for a given course (pure logic + output)
// ========================================================
$cc_exists = 0;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cc = strtolower(trim($_POST['cc'] ?? $_GET['cc']));
    $cc_table = str_replace('.', '_', $cc);

    //Check if it is a valid table
    $res = $conn->query("SHOW TABLES LIKE '$cc_table'");
    $cc_exists = ($res && $res->num_rows > 0);
}

if($cc_exists){
    // Students of selected course
    $cc_students = [];
    $res = $conn->query("SELECT rollnumber FROM `$cc_table`");
    while ($row = $res->fetch_assoc()) {
        $cc_students[] = $row['rollnumber'];
    }
    $cc_students = array_unique($cc_students);

    // Slot parameters
    $maxSlots   = ($time === "Midsem") ? 16 : 14;
    $slotPrefix = ($time === "Midsem") ? 'mid_slot' : 'end_slot';

    // Compute clashes per NEW slot
    $slot_clashes = [];

    for ($new = 1; $new <= $maxSlots; $new++) {

        $old = $mapping[$new];          // new → old
        $slot_var = $slotPrefix . $old;

        $slot_students = [];

        foreach ($$slot_var as $course) {
            $table = str_replace('.', '_', strtolower($course));
            $res = $conn->query("SELECT rollnumber FROM `$table`");
            while ($row = $res->fetch_assoc()) {
                $slot_students[] = $row['rollnumber'];
            }
        }

        $slot_students = array_unique($slot_students);

        $slot_clashes[$new] = count(
            array_intersect($cc_students, $slot_students)
        );
    }

    // ========================================================
    // OUTPUT: ONLY ZERO-CLASH SLOTS
    // ========================================================

    echo "<h3>Clash-free slots for <b>" . strtoupper($cc) . "</b></h3>";

    echo "<table cellpadding='8' cellspacing='0'
        style='border-collapse:collapse; width:100%; text-align:center; border:2px solid black;'>";

    /* -------- ROW 1: SLOTS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black;'>Slots</th>";

    $any = false;

    for ($new = 1; $new <= $maxSlots; $new++) {
        if ($slot_clashes[$new] === 0) {
            $old = $mapping[$new];
            echo "<th style='border:2px solid black;'>Day " . slot_to_day_slot($new, $time)['day'] . " Slot " . slot_to_day_slot($new, $time)['slot'] . " (" . slot_label($old) . ")</th>";
            $any = true;
        }
    }

    echo "</tr>";

    /* -------- ROW 2: CLASH COUNTS -------- */
    echo "<tr>";
    echo "<th style='border:2px solid black;'>Clashes</th>";

    if ($any) {
        for ($new = 1; $new <= $maxSlots; $new++) {
            if ($slot_clashes[$new] === 0) {
                echo "<td style='border:2px solid black; color:red; font-weight:bold;'>0</td>";
            }
        }
    } else {
        echo "<td style='border:2px solid black;' colspan='" . ($maxSlots + 1) . "'>";
        echo "<b>No clash-free slots</b>";
        echo "</td>";
    }

    echo "</tr>";
    echo "</table><br>";
}

?>
<html>
<head>
    <style>
        .center-box select,
        .center-box input[type="file"],
        .center-box input[type="text"] {
            font-size: 16px;
            margin-bottom: 18px;
            padding: 8px;
            width: 10%;
            border: 1px solid #999;
            border-radius: 4px;
            background-color: white;
            color: black;
            box-sizing: border-box;
        }
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
<html>
<h3>Enter course code for checking slot clashes:</h3>
<form action="Page3.php" method="get">

    <input type="hidden" name="time" value="<?php echo htmlspecialchars($time); ?>">
    <input type="hidden" name="year" value="<?php echo htmlspecialchars($year); ?>">
    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
    <input type="hidden" name="slots_json" value='<?= htmlspecialchars(json_encode($slots), ENT_QUOTES) ?>'>
    <div class="center-box">
    <input type="text" name="cc" placeholder="Not Required">
    </div>


    <button class="big-btn">View Slots</button>
</form>
</html>

<?php
//Close conection to database
$conn->close();
?>