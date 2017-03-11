<?php

/**
 *Created by Alexandr Vezhlev.
 */

/**
 *Script gets list of files piped via stdin from Linux find command
 *with modification time later than timestamp file modification time.
 *
 *Files are parsed, data is saved in mysql table.
 *
 *Timestamp file is modified at script end with the time of script execution start.
 */

//save current time immediately after entering the script
$scriptStartTime = time();

//debug messages
//ini_set("display_errors", 1);
//ini_set("display_startup_errors", 1);
//error_reporting(E_ALL);

//log file location
define("LOG_FILE", "/var/log/clap/parse.log");

//set name prefixes of files to parse
define("CDR_PREFIX", "cdr_");
define("CMR_PREFIX", "cmr_");

//set names of database tables
define("CDR_TABLE", "cdr");
define("CMR_TABLE", "cmr");

//set database credentials
define("DB_SERVER", "localhost");
define("DB_USER", "cucm");
define("DB_PASSWORD", "cucmpassword");
define("DB_NAME", "cucm");

//set precalculated 2^32 to fix overflown signed integer fields
define("SIGNED_INT32_FIXER", 4294967296);

//indices of cdr_* files entries with possibly overflown signed integer values
$cdrPossiblyOverflownIntIndices = array(7, 13, 21, 28, 35, 43, 85, 91);

//read piped list of files from stdin
$files = array();

while ($file = fgets(STDIN)) {
    $files[] = trim($file);
}

try {
    $conn = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $parsedFilesCount = 0;
    $parsedRecordsCount = 0;
    //parse each file and save data into respective table
    foreach ($files as $file) {

        try {
            $conn->beginTransaction();
            $records = null;

            switch (true) {

                case strpos($file, CDR_PREFIX) !== false:
                    $records = getRecordsFromFile($file, $cdrPossiblyOverflownIntIndices);
                    $conn->query(getInsertQuery(CDR_TABLE, $records));
                    break;

                case strpos($file, CMR_PREFIX) !== false:
                    $records = getRecordsFromFile($file);
                    $conn->query(getInsertQuery(CMR_TABLE, $records));
                    break;
            }

            $conn->commit();
            $parsedFilesCount++;
            $parsedRecordsCount += count($records);

        } catch (Exception $e) {

            $conn->rollBack();
            $errorMessage = $e instanceof PDOException ? "Not valid file format" : $e->getMessage();

            logData("Parsing failure: '" . $errorMessage . "' when parsing file '" . $file . "'. Transaction rolled back.");
        }
    }

    //touch timestamp file with previously saved script start time
    touch(__DIR__ . "/timestamp", $scriptStartTime);

    empty($files) ?
        logData("No new files to parse.") :
        logData("Successfully parsed " . $parsedFilesCount . " files. Added " . $parsedRecordsCount . " records.");

} catch (PDOException $e) {

    logData("Parsing failure: '" . $e->getMessage() . "'. Unparsed files: " . count($files) . ".");

} finally {

    $conn = null;
}


function logData($data) {
    file_put_contents(LOG_FILE, date("Y-m-d H:i:s") . " " . $data . PHP_EOL, FILE_APPEND);
}


function getInsertQuery($table, $records) {

    $query = "INSERT INTO " . $table . " VALUES ";
    foreach ($records as $record) {
        $query .= "(0," . implode(",", $record) . "),";
    }
    $query = substr($query, 0, -1) . ";";

    return $query;
}


function getRecordsFromFile($file, $possiblyOverflownIntIndices = array()) {

    $records = array();

    //explode file content into lines
    $lines = explode(PHP_EOL, trim(file_get_contents($file)));
    $entries = count($lines);

    //start from line 2 because lines 0 and 1 are headers
    for ($i = 2; $i < $entries; ++$i) {

        //explode each line into array of strings
        $record = explode(",", trim($lines[$i]));

        //fix possibly overflown signed integer fields
        foreach ($possiblyOverflownIntIndices as $index) {
            if ($record[$index] < 0) {
                $record[$index] += SIGNED_INT32_FIXER;
            }
        }
        //set completely empty strings to '0' value (to make proper sql query)
        for ($j = 0; $j < count($record); ++$j) {
            if (empty($record[$j])) {
                $record[$j] = '0';
            }
        }
        $records[] = $record;

    }

    return $records;
}

?>
