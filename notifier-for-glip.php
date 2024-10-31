<?php
/*
Plugin Name: Notifier for Glip
Plugin URI: http://www.carrcommunications.com/notifier-for-glip/
Description: Post a notification to a Glip team conversation whenever a new blog article is posted or a comment is posted to a blog that you maintain. The plugin uses the WebHooks interface to connect with Glip, the team collaboration and productivity platform from RingCentral.
Author: David F. Carr
Version: 0.9
Author URI: http://www.carrcommunications.com
*/

function glip_webhook($webhook_url,$title, $body, $activity,$icon ='' ) {

$json = json_encode(array('icon'=>$icon,'activity'=>$activity,'title'=>$title,'body'=>$body) );

$ch = curl_init($webhook_url);                                                                     
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);                                                                  
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    'Content-Type: application/json',                                                                                
    'Content-Length: ' . strlen($json))                                                          
);                                                                                  $r = curl_exec($ch);
return $r;
}

function glip_post_published_notification( $ID, $post, $update ) {
	
	if ($post->post_date != $post->post_modified)
		return; // don't do this for edits to a previously published post
	$webhook_url = get_option('glip_webhook'); 
	if(empty($webhook_url))
		return; // won't work without it
	
    $author = $post->post_author; /* Post author ID. */
    $name = get_the_author_meta( 'display_name', $author );
    $email = get_the_author_meta( 'user_email', $author );
    $title = $post->post_title;
    $permalink = get_permalink( $ID );
    $edit = get_edit_post_link( $ID, '' );
	$start = substr(strip_tags($post->post_content),0,100);
	$title = $update.sprintf('New post to blog: [%s](%s) by %s %s',$title, $permalink,$name,$email);
	$body = sprintf('%s

%s

[Edit](%s)',$_SERVER['SERVER_NAME'],$start,$edit);
	$icon = plugins_url('wordpress-logo-32-blue.png',__FILE__);
	$activity = 'New Blog Post';
	glip_webhook($webhook_url,$title, $body, $activity,$icon);
}
add_action( 'publish_post', 'glip_post_published_notification', 10, 2 );

add_action('wp_insert_comment','glip_comment_inserted',99,2);

function glip_comment_inserted($comment_id, $comment_object) {

	$webhook_url = get_option('glip_webhook'); 
	if(empty($webhook_url))
		return; // won't work without it

	$permalink = get_permalink($comment_object->comment_post_ID);
	$title = sprintf('New comment on blog by %s %s',$comment_object->comment_author, $comment_object->comment_author_email);
	$body = sprintf('%s
	
	%s
	
	Post: [%s](%s)',$_SERVER['SERVER_NAME'],$comment_object->comment_content,get_the_title($comment_object->comment_post_ID),$permalink);
	$icon = plugins_url('wordpress-logo-32-blue.png',__FILE__);
	$activity = 'New Comment on Blog';
	glip_webhook($webhook_url,$title, $body, $activity,$icon);
}

add_action('admin_init', 'glip_options_init' );
add_action('admin_menu', 'glip_options_add_page');

// Init plugin options to white list our options
function glip_options_init(){
	register_setting( 'glip_webhook_options', 'glip_webhook', 'glip_options_validate' );
}

// Add menu page
function glip_options_add_page() {
	add_options_page('Glip', 'Glip', 'manage_options', 'glip_options', 'glip_options_do_page');
}

// Draw the menu page itself
function glip_options_do_page() {
	?>
<div class="wrap">
		<h2>Glip Options</h2>
		<form method="post" action="options.php">
			<?php settings_fields('glip_webhook_options'); ?>
			<?php $webhook = get_option('glip_webhook'); ?>
       WebHooks address:     <input name="glip_webhook" id="glip_webhook"value="<?php echo $webhook?>" />
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
		<p>You will find this web address in the settings screen for the WebHooks integration, which is included with every Glip account. A menu in the upper right hand corner allows you to change the team conversation updates will be posted to.</p>

<p><img src="<?php echo plugins_url('glip-webhooks.png',__FILE__); ?>" width="580" height="372" alt="Webhooks" /></p>

	</div>
	<?php	
}

// Sanitize and validate input.
function glip_options_validate($input) {

	if (!filter_var($input, FILTER_VALIDATE_URL) === false) {
		return $input;
	} else {
		return '';
	}

}

function glip_admin_notice () {
$w = get_option('glip_webhook');
if(empty ($w) )
	printf('<div class="error">%s <a href="%s">%s</a></div>',__('WebHooks URL must be set for ','glipnotifier'),admin_url('options-general.php?page=glip_options'), __('Glip integration','glipnotifier'));
	
}

add_action('admin_notices', 'glip_admin_notice');
?>