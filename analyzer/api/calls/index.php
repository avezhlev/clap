<?php

/**
 *Created by Alexandr Vezhlev.
 */

/**
 *This script receives input parameters via POST method
 *and outputs JSON filled with data from MySQL database.
 *
 *Parameters:
 *    direction        string,                    direction of a call ("from", "to", "both")
 *    number           string,                    list of masked phone numbers (e.g "1010* | 101?1 | *0")
 *    begintime        date-time string,          begin time of a call select
 *    endtime          date-time string,          end time of a call select
 *    zero             boolean,                   true if zero-duration calls should be selected
 */

require_once("inc/dao/callsdatadao.class.php");
require_once("inc/view/callsdataview.class.php");

$dao = new CallsDataDao("/etc/clap/clap.ini");
$callsData = $dao->getCallsData($_POST);
echo CallsDataView::asJSON($callsData);

?>
