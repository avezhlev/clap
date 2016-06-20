<?php

	/*
	Created by Alexandr Vezhlev.
	
	This script receives input parameters via GET method
	and outputs HTML table filled with data from MySQL database.
	
	Parameters:
		direction	string,				direction of a call ("from", "to", "both")
		number		string,				list of masked phone numbers (e.g "1010* | 101?1 | *0")
		begintime	date-time string,	begin time of a call select
		endtime		date-time string,	end time of a call select
		zero		boolean,			true if zero-duration calls should be selected
	*/
	
	//set database credentials
	define("DB_SERVER", "localhost");
	define("DB_USER", "cucm");
	define("DB_PASSWORD", "cucmpassword");
	define("DB_NAME", "cucm");
	
	//set names of database tables
	define("CDR_TABLE", "cdr");
	
	//set fields
	define("FLD_CALLING_NUMBER", "callingPartyNumber");
	define("FLD_ORIG_CALLED_NUMBER", "originalCalledPartyNumber");
	define("FLD_FINAL_CALLED_NUMBER", "finalCalledPartyNumber");
	define("FLD_CALL_BEGIN_TIME", "dateTimeOrigination");
	define("FLD_CALL_CONNECT_TIME", "dateTimeConnect");
	define("FLD_CALL_END_TIME", "dateTimeDisconnect");
	define("FLD_CALL_DURATION", "duration");
	
	
	//connect to mysql db
	$mysqli = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME) or die(mysqli_error($mysqli));
	
	//if sql query is ok
	if ($result = $mysqli->query(getQuery())) {
		
		//print data output
		echo getOutput($result);
		
	}
	
	//close mysql connection
	$mysqli->close();
	
	
	
	function getQuery() {
		
		//set array of fields to be selected
		$fieldsToSelect = array(FLD_CALLING_NUMBER, FLD_ORIG_CALLED_NUMBER, FLD_FINAL_CALLED_NUMBER, 
								FLD_CALL_BEGIN_TIME, FLD_CALL_CONNECT_TIME, FLD_CALL_END_TIME, FLD_CALL_DURATION);
		
		//begin query construction
		$query = "SELECT " . implode(",", $fieldsToSelect) . " FROM " . CDR_TABLE;
		
		$query .= " WHERE " . FLD_CALL_BEGIN_TIME . " >= " . $_GET['begintime'] . " AND " . FLD_CALL_END_TIME . " <= " . $_GET['endtime'];
		
		if (!empty(trim($_GET['number']))) {
			
			$numbers = explode("|", str_replace(array("*", "?"), array("%", "_"), $_GET['number']));
			$query .= " AND (";
			
			for ($i = 0, $size = sizeof($numbers); $i < $size; ++$i) {
				
				if ($i > 0) {
					$query .= " OR ";
				}
				
				$numbers[$i] = trim($numbers[$i]);
				
				if ($_GET['direction'] === "from") {
					
					$query .= FLD_CALLING_NUMBER . " LIKE '" . $numbers[$i] . "'";
					
				} else if ($_GET['direction'] === "to") {
					
					$query .= FLD_ORIG_CALLED_NUMBER . " LIKE '" . $numbers[$i] . "'" . 
					" OR " . FLD_FINAL_CALLED_NUMBER . " LIKE '" . $numbers[$i] . "'";
				} else {
					
					$query .= FLD_CALLING_NUMBER . " LIKE '" . $numbers[$i] . "'" . 
					" OR " . FLD_ORIG_CALLED_NUMBER . " LIKE '" . $numbers[$i] . "'" . 
					" OR " . FLD_FINAL_CALLED_NUMBER . " LIKE '" . $numbers[$i] . "'";
				}
			}
			$query .= ")";
		}
		
		if ($_GET['zero'] === 'false') {
			
			$query .= " AND " . FLD_CALL_DURATION . " > 0";
		}
		
		$query .= " ORDER BY " . FLD_CALL_BEGIN_TIME . ";";
		
		return $query;
	}
	
	
	function getOutput($data) {
		
		$output = array();
		
		while ($row = mysqli_fetch_assoc($data)) {
			
			$output[] = $row;
		}
		
		return json_encode($output);
	}

?>
