/*!50530 SET @@SESSION.PSEUDO_SLAVE_MODE=1*/;
/*!40019 SET @@session.max_delayed_threads=0*/;
/*!50003 SET @OLD_COMPLETION_TYPE=@@COMPLETION_TYPE,COMPLETION_TYPE=0*/;
DELIMITER /*!*/;
# at 4
#260711  4:02:09 server id 1  end_log_pos 127 CRC32 0x993523c3 	Start: binlog v 4, server v 8.4.10 created 260711  4:02:09 at startup
# Warning: this binlog is either in use or was not closed properly.
ROLLBACK/*!*/;
BINLOG '
QcBRag8BAAAAewAAAH8AAAABAAQAOC40LjEwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAABBwFFqEwANAAgAAAAABAAEAAAAYwAEGggAAAAAAAACAAAACgoKKioAEjQA
CigAAAHDIzWZ
'/*!*/;
# at 292371
#260711  4:50:12 server id 1  end_log_pos 292450 CRC32 0xf65b645e 	Ignorable
# Ignorable event type 34 (MySQL Anonymous_Gtid)
# at 292450
#260711  4:50:12 server id 1  end_log_pos 292539 CRC32 0x7aa718ec 	Query	thread_id=793	exec_time=0	error_code=0	xid=0
SET TIMESTAMP=1783745412/*!*/;
SET @@session.pseudo_thread_id=793/*!*/;
SET @@session.foreign_key_checks=1, @@session.sql_auto_is_null=0, @@session.unique_checks=1, @@session.autocommit=1, @@session.check_constraint_checks=1, @@session.system_versioning_insert_history=0/*!*/;
SET @@session.sql_mode=1168113696/*!*/;
SET @@session.auto_increment_increment=1, @@session.auto_increment_offset=1/*!*/;
/*!\C latin1 *//*!*/;
SET @@session.character_set_client=latin1,@@session.collation_connection=8,@@session.collation_server=255/*!*/;
SET @@session.time_zone='SYSTEM'/*!*/;
SET @@session.lc_time_names=0/*!*/;
SET @@session.collation_database=DEFAULT/*!*/;
BEGIN
/*!*/;
# at 292539
#260711  4:50:12 server id 1  end_log_pos 292619 CRC32 0xbaaa3782 	Table_map: `pesantenku`.`system_settings` mapped to number 880
# at 292619
#260711  4:50:12 server id 1  end_log_pos 292793 CRC32 0xc5c3495a 	Update_rows: table id 880 flags: STMT_END_F

BINLOG '
hMtRahMBAAAAUAAAAAt3BAAAAHADAAAAAAEACnBlc2FudGVua3UAD3N5c3RlbV9zZXR0aW5ncwAG
CAgP9RERBfwDBAAAOgEBwAIB4II3qro=
hMtRah8BAAAArgAAALl3BAAAAHADAAAAAAEAAgAG//8ACwAAAAAAAAABAAAAAAAAABEAcGl0ci5k
cmlsbC5tYXJrZXIVAAAADBNwaXRyLTIwMjYwNzExMDQ0NjQxalHBxWpRyrEACwAAAAAAAAABAAAA
AAAAABEAcGl0ci5kcmlsbC5tYXJrZXIVAAAADBNwaXRyLTIwMjYwNzExMDQ1MDExalHBxWpRy4Ra
ScPF
'/*!*/;
# Number of rows: 1
# at 292793
#260711  4:50:12 server id 1  end_log_pos 292824 CRC32 0x42c2a04a 	Xid = 11828
COMMIT/*!*/;
DELIMITER ;
# End of log file
ROLLBACK /* added by mysqlbinlog */;
/*!50003 SET COMPLETION_TYPE=@OLD_COMPLETION_TYPE*/;
/*!50530 SET @@SESSION.PSEUDO_SLAVE_MODE=0*/;
