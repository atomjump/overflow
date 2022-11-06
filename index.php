<?php
    include_once("classes/cls.pluginapi.php");
    
	if(!isset($notifications_config)) {
        //Get global plugin config - but only once
		$data = file_get_contents (dirname(__FILE__) . "/config/config.json");
        if($data) {
            $overflow_config = json_decode($data, true);
            if(!isset($notifications_config)) {
                echo "Error: overflow config/config.json is not valid JSON.";
                exit(0);
            }
     
        } else {
            echo "Error: Missing config/config.json in overflow plugin.";
            exit(0);
     
        }
    }
    
    
    class plugin_medimage_export
    {
        public $verbose = false;
     	
     	private function trim_trailing_slash_local($str) {
        	return rtrim($str, "/");
    	}
    
    	private function add_trailing_slash_local($str) {
        	//Remove and then add
        	return rtrim($str, "/") . '/';
   		}  
     
 		public function on_message($message_forum_id, $message, $message_id, $sender_id, $recipient_id, $sender_name, $sender_email, $sender_phone)
        {
            global $cnf;
            $api = new cls_plugin_api();
               
            //Increment the count if a count already exists for this forum, else create a count and set as 1.  
            //When creating a count:
            //determine the type of forum and use the default message limit in the config file and set this in the db record.
            //This can be manually adjusted in the database later, or TODO: have a user/admin facility for altering the max message count.
            
            //Check the forum count exists
            $new_msg_cnt = 1;		//Default
            $due_a_trimming = "false";		//Default
            $sql = "SELECT * FROM tbl_overflow_check WHERE int_layer_id = " . clean_data($message_forum_id);
            $result = $api->db_select($sql);
			if($row = $api->db_fetch_array($result))
			{
				//Yes, we have a count here already.
            	if($row['int_current_msg_cnt']) {
            		//Set the new message count to be one greater than the current message count
            		$new_msg_cnt = $row['int_current_msg_cnt'] + 1;
            	}
            	
            	if(isset($row['int_max_messages'])) {
            		$trigger_over_limit = 10;	//default
            		if($overflow_config['triggerOverLimit']) {
            			$trigger_over_limit = $overflow_config['triggerOverLimit'];
            		}
            		
         		 	//If this count is beyond the forum limit, flag the forum as being "due a trimming". It will be trimmed on the next CRON run.
            		if($new_msg_cnt > ($row['int_max_messages'] + $trigger_over_limit)) {
            			//Need to flag as 'due for a trimming'
            			$due_a_trimming = "true";
            		
            		}
            	} else {
            		//A null max messages - no limit - never trim
            		$due_a_trimming = "false";
            	}
            	
            	//Set the new message count, and the 'due a trimming' flag
            	$result = $api->db_update("tbl_overflow_check", "int_current_msg_cnt = " . $new_msg_cnt . ",enm_due_trimming = '" . $due_a_trimming . "' WHERE int_layer_id = " . clean_data($message_forum_id));		
           
            } else {
            	//TODO: Check what type of forum this is, public or private.
            	$type = "public";
            	$max_messages = 50;		//Default
            	
            	if((isset($overflow_config['publicForumLimit']))&&($type == "public")) {
            		if(is_null($overflow_config['publicForumLimit'])) {
            			$max_messages = "NULL";
            		} else {
            			$max_messages = $overflow_config['publicForumLimit'];
            		}
            	}
            	
            	if(($overflow_config['privateForumLimit'])&&($type == "private")) {
            		if(is_null($overflow_config['privateForumLimit'])) {
            			$max_messages = "NULL";
            		} else {
            			$max_messages = $overflow_config['privateForumLimit'];
            		}
            	}
            	
            	//Create a new overflow entry for this forum
            	$sql = "INSERT INTO tbl_overflow ( `int_overflow_id`,  `int_layer_id`, `int_current_msg_cnt`, 'int_max_messages', 'enm_due_trimming') VALUES (null, " . clean_data($message_forum_id) . ", 1, " . clean_data($max_messages) . ",'false')";
            	$result = $api->db_select($sql);
            }
            
            return true;
                
        }
    }
?>
