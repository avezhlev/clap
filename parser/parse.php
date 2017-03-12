<?php

/**
 *Created by Alexandr Vezhlev.
 */

/**
 *Script instances CucmLogsParser that loads its parameters
 * and sets log file location from constructor arguments,
 * then parses CUCM log files into specific MySQL tables
 * and, if successful, moves them to a specific directory.
 */

$parser = new CucmLogsParser("/etc/clap/clap.ini", "/var/log/clap/parse.log");
$parser->parse();

class CucmLogsParser {

    //name prefixes of files to parse
    const CDR_PREFIX = "cdr_";
    const CMR_PREFIX = "cmr_";

    //names of database tables
    const CDR_TABLE = "cdr";
    const CMR_TABLE = "cmr";

    //pre-calculated 2^32 to fix overflown signed integer fields
    const SIGNED_INT32_FIXER = 4294967296;

    //indices of cdr_* files entries columns with possibly overflown signed integer values
    const CDR_POSSIBLY_OVERFLOWN_INT_INDICES = array(7, 13, 21, 28, 35, 43, 85, 91);

    private $filesPath;
    private $parsedPath;
    private $databaseUrl;
    private $databaseUser;
    private $databasePassword;

    private $logFile;

    public function __construct($iniFile, $logFile) {
        $this->logFile = $logFile;
        $this->init($iniFile);
    }

    private function init($iniFile) {
        $parameters = parse_ini_file($iniFile, true);
        if ($parameters !== false) {
            $this->filesPath = $parameters['parser']['filesPath'];
            $this->parsedPath = $parameters['parser']['parsedPath'];
            $this->databaseUrl = $parameters['database']['url'];
            $this->databaseUser = $parameters['database']['user'];
            $this->databasePassword = $parameters['database']['password'];
        }
    }

    public function parse() {
        $this->logMessage("Started files parsing in directory " . $this->filesPath);

        $directoryEntries = scandir($this->filesPath);
        $files = array();
        foreach ($directoryEntries as $entry) {
            if (is_file($this->filesPath . DIRECTORY_SEPARATOR . $entry)) {
                $files[] = $entry;
            }
        }

        if (is_writeable($this->filesPath) and is_writeable($this->parsedPath)) {

            try {
                $conn = new PDO($this->databaseUrl, $this->databaseUser, $this->databasePassword);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $parsedFilesCount = 0;
                $parsedRecordsCount = 0;
                //parse each file and save data into respective table
                foreach ($files as $file) {

                    try {
                        $conn->beginTransaction();
                        $records = null;

                        switch (true) {

                            case strpos($file, self::CDR_PREFIX) !== false:
                                $records = $this->getRecordsFromFile(
                                    $this->filesPath . DIRECTORY_SEPARATOR . $file,
                                    self::CDR_POSSIBLY_OVERFLOWN_INT_INDICES
                                );
                                $conn->query($this->getInsertQuery(self::CDR_TABLE, $records));
                                break;

                            case strpos($file, self::CMR_PREFIX) !== false:
                                $records = $this->getRecordsFromFile(
                                    $this->filesPath . DIRECTORY_SEPARATOR . $file
                                );
                                $conn->query($this->getInsertQuery(self::CMR_TABLE, $records));
                                break;
                        }

                        if (!rename($this->filesPath . DIRECTORY_SEPARATOR . $file,
                            $this->parsedPath . DIRECTORY_SEPARATOR . $file)
                        ) {
                            throw new Exception("Cannot move file");
                        }

                        $conn->commit();
                        $parsedFilesCount++;
                        $parsedRecordsCount += count($records);

                    } catch (Exception $e) {

                        $conn->rollBack();
                        $errorMessage = $e instanceof PDOException ? "Not valid file format" : $e->getMessage();

                        $this->logMessage("Parsing failure: '" . $errorMessage . "' when parsing file '" . $file . "'.");
                    }
                }

                empty($files) ?
                    $this->logMessage("No new files to parse.") :
                    $this->logMessage("Parsed " . $parsedFilesCount . " files. Added " . $parsedRecordsCount . " records.");

            } catch (PDOException $e) {

                $this->logMessage("Fatal error: 'Cannot establish database connection'. Unparsed files: " . count($files) . ".");

            } finally {

                $conn = null;
            }
        } else { //if $filesPath or $parsedPath are not writable

            $this->logMessage(
                "Fatal error: 'Check write permissions for " . $this->filesPath . " and " . $this->parsedPath . "'. Unparsed files: " . count($files) . ".");
        }
    }

    private function logMessage($message) {
        file_put_contents($this->logFile, date("Y-m-d H:i:s") . " " . $message . PHP_EOL, FILE_APPEND);
    }

    private function getInsertQuery($table, $records) {

        $query = "INSERT INTO " . $table . " VALUES ";
        foreach ($records as $record) {
            $query .= "(0," . implode(",", $record) . "),";
        }
        $query = substr($query, 0, -1) . ";";

        return $query;
    }

    private function getRecordsFromFile($file, $possiblyOverflownIntIndices = array()) {

        $records = array();

        $fileContent = file_get_contents($file);
        if ($fileContent === false) {
            throw new Exception("Check read permissions");
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
                    $record[$index] += self::SIGNED_INT32_FIXER;
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
}

?>
