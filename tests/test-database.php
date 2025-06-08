$db = new MomBookingDatabase();
$db->create_tables();
var_dump($db->tables_exist());
