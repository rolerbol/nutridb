#!/usr/bin/php

<?php

/**
 * Script to update nutridb table weights which corresponds
 * to USDA file FOOTNOTE, which corresponds to update files
 * DEL_WGT.txt and ADD_WGT.txt and CHG_WGT.txt.
 *
 * NOTE: This script relies on the fact that updates files
 * are named as they were from the SR19 -> SR20 update.
 */

require("config.php");

$fh_del = fopen("{$updatesDir}/DEL_WGT.txt", "r");
$fh_add = fopen("{$updatesDir}/ADD_WGT.txt", "r");
$fh_chg = fopen("{$updatesDir}/CHG_WGT.txt", "r");

# First handle deletions
fwrite($fh_log, "### PROCESSING DEL_WGT.txt ###\n");
$idx = 0;
while ( ($row = fgetcsv($fh_del, 0, $delimiter, $enclosure)) !== FALSE ) {
	$sql = sprintf("
		UPDATE weights
		SET usda_status = 'deleted'
		WHERE ndb_no = '%s'
			AND seq = '%s'
		",
		$row[0],
		$row[1]
	);
	$db->Modify($sql);
	if ( $db->_error ) {
		fwrite($fh_log, "\tERROR deactivating ndb_no {$row[0]}: $db->_error\n");
	} elseif ( $db->_affectedRows == 0 ) {
		fwrite($fh_log, "\tWARNING: ndb_no {$row[0]} was not found in the database.\n");
	} else {
		$idx++;
	}
}
fwrite($fh_log, "\tDeactivated $idx records from table weights.\n");

# Now handle additions
fwrite($fh_log, "### PROCESSING ADD_WGT.txt ###\n");
$idx = 0;
while ( ($row = fgetcsv($fh_add, 0, $delimiter, $enclosure)) !== FALSE ) {
	# Don't add the record if this ndb_no already exists
	$sql = sprintf("
		SELECT ndb_no FROM weights
		WHERE ndb_no = '%s'
			AND seq = '%s'
			AND amount = '%s'
			AND msre_desc = '%s'
			AND gm_wgt = '%s'
		",
		$row[0],
		$row[1],
		$row[2],
		$db->EscapeString($row[3]),
		$row[4]
	);
	$db->Select($sql);
	if ( $db->_rowCount != 0 ) {
		fwrite($fh_log, "\tWARNING not adding weight for ndb_no {$row[0]} because it already exists\n");
		continue;
	}

	$sql = sprintf("
		INSERT INTO weights(ndb_no, seq, amount, msre_desc, gm_wgt, num_data_pts, std_dev)
		VALUES('%s','%s','%s','%s','%s','%s','%s')
		",
		$row[0],
		$row[1],
		$row[2],
		$db->EscapeString($row[3]),
		$row[4],
		$row[5],
		$row[6]
	);
	$db->Modify($sql);
	if ( $db->_error ) {
		fwrite($fh_log, "\tERROR adding weight for ndb_no {$row[0]}: $db->_error\n");
	} else {
		$idx++;
	}
}
fwrite($fh_log, "\tAdded $idx records to table footnotes\n");

# Now make updates
fwrite($fh_log, "### PROCESSING CHG_WGT.txt ###\n");
$idx = 0;
while ( ($row = fgetcsv($fh_chg, 0, $delimiter, $enclosure)) !== FALSE ) {
	$sql = sprintf("
		UPDATE weights
		SET
			ndb_no = '%s',
			seq = '%s',
			amount = '%s',
			msre_desc = '%s',
			gm_wgt = '%s',
			num_data_pts = '%s',
			std_dev = '%s'
		WHERE ndb_no = '%s'
			AND seq = '%s'
		",
		$row[0],
		$row[1],
		$row[2],
		$db->EscapeString($row[3]),
		$row[4],
		$row[5],
		$row[6],
		$row[0],
		$row[1]
	);
	$db->Modify($sql);
	if ( $db->_error ) {
		fwrite($fh_log, "\tERROR modifying ndb_no {$row[0]}: $db->_error\n");
	} elseif ( $db->_affectedRows != 1 ) {
		fwrite($fh_log, "\tWARNING: nothing modified for ndb_no {$row[0]}.\n");
	} else {
		$idx++;
	}
}
fwrite($fh_log, "\tUpdated $idx records in table weights.\n");

?>
