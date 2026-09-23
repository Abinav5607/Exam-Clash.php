# Exam Timetable PHP Application

A PHP-based web application for generating and validating examination timetables from predefined course-slot assignments.

The application takes academic information and a slot configuration as input, retrieves course registration data, detects examination clashes, rearranges slots to reduce consecutive examinations, and produces a structured timetable for academic verification.

---

## Overview

The system is designed to generate a reliable examination timetable while satisfying predefined slot constraints.

The main objectives are to:

* Map courses to their assigned examination slots.
* Organize examination slots according to predefined day and session structures.
* Detect clashes between courses scheduled in the same slot.
* Prevent timetable generation when unresolved slot clashes exist.
* Rearrange examination slots to reduce consecutive examinations.
* Allow a particular course to be checked against all generated slots for remaining clashes.
* Display the resulting timetable in a clear tabular format.

The application follows a sequential processing flow:

```text
Page1.php
   ↓
Page2.php
   ↓
Page3.php
   ↓
Final Exam Timetable
```

---

## Features

* Semester selection
* Academic year selection
* Midsem / Endsem examination selection
* Slot-file upload
* JSON-based slot representation
* Input and file validation
* Course registration data processing
* Database-backed clash detection
* Intra-slot clash detection
* Slot-to-day/session mapping
* Automatic handling of courses from additional slots
* Slot rearrangement using pairwise exchanges
* Consecutive-examination detection
* Consecutive-examination minimization
* Per-course clash checking
* Clear timetable rendering using HTML tables
* Controlled error handling for invalid input

---

## Technology Stack

| Component          | Technology                                 |
| ------------------ | ------------------------------------------ |
| Frontend           | HTML, CSS                                  |
| Backend            | PHP                                        |
| Database           | MySQL / MariaDB-compatible MySQL interface |
| Data Format        | JSON                                       |
| Web Server         | Apache with PHP                            |
| Database Interface | PHP `mysqli`                               |

---

## Project Structure

```text
.
├── Page1.php
├── Page2.php
├── Page3.php
├── uploads/
└── README.md
```

### `Page1.php`

Responsible for collecting the user's input.

The page allows the user to:

* Upload the slot file.
* Select the semester.
* Select the academic year.
* Select the examination type.
* Start the clash-checking process.

The form submits the information to `Page2.php` using HTTP POST.

---

### `Page2.php`

Responsible for validation, preprocessing, database preparation, and initial clash detection.

Its main operations include:

1. Receiving the submitted semester, year, examination type, and slot file.
2. Validating the uploaded file.
3. Limiting the upload size.
4. Blocking executable file extensions.
5. Reading and decoding the slot JSON.
6. Retrieving course registration information.
7. Creating the required database tables.
8. Checking whether every course in the slot configuration exists in the registration data.
9. Comparing courses assigned to the same slot.
10. Detecting students enrolled in multiple courses scheduled simultaneously.
11. Preventing progression to timetable generation if clashes are detected.
12. Passing valid slot data to `Page3.php`.

The application uses student roll numbers from the course registration data to determine whether two courses have overlapping students.

---

### `Page3.php`

Responsible for timetable generation, slot rearrangement, consecutive-examination analysis, and final course-level clash checking.

It:

* Maps slots to days and sessions.
* Processes Midsem and Endsem schedules differently.
* Handles courses from additional slots.
* Calculates clashes between adjacent examinations.
* Rearranges slots using pairwise exchanges.
* Iteratively improves the slot arrangement.
* Displays the final timetable.
* Allows a specific course to be checked against all available examination slots.

---

# Application Flow

## 1. Input Collection

The user opens `Page1.php` and provides:

* Slot configuration file
* Semester
* Academic year
* Examination type

The available examination types are:

```text
Midsem
Endsem
```

The semester options are:

```text
Spring
Monsoon
```

The submitted information is sent to `Page2.php`.

---

## 2. Input Validation

`Page2.php` validates the submitted information before performing timetable processing.

The uploaded file is checked for:

* Successful upload
* Maximum file size
* Disallowed executable extensions
* Valid uploaded-file status
* Valid JSON structure

The application currently limits uploaded slot files to **10 KB**.

Executable extensions such as PHP, Python, shell scripts, executables, and similar files are rejected.

---

## 3. Slot Data Processing

The slot file is decoded into a PHP associative array.

The application expects the slot information to associate slot numbers with course codes.

Conceptually:

```text
Slot 1 → Course A, Course B
Slot 2 → Course C
Slot 3 → Course D, Course E
...
```

The slot information is then used throughout the timetable-generation process.

---

# Database Processing

The application uses a database named:

```text
crs_reg
```

A common course-registration table is created:

```text
crs_list
```

with information including:

```text
rollnumber
coursecode
coursename
```

Individual course tables are also created from course codes.

These tables contain the students registered for each course.

The application uses the student roll numbers to determine whether two courses contain common students.

