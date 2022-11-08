<?php
    include_once("classes/cls.pluginapi.php");
    
	/*if(!isset($overflow_config)) {
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
    }*/
    
    
    class plugin_overflow
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
        	if(!isset($overflow_config)) {
                //Get global plugin config - but only once
                global $cnf;
                
                $path = dirname(__FILE__) . "/config/config.json";
                
                
	            $data = file_get_contents($path);
	            
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
        
        
            global $cnf;
            
            
          
            
           
            error_log("After setting config:" . $overflow_config['publicForumLimit']);
            
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
            	
            	//Check if we are over the main message limit, and flag if so for future trimming.
            	if(isset($row['int_max_messages'])) {
            		$max_messages = $row['int_max_messages'];
            		$trigger_over_limit = 10;	//default
            		if($overflow_config['triggerOverLimit']) {
            			$trigger_over_limit = $overflow_config['triggerOverLimit'];
            		}
            		
         		 	//If this count is beyond the forum limit, flag the forum as being "due a trimming". It will be trimmed on the next CRON run.
            		if($new_msg_cnt > ($max_messages + $trigger_over_limit)) {
            			//Need to flag as 'due for a trimming'
            			$due_a_trimming = "true";
            		
            		}
            	} else {
            		//A null max messages - no limit - never trim
            		$max_messages = null;
            		$due_a_trimming = "false";
            	}
            	
            	
            	//Check if we are over the 70% message limit for the 1st time in this forum (note: not future times), and
            	//display a note to the user of the limit.
            	if($max_messages) {
		        	$seventy_perc_msg_num = intval(0.7 * ($max_messages+$trigger_over_limit));
		        	error_log("Seventy perc = " . $seventy_perc_msg_num .  "  New msg cnt = " . $new_msg_cnt . "  Cnt trimmed = " . $row['int_cnt_trimmed']);		//TESTING
		        	if(($row['int_cnt_trimmed'] == 0)&&($new_msg_cnt == $seventy_perc_msg_num)) {
		        		  $new_message = "Warning! This forum only keeps the latest " . $max_messages . " messages, and you have reached 70% of that number - the oldest will be removed as you enter new ones. If you want to save older messages you can 'export' them at any time.  To increase the maximum number of messages on the forum please enter 'overflow x' where x is the number, but please keep in mind that you are sharing resources with other users.";		//TODO: x can be up to 'y' maximum.
						  $recipient_ip_colon_id = "";		//No recipient, so the whole group. 123.123.123.123:" . $recipient_id;
						  $sender_name_str = "AtomJump";
						  $sender_email = "webmaster@atomjump.com";
						  $sender_ip = "111.111.111.111";
						  $options = array('notification' => false, 'allow_plugins' => false);
					   	$api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
		        	}
		        }
            	
            	//Set the new message count, and the 'due a trimming' flag
            	$result = $api->db_update("tbl_overflow_check", "int_current_msg_cnt = " . $new_msg_cnt . ",enm_due_trimming = '" . $due_a_trimming . "' WHERE int_layer_id = " . clean_data($message_forum_id));		
           
            } else {
            	//There is no existing overflow record for this forum.
            	//Check what type of forum this is, public or private.
            	//If tbl_layer field 'var_public_code' is set then it is a 'private forum', in this sense (as it has a password access).
            	//A NULL 'var_public_code' means it is a purely 'public forum' in this sense.
            	$type = "public";
            	$sql = "SELECT var_public_code FROM tbl_layer WHERE int_layer_id = " . clean_data($message_forum_id);
		        $result = $api->db_select($sql);
				if($row = $api->db_fetch_array($result))
				{
					if(isset($row['var_public_code'])) {
						//I.e. not a null value
							$type = "private";
					}
				}
				//Note: if the status of this forum changes from private to public or vica-versa, this value will not be automatically
				//updated. You would still need to update the tbl_overflow_check table specifically.
            	
            	
            	
            	$max_messages = 50;		//Default
            	$max_messages_disp = 50;		//Default
            	
            	error_log("Max messages before:" . $max_messages);
            	if((isset($overflow_config['publicForumLimit']))&&($type == "public")) {
            	
            		error_log("Type = public. New max messages will be " . $overflow_config['publicForumLimit']);		//TESTING
            	
            		if(is_null($overflow_config['publicForumLimit'])) {
            			$max_messages = null;
            			$max_messages_disp = "NULL";
            		} else {
            			$max_messages = $overflow_config['publicForumLimit'];
            			$max_messages_disp = $max_messages;
            		}
            	}
            	
            	if((isset($overflow_config['privateForumLimit']))&&($type == "private")) {
            	
            		error_log("Type = private. New max messages will be " . $overflow_config['privateForumLimit']);		//TESTING
            	
            		if(is_null($overflow_config['privateForumLimit'])) {
            			$max_messages = null;
            			$max_messages_disp = "NULL";
            		} else {
            			$max_messages = $overflow_config['privateForumLimit'];
            			$max_messages_disp = $max_messages;
            		}
            	}
            	
            	
            	
            	
            	
            	//Get a current message count, in case the forum already exists
            	$current_msg_count = 1;
            	$sql = "SELECT COUNT(*) as record_count FROM tbl_ssshout WHERE int_layer_id = " . clean_data($message_forum_id) . " AND enm_active = 'true'";
            	$result = $api->db_select($sql);
				if($row = $api->db_fetch_array($result))
				{
					$current_msg_count = $row['record_count'];
				}
				
				//Check the current message count is greater than 70% - in which case we need to warn the user that 
				if($max_messages) {
					$seventy_perc_msg_num = intval(0.7 * ($max_messages+$trigger_over_limit));
		        	if($current_msg_count >= $seventy_perc_msg_num) {
		        		  $new_message = "Warning! This forum only keeps the latest " . $max_messages . " messages, and you have reached 70% of that number - the oldest will be removed as you enter new ones. If you want to save older messages you can 'export' them at any time.  To increase the maximum number of messages on the forum please enter 'overflow x' where x is the number, but please keep in mind that you are sharing resources with other users.";		//TODO: x can be up to 'y' maximum.
						  $recipient_ip_colon_id = "";		//No recipient, so the whole group. 123.123.123.123:" . $recipient_id;
						  $sender_name_str = "AtomJump";
						  $sender_email = "webmaster@atomjump.com";
						  $sender_ip = "111.111.111.111";
						  $options = array('notification' => false, 'allow_plugins' => false);
					   	$api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
		        	}
		        }
				
            	
            	//Create a new overflow entry for this forum
            	$sql = "INSERT INTO tbl_overflow_check ( `int_overflow_id`,  `int_layer_id`, `int_current_msg_cnt`, `int_max_messages`, `enm_due_trimming`) VALUES (NULL, " . clean_data($message_forum_id) . ", " . clean_data($current_msg_count) . ", " . clean_data($max_messages) . ",'false')";
            	
            	$result = $api->db_select($sql);
            	$new_msg_count = $current_msg_count;		//Use this value below
            }
            
            
            //Check for incoming user configuration messages
            $actual_message = explode(": ", $message);			//Remove name of sender         
            if($actual_message[1]) {
            	$uc_message = strtoupper($actual_message[1]);
            	if($this->verbose == true) error_log($uc_message);  
		         	
		        if(strpos($uc_message, "OVERFLOW") === 0) {
				      //Check for messages starting with 'overflow [message cnt]', which
				      $new_max_messages = substr($actual_message[1], 9);		//Where 9 is string length of "OVERFLOW "
				      $new_max_messages = str_replace("\\r","", $new_max_messages);
				      $new_max_messages = str_replace("\\n","", $new_max_messages);
				      $new_max_messages = preg_replace('/\s+/', ' ', trim($new_max_messages));
				      
				    
				      error_log("New max messages = " . $new_max_messages);			//TESTING
				      
				      //TODO: If this is less than the max a user can set from the config
				      
				      if(is_numeric($new_max_messages)) {
				      	//Set this to be the new overflow count
				      	$result = $api->db_update("tbl_overflow_check", "int_max_messages = " . clean_data($new_max_messages) . " WHERE int_layer_id = " . clean_data($message_forum_id));	
				      	$new_message = "You have successfully set the new overflow message count to " . $new_max_messages . ".";
				      	$seventy_perc_msg_num = intval(0.7 * ($new_max_messages+$trigger_over_limit));
				      	
				      	error_log("Seventy perc = " . $seventy_perc_msg_num .  "  New msg cnt = " . $new_msg_cnt);		//TESTING
		        		if($new_msg_cnt >= $seventy_perc_msg_num) {
		        			$new_message .= " Warning! You are already past 70% of this overflow count - the oldest will be removed as you enter new ones. If you want to save older messages you can 'export' them at any time.";
		        		}
				      } else {
				      	//Have entered "OVERFLOW" but no number. Report the overflow count to the user
				      	$new_message = "The current maximum is " . $max_messages . " messages at once, with older messages being deleted.  To increase the maximum number of messages on the forum, please enter 'overflow x' where x is the number, but do keep in mind that you are sharing resources with other users.";
				      }
				    			      
				      $recipient_ip_colon_id = "";		//No recipient, so the whole group. 123.123.123.123:" . $recipient_id;
					  $sender_name_str = "AtomJump";
					  $sender_email = "webmaster@atomjump.com";
					  $sender_ip = "111.111.111.111";
					  $options = array('notification' => false, 'allow_plugins' => false);
					  $api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
				 }
			}

            
            return true;
                
        }
    }
?>
