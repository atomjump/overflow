<?php
	function trim_trailing_slash_local($str) {
        return rtrim($str, "/");
    }
    
    function add_trailing_slash_local($str) {
        //Remove and then add
        return rtrim($str, "/") . '/';
    }
    
	if(!isset($overflow_config)) {
        //Get global plugin config - but only once
		$data = file_get_contents (dirname(__FILE__) . "/config/config.json");
        if($data) {
            $overflow_config = json_decode($data, true);
            if(!isset($overflow_config)) {
                echo "Error: overflow config/config.json is not valid JSON.";
                exit(0);
            }
     
        } else {
            echo "Error: Missing config/config.json in overflow plugin.";
            exit(0);
     
        }
  
  
    }
 
	$start_path = add_trailing_slash_local($overflow_config['serverPath']);
	
	include_once($start_path . 'config/db_connect.php');	
	echo "Start path:" . $start_path . "\n";



	
	$define_classes_path = $start_path;     //This flag ensures we have access to the typical classes, before the cls.pluginapi.php is included
	
	echo "Classes path:" . $define_classes_path . "\n";
	
	require($start_path . "classes/cls.pluginapi.php");
	
	$api = new cls_plugin_api();
	
	
	

	//Create a table for overflow counting
	$sql = "CREATE TABLE IF NOT EXISTS `tbl_overflow_check` ( `int_overflow_id` int(11) NOT NULL AUTO_INCREMENT, `int_layer_id` int(10) unsigned NOT NULL, `int_current_msg_cnt` int(10) unsigned NOT NULL default 0, `int_max_messages` int(10) default NULL, `enm_due_trimming` enum('true','false') COLLATE utf8_bin DEFAULT 'false', `int_last_blurred_msg_id` int(10) unsigned default NULL, `enm_due_blurring` enum('true','false') COLLATE utf8_bin DEFAULT 'false', `time_last_trimmed` timestamp not null default now(), `int_cnt_trimmed`  int(10) unsigned NOT NULL default 0, PRIMARY KEY (`int_overflow_id`), KEY `layer` (`int_layer_id`), KEY `due_trimming` (`enm_due_trimming`), KEY `due_blurring` (`enm_due_blurring`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
	echo "Creating overflow check table. SQL:" . $sql . "\n";
	$result = $api->db_select($sql);

	//TODO: archive table, potentially.
		
	echo "Completed.\n";
	

?>