For example:

```text
Course A → 101, 102, 103
Course B → 103, 104, 105
```

Since student `103` is registered for both courses, scheduling the two courses in the same examination slot creates a clash.

---

# Clash Detection

## Intra-Slot Clash Detection

Courses within each slot are compared pairwise.

For a slot containing:

```text
Course A
Course B
Course C
```

the application checks:

```text
A ↔ B
A ↔ C
B ↔ C
```

The common roll numbers between each pair are obtained using database queries.

If at least one student occurs in both courses, a clash is detected.

The result identifies:

* The two conflicting courses
* Number of students affected
* The relevant roll numbers

If a slot contains an unresolved clash, timetable generation is blocked.

---

# Slot-to-Day/Session Mapping

The application converts numerical slot identifiers into logical timetable positions.

For Midsem examinations, the slots are arranged into groups of four sessions per day.

The mapping uses integer division and modulo operations to determine:

```text
Day
Slot within the day
```

For Endsem examinations, the slot arrangement uses two sessions per day.

This allows numerical slot identifiers to be displayed as meaningful timetable positions such as:

```text
Day 1 Slot 1
Day 1 Slot 2
Day 2 Slot 1
...
```

---

# Handling Additional Slots

For Endsem scheduling, courses initially associated with slots 15 and 16 are considered for reassignment into slots 1–14.

For each course:

1. The students registered for the course are retrieved.
2. Existing slots are examined.
3. The students in the courses already occupying each slot are retrieved.
4. The student sets are compared.
5. The course is placed into the first suitable slot without a student clash.
6. If no suitable slot exists, the course is recorded as unplaced.

This allows the system to handle courses that cannot remain in their original positions.

---

# Consecutive Examination Detection

The application also considers examinations occurring in adjacent slots.

For Midsem examinations, consecutive slot relationships are checked while respecting the break structure.

For Endsem examinations, consecutive examination pairs are checked as:

```text
1 & 2
3 & 4
5 & 6
...
```

The number of students appearing in both consecutive examinations is calculated.

This provides a measure of how many students would have examinations in consecutive sessions.

---

# Slot Rearrangement

The application attempts to reduce consecutive examinations by rearranging slots.

For Midsem:

```text
16 slots
```

are considered.

For Endsem:

```text
14 slots
```

are considered after handling the additional slots.

The system starts with the original slot arrangement and evaluates possible pairwise exchanges.

For example:

```text
Original:

Slot 1 → A
Slot 2 → B
Slot 3 → C

Possible exchange:

Slot 1 → B
Slot 2 → A
Slot 3 → C
```

The new arrangement is evaluated using the consecutive-examination clash cost.

---

# Pairwise Exchange Algorithm

For Midsem, the application considers pairs of slots using:

```text
i = 1 ... 16
j = i + 1 ... 16
```

This corresponds to considering every unordered pair of slots once.

For Endsem, the same approach is applied to the 14 available slots.

For every pair:

1. A temporary mapping is created.
2. The two slot assignments are exchanged.
3. The resulting cost is calculated.
4. If the cost is lower than the current best cost, the exchange is retained.

The process is repeated while improvements are possible.

Conceptually:

```text
Initial timetable
       ↓
Calculate consecutive-exam cost
       ↓
Try pairwise slot exchange
       ↓
Calculate new cost
       ↓
Is the cost lower?
    ↙       ↘
  Yes        No
   ↓          ↓
Keep swap   Reject swap
       ↓
Repeat
```

This is an iterative improvement heuristic rather than an exhaustive search of every possible timetable permutation.

---

# Timetable Generation

After slot processing and rearrangement, the application renders the timetable using HTML tables.

For Midsem examinations, the timetable displays the examination sessions along with:

* Day
* Slot
* Original slot identifier
* Course name
* Course code

For Endsem examinations, the timetable displays the corresponding examination times and courses.

The original slot identifier is retained in the output so that the relationship between the generated timetable and the input slot arrangement remains clear.

---

# Final Course Clash Check

The application provides an additional course-level verification step.

The user can enter a course code and request the slots in which that course can be scheduled without student clashes.

The application:

1. Retrieves the students registered for the selected course.
2. Iterates through the generated slots.
3. Retrieves students associated with each slot.
4. Compares the two student sets.
5. Calculates the number of overlapping students.
6. Displays slots with zero clashes.

The result therefore identifies the examination slots in which the selected course has no student overlap with the courses already assigned there.

---

# Error Handling

The application uses validation and controlled termination to prevent invalid data from reaching later stages.

Examples include:

* Missing semester
* Missing academic year
* Missing examination type
* Missing uploaded file
* Oversized uploaded file
* Disallowed file type
* Invalid uploaded file
* Invalid JSON
* Missing application data
* Database connection failures
* Database creation failures
* Missing course tables
* Invalid slot information

