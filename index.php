<?php
    include_once("classes/cls.pluginapi.php");
    
   
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
   		
   		
   		private $max_messages;			//E.g. 50, or null for unlimited
   		private $max_messages_disp;		//E.g. "50" or "NULL" for unlimited
   		private $max_user_set_limit;	//E.g. 400, or null for unlimited
   		private $max_user_set_limit_disp;	//E.g. 400, or "NULL" for unlimited
   		
   		private function get_max_messages($api, $overflow_config, $message_forum_id) {
   				//Sets the 4 private member variables
	   			/*
	   			  	private $current_max_messages;
	   				private $current_max_messages_disp;			
	   				private $max_user_set_limit;
	   				private $max_user_set_limit_disp;
   				*/
   			
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
            	
            	//Check the current actual max messages
            	$sql = "SELECT * FROM tbl_overflow_check WHERE int_layer_id = " . clean_data($message_forum_id);
		        $result = $api->db_select($sql);
				if($row = $api->db_fetch_array($result))
				{
            		$this->current_max_messages = $row['int_max_messages'];
            		if(is_null($this->current_max_messages)) {
            			$this->current_max_messages_disp = "NULL";
            		} else {
            			$this->current_max_messages_disp = $this->current_max_messages;
            		}
            	}
            	           	
            	if((isset($overflow_config['publicMaxUserSetLimit']))&&($type == "public")) {
            	
            	
            		if(is_null($overflow_config['publicMaxUserSetLimit'])) {
            			$this->max_user_set_limit = null;
            			$this->max_user_set_limit_disp = "NULL";
            		} else {
            			$this->max_user_set_limit = $overflow_config['publicMaxUserSetLimit'];
            			$this->max_user_set_limit_disp = $this->max_user_set_limit;
            		}
            	}
            	
            	if((isset($overflow_config['privateMaxUserSetLimit']))&&($type == "private")) {
            	
            	
            		if(is_null($overflow_config['privateMaxUserSetLimit'])) {
            			$this->max_user_set_limit = null;
            			$this->max_user_set_limit_disp = "NULL";
            		} else {
            			$this->max_user_set_limit = $overflow_config['privateMaxUserSetLimit'];
            			$this->max_user_set_limit_disp = $this->max_user_set_limit;
            		}
            	}
            	
            	return;   		
   		}
     
 		public function on_message($message_forum_id, $message, $message_id, $sender_id, $recipient_id, $sender_name, $sender_email, $sender_phone)
        {
        	$returned_message = false;		//Whether we do a re-entrant call to process a new count, or not, after returning.
        	
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
            
            
                      
            $api = new cls_plugin_api();
               
            //Increment the count if a count already exists for this forum, else create a count and set as 1.  
            //When creating a count:
            //determine the type of forum and use the default message limit in the config file and set this in the db record.
            //This can be manually adjusted in the database later, or use the basic user/admin facility for altering the max message count.
            
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
            	
            	//Once in every 50 or so requests, do a refresh of the count from the actual number - this is necessary because
            	//some plugins will not generate an 'on_message' event after a message has been entered e.g. the 'emoticons_large' plugin.
            	if(rand(0,50) == 0) {		//Use 5 for testing, 50 for live
		        	$sql = "SELECT COUNT(*) as record_count FROM tbl_ssshout WHERE int_layer_id = " . clean_data($message_forum_id) . " AND enm_active = 'true'";
		        	$result = $api->db_select($sql);
					if($row = $api->db_fetch_array($result))
					{
						$new_msg_cnt = $row['record_count'];
					}
				}
				
				//Once in every 10 or so new messages, we should set the forum to have it's images blurred
				if(rand(0,10) == 0) {
					if($row['enm_due_blurring'] == "false") {
						//An 'inactive' value in this record means we don't want to blur this forum. Can be set by the admin user,
						//but a 'false' value means we can configure it to blur
						$result = $api->db_update("tbl_overflow_check", "enm_due_blurring = 'true' WHERE int_layer_id = " . clean_data($message_forum_id));			
					}	
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
		        	if(($row['int_cnt_trimmed'] == 0)&&($new_msg_cnt == $seventy_perc_msg_num)) {
		        		  $new_message = "Warning! This forum only keeps the latest " . $max_messages . " messages, and you have reached 70% of that number - the oldest will be removed as you enter new ones. If you want to save older messages you can 'export' them at any time.  To increase the maximum number of messages on the forum please enter 'overflow x' where x is the number, but please keep in mind that you are sharing resources with other users.";		
						  $recipient_ip_colon_id = "";		//No recipient, so the whole group. 123.123.123.123:" . $recipient_id;
						  $sender_name_str = "AtomJump";
						  $sender_email = "webmaster@atomjump.com";
						  $sender_ip = "111.111.111.111";
						  $options = array('notification' => false, 'allow_plugins' => false);
					   	$api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
					   	$returned_message = true;
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
            	
             	if((isset($overflow_config['publicForumLimit']))&&($type == "public")) {           	
            		if(is_null($overflow_config['publicForumLimit'])) {
            			$max_messages = null;
            			$max_messages_disp = "NULL";
            		} else {
            			$max_messages = $overflow_config['publicForumLimit'];
            			$max_messages_disp = $max_messages;
            		}
            	}
            	
            	if((isset($overflow_config['privateForumLimit']))&&($type == "private")) {
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
		        		  $new_message = "Warning! This forum only keeps the latest " . $max_messages . " messages, and you have reached 70% of that number - the oldest will be removed as you enter new ones. If you want to save older messages you can 'export' them at any time.  To increase the maximum number of messages on the forum please enter 'overflow x' where x is the number, but please keep in mind that you are sharing resources with other users.";		
						  $recipient_ip_colon_id = "";		//No recipient, so the whole group. 123.123.123.123:" . $recipient_id;
						  $sender_name_str = "AtomJump";
						  $sender_email = "webmaster@atomjump.com";
						  $sender_ip = "111.111.111.111";
						  $options = array('notification' => false, 'allow_plugins' => false);
					   	$api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
					   	$returned_message = true;
					   
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
		         	
		         	
		        if(strpos($uc_message, "BLUR") === 0) { 
		        	  //Found a message starting with e.g. 'blur [off|on]'
		        	    //Check whether this has been sent by the admin user
				      $lg = new cls_login(); 							  
	 				  $is_admin = $lg->is_admin($sender_id);	//Note: in future versions (with messaging server >= 3.2.2),
	 				  											// we should just use the API call for this.
	 				  											
	 				  if($is_admin == true) {
	 				  	if(strpos($uc_message, "OFF") !== false) {
	 				  		//Switch image blurring off for this forum
	 				  		$result = $api->db_update("tbl_overflow_check", "enm_due_blurring = 'inactive' WHERE int_layer_id = " . clean_data($message_forum_id));	
	 				  		$new_message = "You have successfully switched image blurring off, on this forum. Tapped images will remain at their original resolution.";
	 				  	}
	 				  	
	 				  	if(strpos($uc_message, "ON") !== false) {
	 				  		$result = $api->db_update("tbl_overflow_check", "enm_due_blurring = 'false' WHERE int_layer_id = " . clean_data($message_forum_id));	
	 				  		$new_message = "You have successfully switched image blurring on, on this forum. Older tapped images will be at a reduced resolution.";
	 				  	}
	 				  } else {
	 				  	//Let general users know they need to contact their admin user
	 				  	$new_message = "Sorry, you need to be the System Admin to switch image blurring on or off. " . $overflow_config ['contactAdminToRemoveLimits'];
	 				  }
	 				  
	 				  $recipient_ip_colon_id = "";		//No recipient, so the whole group. 123.123.123.123:" . $recipient_id;
					  $sender_name_str = "AtomJump";
					  $sender_email = "webmaster@atomjump.com";
					  $sender_ip = "111.111.111.111";
					  $options = array('notification' => false, 'allow_plugins' => false);
					  $api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
	 				  
	 				  
		        }	
		         	
		        if(strpos($uc_message, "OVERFLOW") === 0) {
		        	  //Found a message starting with e.g. 'overflow [message cnt]'. The 0 means the position is at char 0.
				      
				      
				      //Check whether this has been sent by the admin user
				      $lg = new cls_login(); 							  
	 				  $is_admin = $lg->is_admin($sender_id);	//Note: in future versions (with messaging server >= 3.2.2),
	 				  											// we should just use the API call for this.
				      
				      $new_max_messages = substr($actual_message[1], 9);		//Where 9 is string length of "OVERFLOW "
				      $new_max_messages = str_replace("\\r","", $new_max_messages);
				      $new_max_messages = str_replace("\\n","", $new_max_messages);
				      $new_max_messages = preg_replace('/\s+/', ' ', trim($new_max_messages));
				      
				    				      
				      //If this is less than the max a user can set from the config, we shouldn't set the value, unless they are
				      //the admin user.
				      
				       $this->get_max_messages($api, $overflow_config, $message_forum_id);		
				       //This call sets $this->$current_max_messages, $current_max_messages_disp, $max_user_set_limit, $max_user_set_limit_disp;
				      		 
				     
				      
				      if(is_numeric($new_max_messages)) {
				      	
				      	if(($is_admin == true)||			//admin user
				      		($this->max_user_set_limit == null)||		//there is no limit on setting a maximum for users
				      		(($this->current_max_messages)&&($new_max_messages > $this->current_max_messages)&&($new_max_messages <= $this->max_user_set_limit))) {
				      										//or, the limit is larger than before (and it was not unlimited), and
				      									    // less than the limit that users can set it to.
				      										//Note: a general user shouldn't be able to reduce the max, because this
				      										//could lead to it being used to fully delete other people's messages on the forum
				      
						  	//Set this to be the new overflow count
						  	$result = $api->db_update("tbl_overflow_check", "int_max_messages = " . clean_data($new_max_messages) . " WHERE int_layer_id = " . clean_data($message_forum_id));	
						  	$new_message = "You have successfully set the new overflow message count to " . $new_max_messages . ".";
						  	$seventy_perc_msg_num = intval(0.7 * ($new_max_messages+$trigger_over_limit));
						  	if($new_msg_cnt >= $seventy_perc_msg_num) {
				    			$new_message .= " Warning! You are already past 70% of this overflow count - the oldest will be removed as you enter new ones. If you want to save older messages you can 'export' them at any time.";
				    		}
				    	} else {
				    		//Not authorised to set this new value.
				    		if($this->current_max_messages) {
				    			//There is a current limit
				    			$new_message = "Sorry, you cannot decrease the overflow count to below " . $max_messages . " messages, or set this above a maximum of " . $this->max_user_set_limit . " messages. You can contact your System Admin to request this change, however. " . $overflow_config ['contactAdminToRemoveLimits'];
				    		} else {
				    			//It is currently unlimited - a normal user can't introduce a limit
				    			$new_message = "Sorry, you cannot introduce an overflow limit because you currently have no overflow limit (i.e. the forum is unlimited). You can contact your System Admin to request this change, however. " . $overflow_config ['contactAdminToRemoveLimits'];
				    		}				    	
				    	}
				      } else {
				      	if(strpos($uc_message, "UNLIMITED") !== false) {
				      		//Have entered 'overflow unlimited'. Trying to set this to an unlimited				      		 
				      		 if(($is_admin == true)||($this->max_user_set_limit == null)) {
				      			//Authorised to do this
				      			$result = $api->db_update("tbl_overflow_check", "int_max_messages = NULL WHERE int_layer_id = " . clean_data($message_forum_id));	
				      			$new_message = "You have successfully set the new overflow message count to being unlimited.";
				      		} else {
				      			//Not authorised
				      			$new_message = "Sorry, you could not set the new overflow message count to being unlimited. " . $overflow_config ['contactAdminToRemoveLimits'];
				      		}
				      	} else {
				      		//Have entered "overflow" but no number. Report the overflow count to the user
				      		$new_message = "The current maximum is " . $max_messages . " messages at once, with older messages being deleted.  To increase the maximum number of messages on the forum, please enter 'overflow x' where x is the number, but do keep in mind that you are sharing resources with other users.";
				      	}
				      }
				    			      
				      $recipient_ip_colon_id = "";		//No recipient, so the whole group. 123.123.123.123:" . $recipient_id;
					  $sender_name_str = "AtomJump";
					  $sender_email = "webmaster@atomjump.com";
					  $sender_ip = "111.111.111.111";
					  $options = array('notification' => false, 'allow_plugins' => false);
					  $api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
					  $returned_message = true;
				 }
			}

			if($returned_message == true) {
			
				//And register this message on our count, as another entry - but don't process any other plugins..
				$this->on_message($message_forum_id, $new_message, $message_id, $sender_id, $recipient_id, $sender_name, $sender_email, $sender_phone);
			}
            
            return true;
                
        }
    }
?>
