<?php
	/*
	echo "<pre>";
	echo var_dump($_GET);
	echo "</pre>";
	*/
	//turn on debug messages
	ini_set("display_errors", 1);
	ini_set("display_startup_errors", 1);
	error_reporting(E_ALL);
	
	//set names of database tables
	define("CDR_TABLE", "cdr");
	define("CMR_TABLE", "cmr");
	
	//set database credentials
	define("DB_SERVER", "localhost");
	define("DB_USER", "cucm");
	define("DB_PASSWORD", "cucmpassword");
	define("DB_NAME", "cucm");
	
	define("FLD_CALLING_NUMBER", "callingPartyNumber");
	define("FLD_ORIG_CALLED_NUMBER", "originalCalledPartyNumber");
	define("FLD_FINAL_CALLED_NUMBER", "finalCalledPartyNumber");
	
	$mysqli = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME) or die(mysqli_error);
	
	if (empty($_GET['number'])) {
		
		$query = "SELECT * FROM " . CDR_TABLE . ";";
		
	} else {
		
		if ($_GET['direction'] === "from") {
		
			$query = "SELECT * FROM " . CDR_TABLE . " WHERE " . FLD_CALLING_NUMBER . " = " . $_GET['number'] . ";";
			
		} else {
		
			$query = "SELECT * FROM " . CDR_TABLE . " WHERE " . FLD_ORIG_CALLED_NUMBER . " = " . $_GET['number'] . " OR " . FLD_FINAL_CALLED_NUMBER . " = " . $_GET['number'] . ";";
		}
	}
	
	if ($result = $mysqli->query($query)) {
	
		echo "<table>
				<tr>
					<th>" . FLD_CALLING_NUMBER . "</th>
					<th>" . FLD_ORIG_CALLED_NUMBER . "</th>
					<th>" . FLD_FINAL_CALLED_NUMBER . "</th>
				</tr>";
	
		while ($row = mysqli_fetch_array($result)) {
			
			echo "<tr>";
			echo "<td>" . $row[FLD_CALLING_NUMBER] . "</td>";
			echo "<td>" . $row[FLD_ORIG_CALLED_NUMBER] . "</td>";
			echo "<td>" . $row[FLD_FINAL_CALLED_NUMBER] . "</td>";
			echo "</tr>";
		}
		
		echo "</table>";
		
	}
	
	$mysqli->close();

?>
