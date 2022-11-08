<?php
	if(!isset($overflow_config)) {
        //Get global plugin config - but only once
		$data = file_get_contents (dirname(__FILE__) . "/config/config.json");
        if($data) {
            $overflow_config = json_decode($data, true);
            if(!isset($overflow_config)) {
                echo "Error: remove_aged config/config.json is not valid JSON.";
                exit(0);
            }
     
        } else {
            echo "Error: Missing config/config.json in remove_aged plugin.";
            exit(0);
     
        }
    }
    
	function trim_trailing_slash_local($str) {
        return rtrim($str, "/");
    }
    
    function add_trailing_slash_local($str) {
        //Remove and then add
        return rtrim($str, "/") . '/';
    }    
    
    
    $start_path = add_trailing_slash_local($overflow_config['serverPath']);
    
	if(isset($_REQUEST['code']) && ($_REQUEST['code'] === $overflow_config['securityCode'])) {
		//Yes passed the security check
		$image_folder = $start_path . "images/im/";
		$image_file = $_REQUEST['imageName'];
		if(isset($image_file) &&($image_file != "")) {
		
			if(unlink($image_folder . $image_file)) {
				echo "Success deleting " . $image_file . ".\n";
				error_log("Success deleting " . $image_folder . $image_file . ".");
				return true;
			} else {
				echo "Failure deleting " . $image_file . ".\n";
				error_log("Failure deleting " . $image_folder . $image_file . ".");
				return true;
			}
		}
	} else {
		echo "Sorry, you have no permission.";
	}

?>
