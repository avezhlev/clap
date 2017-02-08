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

		try {
			//connect to mysql db
			$conn = new PDO("mysql:host=" . self::DB_SERVER .";dbname=" . self::DB_NAME, self::DB_USER, self::DB_PASSWORD);
	    	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	    	$query = self::getQuery($data);
	    	$stmt = $conn->prepare($query["sql"]);
	    	//if sql query is ok
	    	if ($stmt->execute($query["params"])) {
	    		$callsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
	    	}
	    } catch(PDOException $e) {
		    $callsData[] = array("Error: " => $e->getMessage());
		}

		$conn = null;

		return $callsData;
	}



	static function getQuery($data) {
		
		//set array of fields to be selected
		$fieldsToSelect = array(self::FLD_CALLING_NUMBER, self::FLD_ORIG_CALLED_NUMBER, self::FLD_FINAL_CALLED_NUMBER, 
								self::FLD_CALL_BEGIN_TIME, self::FLD_CALL_CONNECT_TIME, self::FLD_CALL_END_TIME, self::FLD_CALL_DURATION);
		
		//begin query and params array construction
		$sql = "SELECT " . implode(",", $fieldsToSelect) . " FROM " . self::CDR_TABLE;
		$params = array();
		
		$sql .= " WHERE " . self::FLD_CALL_BEGIN_TIME . " >= ? AND " . self::FLD_CALL_END_TIME . " <= ?";
		$params[] = $data['begintime'];
		$params[] = $data['endtime'];
		
		if (!empty(trim($data['number']))) {
			
			$numbers = explode("|", str_replace(array("*", "?"), array("%", "_"), $data['number']));
			$sql .= " AND (";
			
			for ($i = 0, $size = sizeof($numbers); $i < $size; ++$i) {
				
				if ($i > 0) {
					$sql .= " OR ";
				}
				
				$numbers[$i] = trim($numbers[$i]);
				
				if ($data['direction'] === "from") {
					
					$sql .= self::FLD_CALLING_NUMBER . " LIKE ?";
					$params[] = $numbers[$i];
					
				} else if ($data['direction'] === "to") {
					
					$sql .= self::FLD_ORIG_CALLED_NUMBER . " LIKE ?" .
					" OR " . self::FLD_FINAL_CALLED_NUMBER . " LIKE ?";
					$params[] = $numbers[$i];
					$params[] = $numbers[$i];

				} else {
					
					$sql .= self::FLD_CALLING_NUMBER . " LIKE ?" .
					" OR " . self::FLD_ORIG_CALLED_NUMBER . " LIKE ?" .
					" OR " . self::FLD_FINAL_CALLED_NUMBER . " LIKE ?";
					$params[] = $numbers[$i];
					$params[] = $numbers[$i];
					$params[] = $numbers[$i];
				}
			}
			$sql .= ")";
		}
		
		if ($data['zero'] === 'false') {
			
			$sql .= " AND " . self::FLD_CALL_DURATION . " > 0";
		}
		
		$sql .= " ORDER BY " . self::FLD_CALL_BEGIN_TIME . ";";
		
		return array("sql" => $sql, "params" => $params);
	}

}

?>
