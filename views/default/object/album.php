<?php
	/**
	 * Elgg file browser.
	 * File renderer.
	 * 
	 * @package ElggFile
	 * @author Curverider Ltd
	 * @copyright Curverider Ltd 2008
	 * @link http://elgg.com/
	 */

	global $CONFIG;
	
	$file = $vars['entity'];	
	$file_guid = $file->getGUID();
	$tags = $file->tags;
	$title = $file->title;
	$desc = $file->description;
	$owner = $vars['entity']->getOwnerEntity();
	$friendlytime = friendly_time($vars['entity']->time_created);
	$mime = $file->mimetype;
	
	if (get_context() == "search") { 	

		if (get_input('search_viewtype') == "gallery") {
			//default gallery view for album listing @ /photos/owned/
			
			//get album cover if one was set 
			if($file->cover)
				$album_cover = '<img src="'.$vars['url'].'mod/tidypics/thumbnail.php?file_guid='.$file->cover.'&size=small" border="0" class="album_cover"  alt="thumbnail"/>';
			else
				$album_cover = '<img src="'.$vars['url'].'mod/tidypics/graphics/img_error.jpg" class="album_cover" alt="new album">';

	?>
			<div class="album_gallery_item">			
				<a href="<?php echo $file->getURL();?>"><?php echo $title;?></a><br>
				<a href="<?php echo $file->getURL();?>"><?php echo $album_cover;?></a><br>
				<small><a href="<?php echo $vars['url'];?>pg/profile/<?php echo $owner->username;?>"><?php echo $owner->name;?></a> <?php echo $friendlytime;?><br>			
				<?php
				//get the number of comments
				$numcomments = elgg_count_comments($file);
				if ($numcomments)
					echo "<a href=\"{$file->getURL()}\">" . sprintf(elgg_echo("comments")) . " (" . $numcomments . ")</a>";
				?>
				</small>
			</div>
			
		<?php	
		} else {
			//album list-entity view

			$info = '<p><a href="' .$file->getURL(). '">'.$title.'</a></p>';
			$info .= "<p class=\"owner_timestamp\"><a href=\"{$vars['url']}pg/file/{$owner->username}\">{$owner->name}</a> {$friendlytime}";
			$numcomments = elgg_count_comments($file);
			if ($numcomments)
				$info .= ", <a href=\"{$file->getURL()}\">" . sprintf(elgg_echo("comments")) . " (" . $numcomments . ")</a>";
			$info .= "</p>";
			
			$icon = "<a href=\"{$file->getURL()}\">" . elgg_view("tidypics/icon", array('album' => true, 'size' => 'small')) . "</a>";
			
			echo elgg_view_listing($icon, $info);
		
		}
		
	} else {							
		// individual album view	
?>
	<div id="pages_breadcrumbs">
<?php
		if (is_null(page_owner_entity()->username) || empty(page_owner_entity()->username)) { //when no owner available, link to world photos
?>
		<a href="<?php echo $vars['url'] . 'pg/photos/world'; ?>"><?php echo elgg_echo("albums"); ?></a>&nbsp;&#62;&nbsp;
<?php
		} else {
?>
		<a href="<?php echo $vars['url'] . 'pg/photos/owned/' . page_owner_entity()->username; ?>"><?php echo sprintf(elgg_echo("album:user"), page_owner_entity()->name); ?></a>&nbsp;&#62;&nbsp;
<?php
		}
?>
		<?php echo $title; ?>
	</div>  

<?php 
	echo '<div id="tidypics_title">'.$title.'</div>'; 
	echo '<div id="tidypics_desc">'.autop($desc).'</div>';
	
	if ($file->canEdit()) {  // add edits
		// specific to my theme only
		//add_submenu_item(elgg_echo('album:addpix'), $vars['url'] . "pg/photos/upload/". $file_guid , '', 'jade');	
		//add_submenu_item(elgg_echo('album:edit'), $vars['url'] . "mod/tidypics/edit.php?file_guid=". $file_guid , '', 'jade');	
		//add_submenu_item(elgg_echo('album:delete'), $vars['url'] . "action/tidypics/delete?file=". $file_guid , '', 'jade');	
				
?>
	<div id="tidypics_controls">
		<a href="<?php echo $vars['url'] . "pg/photos/upload/" . $file_guid ;?>"><?php echo elgg_echo('album:addpix');?></a>	
		<a href="<?php echo $vars['url']; ?>mod/tidypics/edit.php?file_guid=<?php echo $file->getGUID(); ?>"><?php echo elgg_echo('album:edit'); ?></a>&nbsp; 
		
		<?php echo elgg_view('output/confirmlink',array(						
							'href' => $vars['url'] . "action/tidypics/delete?file=" . $file->getGUID(),
							'text' => elgg_echo("album:delete"),
							'confirm' => elgg_echo("album:delete:confirm"),						
						));  
		?>
	</div>
<?php
	}

	// display the simple image views. Uses: via 'object/image.php'
	$count = get_entities("object","image", $file_guid, '', 999);

	//build array for back | next links 
	$_SESSION['image_sort'] = array();
	
	foreach($count as $image){
		array_push($_SESSION['image_sort'], $image->guid);
	}
		
	if(count($count) > 0)
		echo list_entities("object","image", $file_guid, 24, false);	
	else
		echo elgg_echo('image:none');
	
?>
	<div class="clearfloat"></div>
	<div id="tidypics_info">
		<div class="object_tag_string"><?php echo elgg_view('output/tags',array('value' => $tags));?></div>	
		<?php echo elgg_echo('album:by');?> <b><a href="<?php echo $vars['url'] ;?>pg/profile/<?php echo $owner->username; ?>"><?php echo $owner->name; ?></a></b>  <?php echo $friendlytime; ?><br>
		<?php echo elgg_echo('image:total');?> <b><?php echo count($count);?></b><br>		
	</div>

<?php
		if ($vars['full']) {
			echo elgg_view_comments($file);		
		}
	
}
?>
