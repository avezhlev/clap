#!/bin/bash

logs_location="/var/ftp"
script_location="/usr/scripts/cucm/parser"

find $logs_location -newer $script_location/timestamp -type f -printf "%T+\t%p\n" | sort | awk '{print $2;}' | php $script_location/parse.php
