<?php

class CallsDataDao {

	//set database credentials
	const DB_SERVER = "localhost";
	const DB_USER = "cucm";
	const DB_PASSWORD = "cucmpassword";
	const DB_NAME = "cucm";
	
	//set names of database tables
	const CDR_TABLE = "cdr";
	
	//set fields
	const FLD_CALLING_NUMBER = "callingPartyNumber";
	const FLD_ORIG_CALLED_NUMBER = "originalCalledPartyNumber";
	const FLD_FINAL_CALLED_NUMBER = "finalCalledPartyNumber";
	const FLD_CALL_BEGIN_TIME = "dateTimeOrigination";
	const FLD_CALL_CONNECT_TIME = "dateTimeConnect";
	const FLD_CALL_END_TIME = "dateTimeDisconnect";
	const FLD_CALL_DURATION = "duration";


	static function getCallsData($data) {

		$callsData = array();
		//connect to mysql db
		$mysqli = new mysqli(self::DB_SERVER, self::DB_USER, self::DB_PASSWORD, self::DB_NAME) or die(mysqli_error($mysqli));

		//if sql query is ok
		if ($result = $mysqli->query(self::getQuery($data))) {

			while ($row = mysqli_fetch_assoc($result)) {
				$callsData[] = $row;
			}
			
		}
		//close mysql connection
		$mysqli->close();

		return $callsData;
	}

	static function getQuery($data) {
		
		//set array of fields to be selected
		$fieldsToSelect = array(self::FLD_CALLING_NUMBER, self::FLD_ORIG_CALLED_NUMBER, self::FLD_FINAL_CALLED_NUMBER, 
								self::FLD_CALL_BEGIN_TIME, self::FLD_CALL_CONNECT_TIME, self::FLD_CALL_END_TIME, self::FLD_CALL_DURATION);
		
		//begin query construction
		$query = "SELECT " . implode(",", $fieldsToSelect) . " FROM " . self::CDR_TABLE;
		
		$query .= " WHERE " . self::FLD_CALL_BEGIN_TIME . " >= " . $data['begintime'] . " AND " . self::FLD_CALL_END_TIME . " <= " . $data['endtime'];
		
		if (!empty(trim($data['number']))) {
			
			$numbers = explode("|", str_replace(array("*", "?"), array("%", "_"), $data['number']));
			$query .= " AND (";
			
			for ($i = 0, $size = sizeof($numbers); $i < $size; ++$i) {
				
				if ($i > 0) {
					$query .= " OR ";
				}
				
				$numbers[$i] = trim($numbers[$i]);
				
				if ($data['direction'] === "from") {
					
					$query .= self::FLD_CALLING_NUMBER . " LIKE '" . $numbers[$i] . "'";
					
				} else if ($data['direction'] === "to") {
					
					$query .= self::FLD_ORIG_CALLED_NUMBER . " LIKE '" . $numbers[$i] . "'" . 
					" OR " . self::FLD_FINAL_CALLED_NUMBER . " LIKE '" . $numbers[$i] . "'";
				} else {
					
					$query .= self::FLD_CALLING_NUMBER . " LIKE '" . $numbers[$i] . "'" . 
					" OR " . self::FLD_ORIG_CALLED_NUMBER . " LIKE '" . $numbers[$i] . "'" . 
					" OR " . self::FLD_FINAL_CALLED_NUMBER . " LIKE '" . $numbers[$i] . "'";
				}
			}
			$query .= ")";
		}
		
		if ($data['zero'] === 'false') {
			
			$query .= " AND " . self::FLD_CALL_DURATION . " > 0";
		}
		
		$query .= " ORDER BY " . self::FLD_CALL_BEGIN_TIME . ";";
		
		return $query;
	}

}

?>
