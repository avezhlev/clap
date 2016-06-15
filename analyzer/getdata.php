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
	define("FLD_CALL_END_TIME", "dateTimeDisconnect");
	define("FLD_CALL_DURATION", "duration");
	
	//set error output text
	define("DATA_UNAVAILABLE", "Unable to retrieve data");
	
	define("TIME_PATTERN", "Y-m-d H:i:s");
	
	$mysqli = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME) or die(mysqli_error);
	
	$begintime = strtotime($_GET['begintime']);
	//+ 60 * $_GET['timezone'] + $serverOffset;
	$endtime = strtotime($_GET['endtime']);
	//+ 60 * $_GET['timezone'] + $serverOffset;
	
	$query = "SELECT * FROM " . CDR_TABLE;
	$query .= " WHERE " . FLD_CALL_BEGIN_TIME . " >= " . $begintime . " AND " . FLD_CALL_END_TIME . " <= " . $endtime;
	
	if (!empty($_GET['number'])) {
		
		if ($_GET['direction'] === "from") {
		
			$query .= " AND " . FLD_CALLING_NUMBER . " = " . $_GET['number'];
			
		} else {
		
			$query .= " AND (" . FLD_ORIG_CALLED_NUMBER . " = " . $_GET['number'] . " OR " . FLD_FINAL_CALLED_NUMBER . " = " . $_GET['number'] . ")";
		}
	}
	
	$query .= ";";
	
	$output = "";
	
	if ($result = $mysqli->query($query)) {
		
		$output .= "<table>
					<tr>
						<th>" . FLD_CALLING_NUMBER . "</th>
						<th>" . FLD_ORIG_CALLED_NUMBER . "</th>
						<th>" . FLD_FINAL_CALLED_NUMBER . "</th>
						<th>" . FLD_CALL_BEGIN_TIME . "</th>
						<th>" . FLD_CALL_END_TIME . "</th>
						<th>" . FLD_CALL_DURATION . "</th>
					</tr>";
	
		while ($row = mysqli_fetch_array($result)) {
			
			$output .= "<tr>
						<td>" . $row[FLD_CALLING_NUMBER] . "</td>
						<td>" . $row[FLD_ORIG_CALLED_NUMBER] . "</td>
						<td>" . $row[FLD_FINAL_CALLED_NUMBER] . "</td>
						<td>" . date(TIME_PATTERN, $row[FLD_CALL_BEGIN_TIME]) . "</td>
						<td>" . date(TIME_PATTERN, $row[FLD_CALL_END_TIME]) . "</td>
						<td>" . $row[FLD_CALL_DURATION] . "</td>
					</tr>";
		}
		
		$output .= "</table>";
		
	} else {
		
		$output .= DATA_UNAVAILABLE;
	}
	
	$mysqli->close();
	
	echo $output;

?>
