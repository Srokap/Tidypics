<?php
	/**
	 * Elgg multi-image uploader action
	* 
	* This will upload up to 10 images at at time to an album
	 */

	global $CONFIG;
	include dirname(dirname(__FILE__)) . "/lib/resize.php";

	// Get common variables
	$access_id = (int) get_input("access_id");
	$container_guid = (int) get_input('container_guid', 0);
	if (!$container_guid)
		$container_guid == $_SESSION['user']->getGUID();

	$maxfilesize = get_plugin_setting('maxfilesize','tidypics'); 
	if (!$maxfilesize)
		$maxfilesize = 5; // default to 5 MB if not set 
	$maxfilesize = 1024 * 1024 * $maxfilesize; // convert to bytes from MBs

	$image_lib = get_plugin_setting('image_lib', 'tidypics');
	if (!$image_lib)
		$image_lib = 'GD';

	// post limit exceeded
	if (count($_FILES) == 0) {
		trigger_error('Tidypics warning: user exceeded post limit on image upload', E_USER_WARNING);
		register_error('Too many large images - try to upload fewer or smaller images');
		forward(get_input('forward_url', $_SERVER['HTTP_REFERER']));
	}

	// test to make sure at least 1 image was selected by user
	$num_images = 0;
	foreach($_FILES as $key => $sent_file) {
		if (!empty($sent_file['name']))
			$num_images++;
	}
	if ($num_images == 0) {
		// have user try again
		register_error('No images were selected. Please try again');
		forward(get_input('forward_url', $_SERVER['HTTP_REFERER']));
	}

	$uploaded_images = array();
	$not_uploaded = array();
	$error_msgs = array();
	foreach($_FILES as $key => $sent_file) {
		
		// skip empty entries 
		if (empty($sent_file['name']))
			continue;
		
		$name = $sent_file['name'];
		$mime = $sent_file['type'];
		
		if ($sent_file['error']) {
			array_push($not_uploaded, $sent_file['name']);
			if ($sent_file['error'] == 1) {
				trigger_error('Tidypics warning: image exceed server php upload limit', E_USER_WARNING);
				array_push($error_msgs, 'Image was too large in MB');
			}
			else {
				array_push($error_msgs, 'Unknown upload error');
			}
			continue;
		}
		
		//make sure file is an image
		if ($mime != 'image/jpeg' && $mime != 'image/gif' && $mime != 'image/png' && $mime != 'image/pjpeg') {
			array_push($not_uploaded, $sent_file['name']);
			array_push($error_msgs, 'Not a known image type');
			continue;
		}

		// make sure file does not exceed memory limit
		if ($sent_file['size'] > $maxfilesize) {
			array_push($not_uploaded, $sent_file['name']);
			array_push($error_msgs, 'Image was too large in MB');
			continue;
		}
		
		// make sure the in memory image size does not exceed memory available - GD only
		$imginfo = getimagesize($sent_file['tmp_name']);
		$mem_avail = ini_get('memory_limit');
		$mem_avail = rtrim($mem_avail, 'M');
		$mem_avail = $mem_avail * 1024 * 1024;
		if ($image_lib === 'GD') {
			$mem_required = 5 * $imginfo[0] * $imginfo[1];
			$mem_avail = $mem_avail - memory_get_peak_usage() - 4194304; // 4 MB buffer
			if ($mem_required > $mem_avail) {
				array_push($not_uploaded, $sent_file['name']);
				array_push($error_msgs, 'Image was too large in pixels');
				trigger_error('Tidypics warning: image memory size too large for resizing so rejecting', E_USER_WARNING);
				continue;
			}
		} else if ($image_lib === 'ImageMagick') {  // this will be for PHP ImageMagick
/*
			$mem_required = 5 * $imginfo[0] * $imginfo[1];
			$mem_avail = $mem_avail - memory_get_peak_usage() - 4194304; // 4 MB buffer
			if ($mem_required > $mem_avail) {
				array_push($not_uploaded, $sent_file['name']);
				trigger_error('Tidypics warning: image memory size too large for resizing so rejecting', E_USER_WARNING);
				continue;
			}
*/
		}

		//this will save to users folder in /image/ and organize by photo album
		$prefix = "image/" . $container_guid . "/";
		$file = new ElggFile();
		$filestorename = strtolower(time().$name);
		$file->setFilename($prefix.$filestorename);
		$file->setMimeType($mime);
		$file->originalfilename = $name;
		$file->subtype="image";
		$file->access_id = $access_id;
		if ($container_guid) {
			$file->container_guid = $container_guid;
		}
		$file->open("write");
		$file->write(get_uploaded_file($key));
		$file->close();
		$result = $file->save();

		if (!$result) {
			array_push($not_uploaded, $sent_file['name']);
			array_push($error_msgs, 'Unknown error saving the image on server');
			continue;
		}
		

		if ($image_lib === 'GD') {

			if (tp_create_gd_thumbnails($file, $prefix, $filestorename) != true) {
				trigger_error('Tidypics warning: failed to create thumbnails', E_USER_WARNING);
			}
			
		} else if ($image_lib === 'ToDo:ImageMagick') {  // ImageMagick PHP 
/*
			if (tp_create_imagick_thumbnails($file, $prefix, $filestorename) != true) {
				trigger_error('Tidypics warning: failed to create thumbnails', E_USER_WARNING);
			}
*/
		} else { // ImageMagick command line


			if (tp_create_imagick_cmdline_thumbnails($file, $prefix, $filestorename) != true) {
				trigger_error('Tidypics warning: failed to create thumbnails', E_USER_WARNING);
			}

			$album = get_entity($container_guid);
			$user = get_user_entity_as_row($album->owner_guid);
			$username = $user->username;

			$im_path = get_plugin_setting('convert_command', 'tidypics');
			if(!$im_path) {
				$im_path = "/usr/bin/";
			}
			if(substr($im_path, strlen($im_path)-1, 1) != "/") $im_path .= "/";
			
			$viewer = get_loggedin_user();
			$watermark_text = get_plugin_setting('watermark_text', 'tidypics');
			$watermark_text = str_replace("%username%", $viewer->username, $watermark_text);
			$watermark_text = str_replace("%sitename%", $CONFIG->sitename, $watermark_text);
			if( $watermark_text ) { //get this value from the plugin settings
				if( $thumblarge ) {
					$ext = ".png";
					
					$watermark_filename = strtolower($watermark_text);
					$watermark_filename = preg_replace("/[^\w-]+/", "-", $watermark_filename);
					$watermark_filename = trim($watermark_filename, '-');
					
					$user_stamp_base = strtolower(dirname(__FILE__) . "/" . $viewer->name . "_" . $watermark_filename . "_stamp");
					$user_stamp_base = preg_replace("/[^\w-]+/", "-", $user_stamp_base);
					$user_stamp_base = trim($user_stamp_base, '-');
					
					if( !file_exists( $user_stamp_base . $ext )) { //create the watermark if it doesn't exist
						$commands = array();
						$commands[] = $im_path . 'convert -size 300x50 xc:grey30 -pointsize 20 -gravity center -draw "fill grey70  text 0,0  \''. $watermark_text . '\'" "'. $user_stamp_base . '_fgnd' . $ext . '"';
						$commands[] = $im_path . 'convert -size 300x50 xc:black -pointsize 20 -gravity center -draw "fill white  text  1,1  \''. $watermark_text . '\' text  0,0  \''. $watermark_text . '\' fill black  text -1,-1 \''. $watermark_text . '\'" +matte ' . $user_stamp_base . '_mask' . $ext;
						$commands[] = $im_path . 'composite -compose CopyOpacity  "' . $user_stamp_base . "_mask" . $ext . '" "' . $user_stamp_base . '_fgnd' . $ext . '" "' . $user_stamp_base . $ext . '"';
						$commands[] = $im_path . 'mogrify -trim +repage "' . $user_stamp_base . $ext . '"';
						$commands[] = 'rm "' . $user_stamp_base . '_mask' . $ext . '"';
						$commands[] = 'rm "' . $user_stamp_fgnd . '_mask' . $ext . '"';
						
						foreach( $commands as $command ) {
							exec( $command );
						}
					}
					//apply the watermark
					$commands = array();
					$commands[] = $im_path . 'composite -gravity south -geometry +0+10 "' . $user_stamp_base . $ext . '" "' . $thumblarge . '" "' . $thumblarge . '_watermarked"';
					$commands[] = "mv \"$thumblarge" . "_watermarked\" \"$thumblarge\"";
					foreach( $commands as $command ) {
						exec( $command );
					}
				}
			}
		} // end of image library selector
					
		array_push($uploaded_images, $file->guid);

		unset($file);  // may not be needed but there seems to be a memory leak
		

	} //end of for loop
	
	
	if (count($not_uploaded) > 0) {
		if (count($uploaded_images) > 0)
			$error = sprintf(elgg_echo("tidypics:partialuploadfailure"), count($not_uploaded), count($not_uploaded) + count($uploaded_images))  . '<br />';
		else
			$error = elgg_echo("tidypics:completeuploadfailure") . '<br />';

		$num_failures = count($not_uploaded);
		for ($i = 0; $i < $num_failures; $i++) {
			$error .= "{$not_uploaded[$i]}: {$error_msgs[$i]} <br />";
		}
		register_error($error);
		
		if (count($uploaded_images) == 0)
			forward(get_input('forward_url', $_SERVER['HTTP_REFERER'])); //upload failed, so forward to previous page
		else {
			// some images did upload so we fall through
		}
	} else {
			system_message(elgg_echo("images:saved"));
	}

	// successful upload so check if this is a new album and throw river event if so
	$album = get_entity($container_guid);
	if ($album->new_album == 1) {
		if (function_exists('add_to_river'))
			add_to_river('river/object/album/create', 'create', $album->owner_guid, $album->guid);
		$album->new_album = 0;
	}
	// plugins can register to be told when a Tidypics album has had images added
	trigger_elgg_event('upload', 'tp_album', $album);
	
	
	//forward to multi-image edit page
	forward($CONFIG->wwwroot . 'mod/tidypics/edit_multi.php?files=' . implode('-', $uploaded_images)); 

?>
