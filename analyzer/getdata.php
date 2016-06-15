<?php

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
	
	define("OUTPUT_INDEX", "#");
	define("OUTPUT_TOTAL_DURATION", "Total duration");
	
	//set output pattern for date-time
	define("TIME_PATTERN", "Y-m-d H:i:s");
	
	
	$mysqli = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME) or die(mysqli_error);
	
	$begintime = strtotime($_GET['begintime']);
	//+ 60 * $_GET['timezone'] + $serverOffset;
	$endtime = strtotime($_GET['endtime']);
	//+ 60 * $_GET['timezone'] + $serverOffset;
	
	$query = "SELECT * FROM " . CDR_TABLE;
	$query .= " WHERE " . FLD_CALL_BEGIN_TIME . " >= " . $begintime . " AND " . FLD_CALL_END_TIME . " <= " . $endtime;
	
	if (!empty($_GET['number'])) {
		
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
	
	$output = "";
	$index = 0;
	$totalDuration = 0;
	
	if ($result = $mysqli->query($query)) {
		
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
	
		while ($row = mysqli_fetch_array($result)) {
			
			$totalDuration += $row[FLD_CALL_DURATION];
			
			$output .= "<tr>
						<td>" . ++$index . "</td>
						<td>" . $row[FLD_CALLING_NUMBER] . "</td>
						<td>" . $row[FLD_ORIG_CALLED_NUMBER] . "</td>
						<td>" . $row[FLD_FINAL_CALLED_NUMBER] . "</td>
						<td>" . date(TIME_PATTERN, $row[FLD_CALL_BEGIN_TIME]) . "</td>
						<td>" . ($row[FLD_CALL_CONNECT_TIME] > 0 ? date(TIME_PATTERN, $row[FLD_CALL_CONNECT_TIME]) : "") . "</td>
						<td>" . date(TIME_PATTERN, $row[FLD_CALL_END_TIME]) . "</td>
						<td>" . $row[FLD_CALL_DURATION] . "</td>
					</tr>";
		}
		
		$output .= "<tr>
						<td colspan='7'>" . OUTPUT_TOTAL_DURATION ."</td>
						<td>" . $totalDuration . "</td>
					</tr>
					</table>";
	} else {
		
		$output .= DATA_UNAVAILABLE;
	}
	
	$mysqli->close();
	
	echo $output;

?>
