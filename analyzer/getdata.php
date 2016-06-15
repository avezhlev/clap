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
		timezone	integer,			time zone offset in -(minutes)
		zero		boolean,			true if zero-duration calls should be selected
	*/
	
	//turn on debug messages
	ini_set("display_errors", 1);
	ini_set("display_startup_errors", 1);
	error_reporting(E_ALL);
	
	//set server-side timezone for the script
	//date_default_timezone_set('Asia/Yekaterinburg');
	
	//set names of database tables
	define("CDR_TABLE", "cdr");
	define("CMR_TABLE", "cmr");
	
	//set database credentials
	define("DB_SERVER", "localhost");
	define("DB_USER", "cucm");
	define("DB_PASSWORD", "cucmpassword");
	define("DB_NAME", "cucm");
	
	//set call parameters fields
	define("FLD_CALLING_NUMBER", "callingPartyNumber");
	define("FLD_ORIG_CALLED_NUMBER", "originalCalledPartyNumber");
	define("FLD_FINAL_CALLED_NUMBER", "finalCalledPartyNumber");
	define("FLD_CALL_BEGIN_TIME", "dateTimeOrigination");
	define("FLD_CALL_CONNECT_TIME", "dateTimeConnect");
	define("FLD_CALL_END_TIME", "dateTimeDisconnect");
	define("FLD_CALL_DURATION", "duration");
	
	//set error output text
	define("DATA_UNAVAILABLE", "Unable to retrieve data");
	
	//set result table field names
	define("OUTPUT_INDEX", "#");
	define("OUTPUT_TOTAL_DURATION", "Total duration");
	
	//set output pattern for date-time
	define("TIME_PATTERN", "Y-m-d H:i:s");
	
	
	//connect to mysql db
	$mysqli = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME) or die(mysqli_error);
	
	//initialize data to output
	$output = "";
	$index = 0;
	$totalDuration = 0;
	
	//if sql query is ok
	if ($result = $mysqli->query(getQuery())) {
		
		//add table header
		$output .= "<table>
					<tr>
						<th>" . OUTPUT_INDEX . "</th>
						<th>" . FLD_CALLING_NUMBER . "</th>
						<th>" . FLD_ORIG_CALLED_NUMBER . "</th>
						<th>" . FLD_FINAL_CALLED_NUMBER . "</th>
						<th>" . FLD_CALL_BEGIN_TIME . "</th>
						<th>" . FLD_CALL_CONNECT_TIME . "</th>
						<th>" . FLD_CALL_END_TIME . "</th>
						<th>" . FLD_CALL_DURATION . "</th>
					</tr>";
		
		//for every record in returned dataset
		while ($row = mysqli_fetch_array($result)) {
			
			//add up the total calls duration
			$totalDuration += $row[FLD_CALL_DURATION];
			
			//add table row with data
			$output .= "<tr>
						<td>" . ++$index . "</td>
						<td>" . $row[FLD_CALLING_NUMBER] . "</td>
						<td>" . $row[FLD_ORIG_CALLED_NUMBER] . "</td>
						<td>" . $row[FLD_FINAL_CALLED_NUMBER] . "</td>
						<td>" . date(TIME_PATTERN, $row[FLD_CALL_BEGIN_TIME]) . "</td>
						<td>" . ($row[FLD_CALL_CONNECT_TIME] > 0 ? date(TIME_PATTERN, $row[FLD_CALL_CONNECT_TIME]) : "") . "</td>
						<td>" . date(TIME_PATTERN, $row[FLD_CALL_END_TIME]) . "</td>
						<td>" . getDurationString($row[FLD_CALL_DURATION]) . "</td>
					</tr>";
		}
		
		//add row with total calls duration
		$output .= "<tr>
						<td colspan='7'>" . OUTPUT_TOTAL_DURATION ."</td>
						<td>" . getDurationString($totalDuration) . "</td>
					</tr>
					</table>";
	} else {
		
		//if sql query is not ok
		$output .= DATA_UNAVAILABLE;
	}
	
	//close mysql connection
	$mysqli->close();
	
	//print data output
	echo $output;
	
	
	
	function getQuery() {
		
		//set array of fields to be selected
		$fieldsToSelect = array(FLD_CALLING_NUMBER, FLD_ORIG_CALLED_NUMBER, FLD_FINAL_CALLED_NUMBER, 
								FLD_CALL_BEGIN_TIME, FLD_CALL_CONNECT_TIME, FLD_CALL_END_TIME, FLD_CALL_DURATION);
		
		//begin query construction
		$query = "SELECT " . implode(",", $fieldsToSelect) . " FROM " . CDR_TABLE;
		
		//retrieve begin and end time of data to select
		$begintime = strtotime($_GET['begintime']);
		//+ 60 * $_GET['timezone'] + $serverOffset;
		$endtime = strtotime($_GET['endtime']);
		//+ 60 * $_GET['timezone'] + $serverOffset;
		
		$query .= " WHERE " . FLD_CALL_BEGIN_TIME . " >= " . $begintime . " AND " . FLD_CALL_END_TIME . " <= " . $endtime;
		
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
		//$query = $mysqli->real_escape_string($query);
		
		return $query;
	}
	
	
	function getDurationString($duration) {
		
		$h = intval($duration / 3600);
		$m = intval(($duration - $h *3600) / 60);
		$s = $duration - $h *3600 - $m * 60;
		
		return ($h > 0 ? $h . "h " : "") . ($m > 0 ? $m . "m " : "") . $s . "s";
	}

?>