When a critical error is encountered, processing is stopped rather than continuing with potentially invalid timetable data.

---

# Security Considerations

The repository should not contain:

* Database passwords
* API keys
* Authentication tokens
* `.env` files containing secrets
* SQL dumps containing real student information
* Uploaded files containing private student data
* Session IDs or authentication cookies

The `uploads/` directory should also generally be excluded from version control if it contains user-uploaded files.

A suitable `.gitignore` can include:

```gitignore
.env
uploads/
*.sql
*.sqlite
*.db
```

For deployment, the PHP application should ideally use a dedicated database account with only the permissions it requires rather than the MySQL `root` account.

---

# Requirements

Before running the application, the environment should provide:

* Apache
* PHP
* MySQL or a compatible MySQL/MariaDB database
* PHP `mysqli` extension

The application also requires access to the course-registration data source used by `Page2.php`.

---

# Running the Application

1. Install and configure Apache and PHP.
2. Install and configure MySQL/MariaDB.
3. Create/configure the required database.
4. Place the PHP files in the Apache web directory.
5. Ensure the `uploads/` directory is writable by the web server.
6. Configure the course-registration API/data source used by `Page2.php`.
7. Open:

```text
Page1.php
```

in a browser.

8. Upload the slot configuration.
9. Select the semester, academic year, and examination type.
10. Select **Check Clashes**.
11. If the slot arrangement is valid, proceed to timetable generation.
12. Review the generated timetable and use the course-level clash checker when required.

---

# Example Workflow

```text
User
 │
 │  Slot file + academic information
 ▼
Page1.php
 │
 │  POST
 ▼
Page2.php
 │
 ├── Validate input
 ├── Decode slot JSON
 ├── Retrieve course data
 ├── Build course tables
 ├── Verify courses
 └── Detect intra-slot clashes
 │
 │  No unresolved clash
 ▼
Page3.php
 │
 ├── Map slots to days/sessions
 ├── Process additional courses
 ├── Detect consecutive examinations
 ├── Calculate clash costs
 ├── Try pairwise slot exchanges
 ├── Iteratively improve arrangement
 ├── Render timetable
 └── Perform final course-level checking
 │
 ▼
Final Exam Timetable
```

---

# Algorithms Used

The main algorithms implemented by the application are:

### 1. Slot-to-Day/Session Mapping

Uses arithmetic decomposition of slot numbers to determine their day and session positions.

### 2. Slot-wise Course Aggregation

Courses are grouped using slot-indexed arrays, allowing operations to be performed at the slot level.

### 3. Intra-Slot Clash Detection

Courses within the same slot are compared pairwise, with student registration overlap used to identify conflicts.

### 4. Pairwise Slot Combination

Possible slot exchanges are generated using nested loops so that each unordered pair of slots is considered once.

### 5. Pairwise Slot Exchange

Two slot assignments are temporarily exchanged and evaluated.

### 6. Iterative Improvement

An exchange is retained when it improves the current consecutive-examination cost, and the process continues while improvements are available.

### 7. Consecutive Examination Detection

Adjacent examination positions are checked to identify students who have examinations in consecutive sessions.

### 8. Per-Course Global Clash Checking

A selected course is compared against all generated slots to identify positions with no student overlap.

### 9. Targeted Course Reassignment

Courses that cannot be placed without violating the relevant clash conditions are tracked separately for further handling.

---

# Design Approach

The application separates the major stages of processing across three PHP files:

```text
Input
  ↓
Validation & Preprocessing
  ↓
Timetable Generation
  ↓
Verification & Rendering
```

This separation makes the application easier to understand, debug, and modify.

Each page has a defined responsibility:

| File        | Responsibility                                                                             |
| ----------- | ------------------------------------------------------------------------------------------ |
| `Page1.php` | User input and form handling                                                               |
| `Page2.php` | Validation, preprocessing, database preparation, and initial clash detection               |
| `Page3.php` | Timetable generation, slot optimization, consecutive-exam analysis, and final verification |

---

# Limitations

The slot rearrangement process is an **iterative improvement heuristic**. It evaluates pairwise exchanges and retains improvements rather than exhaustively evaluating every possible permutation of the complete timetable.

Therefore, the resulting arrangement is based on the implemented improvement process and the constraints checked by the application.

The application also depends on the availability and correctness of the course-registration data used during clash detection.

---

# Project Goal

The goal of the project is to automate a significant portion of the examination scheduling process while maintaining explicit clash checking and providing a timetable that is easier to verify and modify.

The system combines:

```text
Input Validation
       +
Course Registration Data
       +
Slot Clash Detection
       +
Slot Rearrangement
       +
Consecutive Exam Minimization
       +
Final Verification
       =
Exam Timetable
```

---

## Author / Project Information

This repository contains the PHP implementation of the Exam Timetable Application, including the input interface, validation and preprocessing logic, timetable generation, clash detection, slot rearrangement, and final verification.
