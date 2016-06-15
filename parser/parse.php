<?php

	/*
	Created by Alexandr Vezhlev.
	Last modified by Alexandr Vezhlev on 2016/06/09.
	
	Script gets list of files piped via stdin from Linux find command 
	with modification time later than timestamp file modification time.
	
	Files are parsed, data is saved in mysql table.
	
	Timestamp file is modified at script end with the time of script execution start
	*/
	
	//save current time immediately after entering the script
	$scriptStartTime = time();
	
	//turn on debug messages
	ini_set("display_errors", 1);
	ini_set("display_startup_errors", 1);
	error_reporting(E_ALL);
	
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
	$cdrPossiblyOverflownIntIndices = array(7,13,21,28,35,43,85,91);
	
	//read piped list of files from stdin
	$files = array();
	while ($file = fgets(STDIN)){
		$files[] = trim($file);
	}
	
	//connect to mysql server or die painfully
	$mysqli = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME) or die(mysqli_error);
		
	//parse each file and save data into respective table
	foreach ($files as $file) {
		
		switch (true) {
			case strpos($file, CDR_PREFIX) !== false:
				parseFileIntoTable($file, $mysqli, CDR_TABLE, $cdrPossiblyOverflownIntIndices);
				break;
			case strpos($file, CMR_PREFIX) !== false:
				parseFileIntoTable($file, $mysqli, CMR_TABLE);
				break;
		}
	}
	
	//touch timestamp file with previously saved script start time
	touch(__DIR__ . "/timestamp", $scriptStartTime);
	
	
	function parseFileIntoTable($file, $mysqli, $table, $possiblyOverflownIntIndices = array()) {
		
		//explode file content into lines
		$lines = explode(PHP_EOL, trim(file_get_contents($file)));
		$entries = count($lines);
		
		//begin sql query construction
		$query = "INSERT INTO " . $table . " VALUES ";
		
		//start from line 2 because lines 0 and 1 are headers
		for ($i = 2; $i < $entries; ++$i) {
			
			//explode each line into array of strings
			$data = explode(",", trim($lines[$i]));
			
			//fix possibly overflown signed integer fields
			foreach ($possiblyOverflownIntIndices as $index) {
				if ($data[$index] < 0) {
					$data[$index] +=  SIGNED_INT32_FIXER;
				}
			}
			//set completely empty strings to "" value (to make proper sql query)
			for ($j = 0; $j < count($data); ++$j) {
				if (empty($data[$j])) {
					$data[$j] = '""';
				}
			}
			
			//combine all data into query
			$query .= "(0," . implode(",", $data) . ")" . ($i === $entries-1 ? ";" : ",");
		}
		
		//execute query
		$mysqli->query($query);
	}
	
?>
