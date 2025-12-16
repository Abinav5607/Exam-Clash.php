<html>
<body>
<?php
//Do security later
//first take the input time table
//This will give the timetable file from user and send them to output.php
?>
<form action ="Output-Page.php" method = "post" enctype="multipart/form-data">
    <p>Upload Slots: </p>
    <input type = "file" name = "timetable" required>
    <br>
    <p>Select Semester: </p>
    <select name="semester" required>
    <option value="" disabled selected hidden>Select</option>
    <option value="Spring">Spring</option>
    <option value="Monsoon">Monsoon</option>
    </select>
    <br>
    <p>Select academic year: </p>
    <select name="year" required>
    <option value="" disabled selected hidden>Select</option>

    <?php
    $currentYear = (int)date("Y");

    // generate from (currentYear - 2) to (currentYear + 2)
    for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++) {
        $nextShort = substr((string)($y + 1), -2);   // last 2 digits
        $value = "{$y}-{$nextShort}";
        $label = "{$y}-" . ($y + 1);

        echo "<option value=\"$value\">$label</option>";
    }
    ?>
    </select>
    <br>
    <p>Select Exam: </p>
    <select name="time" required>
    <option value="" disabled selected hidden>Select</option>
    <option value="Midsem">Midsem</option>
    <option value="Endsem">Endsem</option>
    </select>
    <br>
    <br>
    <br>
    <button>Submit</button>
</form>
</body>



</html>
