<?php

class CallsDataDao {
	
	//names of database tables
	const CDR_TABLE = "cdr";
	
	//table fields
	const FLD_CALLING_NUMBER = "callingPartyNumber";
	const FLD_ORIG_CALLED_NUMBER = "originalCalledPartyNumber";
	const FLD_FINAL_CALLED_NUMBER = "finalCalledPartyNumber";
	const FLD_CALL_BEGIN_TIME = "dateTimeOrigination";
	const FLD_CALL_CONNECT_TIME = "dateTimeConnect";
	const FLD_CALL_END_TIME = "dateTimeDisconnect";
	const FLD_CALL_DURATION = "duration";

    const ERROR_MSG_SQL_SERVER_ISSUE = "SQL service unavailable";
	const ERROR_MSG_MAX_RECORDS_EXCEEDED = "The number of entries exceeds ";
    const INFO_MSG_NARROW_CONDITIONS = "Please narrow selection conditions";
	const INFO_MSG_NO_DATA = "No data for these conditions";

    private $databaseUrl;
    private $databaseUser;
    private $databasePassword;

    private $maxRecords;

    public function __construct($iniFile) {
        $this->init($iniFile);
    }

    private function init($iniFile) {
        $parameters = parse_ini_file($iniFile, true);
        if ($parameters !== false) {
            $this->databaseUrl = $parameters['database']['url'];
            $this->databaseUser = $parameters['database']['user'];
            $this->databasePassword = $parameters['database']['password'];
            $this->maxRecords = $parameters['analyzer']['maxRecords'];
        }
    }

    public function getCallsData($data) {

		$callsData = array();

		try {
			//connect to mysql db
			$conn = new PDO($this->databaseUrl, $this->databaseUser, $this->databasePassword);
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			$query = $this->getQuery($data);
			$stmt = $conn->prepare($query["sql"]);
			//if sql query is ok
			if ($stmt->execute($query["params"])) {
				$i = 0;
				while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					++$i;
					if ($i > $this->maxRecords) {
						return array(array(
						    "Warning" => self::ERROR_MSG_MAX_RECORDS_EXCEEDED . $this->maxRecords,
                            "Info" => self::INFO_MSG_NARROW_CONDITIONS
                        ));
					}
					$callsData[] = $row;
				}
				if ($i == 0) {
					return array(array("Info" => self::INFO_MSG_NO_DATA));
				}
			}
		} catch(PDOException $e) {

			if (is_null($conn)) {
				return array(array("Error: " => self::ERROR_MSG_SQL_SERVER_ISSUE));
			} else {
				return array(array("Error: " => $e->getMessage()));
			}

		} finally {

			$stmt = null;
			$conn = null;
		}

		return $callsData;
	}



	private function getQuery($data) {
		
		//set array of fields to be selected
		$fieldsToSelect = array(self::FLD_CALLING_NUMBER, self::FLD_ORIG_CALLED_NUMBER, self::FLD_FINAL_CALLED_NUMBER, 
								self::FLD_CALL_BEGIN_TIME, self::FLD_CALL_CONNECT_TIME, self::FLD_CALL_END_TIME, self::FLD_CALL_DURATION);
		
		//begin query and params array construction
		$sql = "SELECT " . implode(",", $fieldsToSelect) . " FROM " . self::CDR_TABLE;
		$params = array();
		
		$sql .= " WHERE " . self::FLD_CALL_END_TIME . " >= ? AND " . self::FLD_CALL_BEGIN_TIME . " <= ?";
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
