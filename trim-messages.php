<?php

	/*
	
	Trim message forums that are flagged to be trimmed from the oldest first. 
	This will be called hourly on a regular cronjob.
	
	0 * * * *       /usr/bin/php /your_server_path/api/plugins/overflow/trim-messages.php
	
	1. Loop through each message from the bottom of that forum and search individually within that message for
	images that are held on this server. They will start with the config.json JSON entry uploads.vendor.imageURL
	if images are uploaded via AmazonAWS (/digitalocean), or with the current server URL /images/im/ if uploaded
	to the same server.
	2. Delete the image from using the AmazonAWS API or the local file-system unlink();
	3. Delete the message
	4. Repeat for all messages in the list (at the bottom of the forum). 
	
	TODO: archiving old messages
	
	*/

	$preview = false;		//Usually set to false, unless we are testing this manually 

	use Aws\S3\S3Client;
	use Aws\S3\Exception\S3Exception;
	
	require('vendor/aws-autoloader.php');


	function trim_trailing_slash_local($str) {
        return rtrim($str, "/");
    }
    
    function add_trailing_slash_local($str) {
        //Remove and then add
        return rtrim($str, "/") . '/';
    }
    
    
	function delete_image($image_file, $image_folder, $preview = false) {
		global $local_server_path;
		global $cnf;
		
		

		if(isset($cnf['uploads']['use'])) {
			if($cnf['uploads']['use'] == "amazonAWS") {

				if(isset($cnf['uploads']['vendor']['amazonAWS']['uploadUseSSL'])) {
					$use_ssl = $cnf['uploads']['vendor']['amazonAWS']['uploadUseSSL'];
					
				} else {
					$use_ssl = false;		//Default
				}
				
				if(isset($cnf['uploads']['vendor']['amazonAWS']['uploadEndPoint'])) {
					$endpoint = $cnf['uploads']['vendor']['amazonAWS']['uploadEndPoint'];
				} else {
					$endpoint = "https://s3.amazonaws.com";		//Default
				}
				
				if(isset($cnf['uploads']['vendor']['amazonAWS']['bucket'])) {
					$bucket = $cnf['uploads']['vendor']['amazonAWS']['bucket'];
				} else {
					$bucket = "ajmp";		//Default
				}
				
				if(isset($cnf['uploads']['vendor']['amazonAWS']['region'])) {
					$region = $cnf['uploads']['vendor']['amazonAWS']['region'];
				} else {
					$region = 'nyc3';
				}		
		
				
				
				$output = "Preparing to delete image: " . $image_file . "    from bucket: " . $bucket .   "   from region: " . $region .  "   at endpoint: " . $endpoint;
				echo $output . "\n";
				error_log($output);
				
				
				if($preview !== false) {
					//A preview, always return deleted
					return true;
				} else {
		
					echo "S3 connection about to be made\n";
										
					//Get an S3 client
					$s3 = new Aws\S3\S3Client([
							'version' => 'latest',
							'region'  => $region,				
							'endpoint' => $endpoint,			//E.g. 'https://nyc3.digitaloceanspaces.com'
							'credentials' => [
									'key'    => $cnf['uploads']['vendor']['amazonAWS']['accessKey'],
									'secret' => $cnf['uploads']['vendor']['amazonAWS']['secretKey'],
								]
					]);
					
					
		
					if($s3 != false) {
						echo "S3 connection made\n";
						
						try {
							// Upload data.
							$s3->deleteObject([
								'Bucket' => $bucket,
								'Key'    => $image_file
							]);

							// Print the URL to the object.
							error_log("Successfully deleted: " . $image_file);
							echo "Successfully deleted: " . $image_file . "\n";
							//Deleted correctly
						
							return true;
						} catch (S3Exception $e) {
							//Error deleting from Amazon
							error_log($e->getMessage());
							echo "Error deleting: " . $e->getMessage() . "\n";
							return false;
						}
					} else {
						echo "S3 connection not made\n";
						return false;
					}
				} 
			} else {
			
				//Delete locally
				$output = "Preparing to delete image: " . $image_folder . $image_file;
				echo $output . "\n";
				error_log($output);
				if($preview !== true) {
					if(unlink($image_folder . $image_file)) {
						echo "Success deleting.\n";
						error_log("Success deleting");
						return true;
					} else {
						echo "Failure deleting.\n";
						error_log("Failure deleting");
						return true;
					}
				} else {
					return true;
				}
			}
		}

		
		
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



    $agent = $overflow_config['agent'];
	ini_set("user_agent",$agent);
	$_SERVER['HTTP_USER_AGENT'] = $agent;
	$start_path = add_trailing_slash_local($overflow_config['serverPath']);
	if($overflow_config['layerTitleDbOverride']) {
		//Override the selected database
		$_REQUEST['uniqueFeedbackId'] = $overflow_config['layerTitleDbOverride'];
	}
	if($overflow_config['preview']) {
		$preview = $overflow_config['preview'];
	}
	
	if($overflow_config['randomPause']) {
		//Pause execution for a random interval to prevent multiple servers in a cluster calling at the same time on the cron.
		$pause_by = rand(1,$overflow_config['randomPause']);
		sleep($pause_by);
	}

	$image_folder = $start_path . "images/im/";
	
	$notify = false;
	include_once($start_path . 'config/db_connect.php');	
	
	$define_classes_path = $start_path;     //This flag ensures we have access to the typical classes, before the cls.pluginapi.php is included
	require($start_path . "classes/cls.pluginapi.php");
	
	$api = new cls_plugin_api();
	
	
	if($preview == true) {
		echo "Preview mode ON\n";
	}
	
	
	echo "Using database host: " .  $cnf['db']['hosts'][0] . "  name:" . $cnf['db']['name'] . "\n";
		
	$delete_forum = false;		
	if(isset($cnf['db']['deleteDeletes'])) {
		//Defaults to the server-defined option, unless..
		$delete_forum = $cnf['db']['deleteDeletes'];
	}
	if(isset($overflow_config['deleteForum'])) {
		//Unless we have an override in our local config
		$delete_forum = $overflow_config['deleteForum'];	
	}
	

	echo "Checking for layers due to be trimmed...\n";
	$sql = "SELECT * FROM tbl_overflow_check WHERE enm_due_trimming = 'true'";
    $result = $api->db_select($sql);
	while($row = $api->db_fetch_array($result))
	{
			$this_layer = $row['int_layer_id'];
			
			echo "Layer: " . $this_layer . "\n";
			
			//Get messages in the forum that are aged - sort by order inserted, up to the limit of the 
			$old_messages_cnt = $row['int_current_msg_cnt'];
			$messages_to_trim = $old_messages_cnt - $row['int_max_messages'];
			$current_trimmed_cnt = $row['int_cnt_trimmed'];		//Use this for writing back the trimmed count as a record
			$sql = "SELECT int_ssshout_id, var_shouted FROM tbl_ssshout WHERE int_layer_id = " . $this_layer . " ORDER BY int_ssshout_id LIMIT " . $messages_to_trim;
			
			
			//echo $sql . "\n";
			$result_msgs = $api->db_select($sql);
			while($row_msg = $api->db_fetch_array($result_msgs))
			{
				echo "Message: " . $row_msg['var_shouted'] . "    ID:" . $row_msg['int_ssshout_id'] . "\n";
				
				global $cnf;
				
				if($delete_forum === true) {
					
					
					//Search for any images in the message
					echo "Search term = " . $cnf['uploads']['replaceHiResURLMatch'] . "\n";
					$url_matching = "ajmp";		//Works with Amazon based jpgs on atomjump.com which include ajmp.
					if($cnf['uploads']['replaceHiResURLMatch']) $url_matching = $cnf['uploads']['replaceHiResURLMatch'];
					
					
					$preg_search = "/.*?" . $url_matching ."(.*?)\.jpg/i";
					preg_match_all($preg_search, $row_msg['var_shouted'], $matches);
				
					
						
					if(count($matches) > 1) {
						//Yes we have at least one image
						for($cnt = 0; $cnt < count($matches[1]); $cnt++) {
							echo "Matched image raw: " . $matches[1][$cnt] . "\n";
							$between_slashes = explode( "/", $matches[1][$cnt]);
							$len = count($between_slashes) - 1;
							$image_name = $between_slashes[$len] . ".jpg";
							$image_hi_name = $between_slashes[$len] . "_HI.jpg";
							echo "Image name: " . $image_name . "\n";
				
				
							//Delete this image
							delete_image($image_name, $image_folder, $preview);
							delete_image($image_hi_name, $image_folder, $preview);
						}
					}
					
					
					//Delete the record
					if($preview == false) {
						echo "Deleting message " . $row_msg['int_ssshout_id'] . "\n";
						error_log("Deleting message " . $row_msg['int_ssshout_id']);
						$sql_del = "DELETE FROM tbl_ssshout WHERE int_ssshout_id = " . $row_msg['int_ssshout_id'];
						$api->db_select($sql_del);
					}
				
				
				} else {
					echo "Deactivating. But leaving images.";
					if($preview == false) {
					   echo "Deactivating message " . $row_msg['int_ssshout_id'] . "\n";
					   error_log("Deactivating message " . $row_msg['int_ssshout_id']);
					   
					   $api->db_update("tbl_ssshout", "enm_active = 'false' WHERE int_ssshout_id = " . $row_msg['int_ssshout_id']);
					} else {
						echo "Would be deactivating message " . $row_msg['int_ssshout_id'] . "\n";
					}
				}
			}
			
			
			//Write back the number of messages trimmed into the tbl_overflow record, reduce the count, switch to 'not due a trimming'
			$new_trimmed_cnt = $current_trimmed_cnt + $messages_to_trim;
			$new_messages_cnt = $old_messages_cnt - $messages_to_trim;
			$api->db_update("tbl_overflow_check", "int_current_msg_cnt = " . $new_messages_cnt . ", int_cnt_trimmed = " . $new_trimmed_cnt . ", enm_due_trimming = 'false' WHERE int_layer_id = " . $this_layer);
		
	} 
		

	
	session_destroy();  //remove session




?>
