<html>
<head>
    <style>
        body {
            background-color: white;
            color: black;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
        }

        .center-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 20px;
            max-width: 500px;
            margin: 60px auto;
            padding: 40px;
            border-radius: 8px;
            border: 1px solid #ccc;
            background-color: #fff;
        }

        .center-box p {
            margin: 10px 0 6px 0;
            width: 100%;
            text-align: left;
        }

        /* UNIFIED INPUT STYLING */
        .center-box select,
        .center-box input[type="file"],
        .center-box input[type="text"] {
            font-size: 16px;
            margin-bottom: 18px;
            padding: 8px;
            width: 100%;
            border: 1px solid #999;
            border-radius: 4px;
            background-color: white;
            color: black;
            box-sizing: border-box;
        }

        .center-box select:focus,
        .center-box input[type="file"]:focus,
        .center-box input[type="text"]:focus {
            outline: none;
            border-color: black;
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

<body>
<?php
//Take input file with slots and the three parameters of year, semester and exam time
//Send in to Output-Page.php
?>

<form action="Page2.php" method="post" enctype="multipart/form-data">
    <div class="center-box">

        <p>Upload file with slots:</p>
        <input type="file" name="slots" required>

        <p>Select Semester:</p>
        <select name="semester" required>
            <option value="" disabled selected hidden>Select</option>
            <option value="Spring">Spring</option>
            <option value="Monsoon">Monsoon</option>
        </select>

        <p>Select academic year:</p>
        <select name="year" required>
            <option value="" disabled selected hidden>Select</option>

            <?php
            $currentYear = (int)date("Y");

            // generate from (currentYear - 2) to (currentYear + 2)
            for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++) {
                $nextShort = substr((string)($y + 1), -2);
                $value = "{$y}-{$nextShort}";
                $label = "{$y}-" . ($y + 1);

                echo "<option value=\"$value\">$label</option>";
            }
            ?>
        </select>

        <p>Select Exam:</p>
        <select name="time" required>
            <option value="" disabled selected hidden>Select</option>
            <option value="Midsem">Midsem</option>
            <option value="Endsem">Endsem</option>
        </select>
        <button class="big-btn">Check Clashes</button>

    </div>
</form>
</body>
</html>