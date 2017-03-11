<?php

/**
 *Created by Alexandr Vezhlev.
 */

/**
 *Script loads its parameters from INI_FILE,
 *parses CUCM log files in FILES_PATH parameter
 *into specific MySQL tables
 *and moves them to PARSED_PATH parameter
 */

//change this if you put ini file elsewhere
define("INI_FILE", "/etc/clap/clap.ini");
//*****************************************

//ini file sections and keys
//database
define("DATABASE", "database");
define("URL", "url");
define("USER", "user");
define("PASSWORD", "password");
//parser
define("PARSER", "parser");
define("FILES_PATH", "filespath");
define("PARSED_PATH", "parsedpath");
define("LOG_FILE", "logfile");

//name prefixes of files to parse
define("CDR_PREFIX", "cdr_");
define("CMR_PREFIX", "cmr_");

//names of database tables
define("CDR_TABLE", "cdr");
define("CMR_TABLE", "cmr");

//pre-calculated 2^32 to fix overflown signed integer fields
define("SIGNED_INT32_FIXER", 4294967296);

//indices of cdr_* files entries with possibly overflown signed integer values
$cdrPossiblyOverflownIntIndices = array(7, 13, 21, 28, 35, 43, 85, 91);

$parameters = parse_ini_file(INI_FILE, true);
$logFile = $parameters[PARSER][LOG_FILE];

logMessage($logFile, "Started files parsing in directory " . $parameters[PARSER][FILES_PATH]);

$directoryEntries = scandir($parameters[PARSER][FILES_PATH]);
$files = array();
foreach ($directoryEntries as $entry) {
    if (is_file($parameters[PARSER][FILES_PATH] . DIRECTORY_SEPARATOR . $entry)) {
        $files[] = $entry;
    }
}

if (is_writeable($parameters[PARSER][FILES_PATH]) and is_writeable($parameters[PARSER][PARSED_PATH])) {

    try {
        $conn = new PDO(
            $parameters[DATABASE][URL],
            $parameters[DATABASE][USER],
            $parameters[DATABASE][PASSWORD]
        );
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
                        $records = getRecordsFromFile(
                            $parameters[PARSER][FILES_PATH] . DIRECTORY_SEPARATOR . $file,
                            $cdrPossiblyOverflownIntIndices
                        );
                        $conn->query(getInsertQuery(CDR_TABLE, $records));
                        break;

                    case strpos($file, CMR_PREFIX) !== false:
                        $records = getRecordsFromFile($parameters[PARSER][FILES_PATH] . DIRECTORY_SEPARATOR . $file);
                        $conn->query(getInsertQuery(CMR_TABLE, $records));
                        break;
                }

                if (!rename($parameters[PARSER][FILES_PATH] . DIRECTORY_SEPARATOR . $file,
                    $parameters[PARSER][PARSED_PATH] . DIRECTORY_SEPARATOR . $file)
                ) {
                    throw new Exception("Cannot move file");
                }

                $conn->commit();
                $parsedFilesCount++;
                $parsedRecordsCount += count($records);

            } catch (Exception $e) {

                $conn->rollBack();
                $errorMessage = $e instanceof PDOException ? "Not valid file format" : $e->getMessage();

                logMessage($logFile, "Parsing failure: '" . $errorMessage . "' when parsing file '" . $file . "'.");
            }
        }

        empty($files) ?
            logMessage($logFile, "No new files to parse.") :
            logMessage($logFile, "Parsed " . $parsedFilesCount . " files. Added " . $parsedRecordsCount . " records.");

    } catch (PDOException $e) {

        logMessage($logFile, "Fatal error: 'Cannot establish database connection'. Unparsed files: " . count($files) . ".");

    } finally {

        $conn = null;
    }
} else { //if FILES_PATH or PARSED_PATH are not writable

    logMessage($logFile,
        "Fatal error: 'Check write permissions for " .
        $parameters[PARSER][FILES_PATH] .
        " and " .
        $parameters[PARSER][PARSED_PATH] .
        "'. Unparsed files: " . count($files) . ".");
}


function logMessage($logFile, $message) {
    file_put_contents($logFile, date("Y-m-d H:i:s") . " " . $message . PHP_EOL, FILE_APPEND);
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

    $fileContent = file_get_contents($file);
    if ($fileContent === false) {
        throw new Exception("Check read permissions.");
    }
    //explode file content into lines
    $lines = explode(PHP_EOL, trim($fileContent));
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
