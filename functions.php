<?php
function woffice_child_scripts() {
	if ( ! is_admin() && ! in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) ) {
		$theme_info = wp_get_theme();
    wp_enqueue_style( 'woffice-child-stylesheet', get_stylesheet_uri(), array(), WOFFICE_THEME_VERSION );
    wp_enqueue_style( 'adobe-fonts', 'https://use.typekit.net/ifu0pdf.css');
	}
}
add_action('wp_enqueue_scripts', 'woffice_child_scripts', 30);

add_action('after_setup_theme', function () {

	// Load custom translation file for the parent theme
	load_theme_textdomain( 'woffice', get_stylesheet_directory() . '/languages/' );

	// Load translation file for the child theme
	load_child_theme_textdomain( 'woffice', get_stylesheet_directory() . '/languages' );
});

// ----------------
// Stay Other stuff
// ----------------

require_once get_template_directory() .'/inc/init.php';
define('WOFFICE_THEME_VERSION', '2.8.7');

// -------
// helpers
// -------

function so_is_project($post) {
	return get_post_type($post) == 'project';
}

function so_get_project_members_from_comment($comment_id) {
	//returns an array of project member IDs based on a comment posted in that project

	$comment = get_comment($comment_id);
	$post_id = $comment->comment_post_ID;

	$project_details = get_post_meta($post_id, 'fw_options', true);
	$project_member_ids = isset($project_details['project_members']) ? $project_details['project_members'] : '';

	return $project_member_ids;
}

function so_is_commenter($potential_author, $comment_id) {
	$comment = get_comment($comment_id);
	$comment_author = $comment->comment_author;

	return $potential_author == $comment_author;
}

// -----
// hooks
// -----

function so_notify_all_followers()
{
	//priority before other function
	//check post for comment
	//if post is a project, then get all the 'followers'
	//send email to all followers
}

function so_comment_moderation_recipients($emails, $comment_id) {
	// Email code inspired by: http://www.sourcexpress.com/customize-wordpress-comment-notification-emails/
	// Woffice functions: see helpers.php in woffice-core > extensions > woffice-projects
	/*$comment = get_comment($comment_id);
	$post_id = $comment->comment_post_ID;
	$project_member_ids = woffice_get_project_members($post_id); //returns array with user IDs
	$project_members = ( !empty($post_id) ) ? woffice_get_project_members($post_id) : array();

	echo "post id: ".$post_id;
	echo "<br />function exists: ".function_exists('woffice_get_project_members');
	echo "<br />project member ids: ".count($project_members);*/

	$project_member_ids = so_get_project_members_from_comment($comment_id);
	foreach ($project_member_ids as $project_member_id) {
		$user = get_user_by('id', $project_member_id);
		$email = $user->user_email;
		$name = $user->display_name;

		if (!empty($email) && !in_array($email, $emails) && !so_is_commenter($name, $comment_id)) {
			$emails[] = $email;
		}
	}

    return $emails;
}
add_filter('comment_moderation_recipients', 'so_comment_moderation_recipients', 11, 2);
add_filter('comment_notification_recipients', 'so_comment_moderation_recipients', 11, 2);


function so_comment_notification_text($notify_message, $comment_id) {
	//inspired by https://www.webhostinghero.com/how-to-change-the-comment-notification-email-in-wordpress/

	$comment = get_comment($comment_id);
	$post_id = $comment->comment_post_ID;

	if (so_is_project($post_id)) {
		$post = get_post($post_id);

		$so_message = $comment->comment_author." posted a new comment in \"".$post->post_title."\":\r\n\r\n";
		$so_message .= $comment->comment_content."\r\n\r\n";
		$so_message .= get_permalink($post_id)."#project-content-comments";

		return $so_message;
	} else {
		return $notify_message;
	}
}
add_filter('comment_notification_text', 'so_comment_notification_text', 10,2);


function so_add_bp_notification($comment_id, $comment_approved) {
	$comment = get_comment($comment_id);
	$project_post_id = $comment->comment_post_ID;

	if (so_is_project($project_post_id)) {
		$user_ids_to_notify = so_get_project_members_from_comment($comment_id);

		$comment = get_comment($comment_id);
		$comment_author = get_user_by('email', $comment->comment_author_email);
		$comment_author_id = $comment_author->ID;

		foreach ($user_ids_to_notify as $user_id_to_notify) {
			$user_to_notify = get_user_by('id', $user_id_to_notify);
			$name_to_notify = $user->display_name;

			if (!so_is_commenter($name_to_notify, $comment_id)) {
				$notification_args = array (
					'user_id'			=> $user_id_to_notify,
					'item_id'			=> $project_post_id,	//project_id
					'secondary_item_id' => $comment_author_id,	//check this
					'component_name'    => 'woffice_project',
					'component_action'  => 'woffice_project_comment',
				);

				$notification_id = bp_notifications_add_notification($notification_args);
			}
		}
	} else {
		return false;
	}
}
add_action('comment_post', 'so_add_bp_notification', 10, 2);
//add_action('transition_comment_status', 'so_add_bp_notification', 10, 3);
add_action( 'edit_comment', 'so_add_bp_notification', 10    );


/*
 * Function for post duplication. Dups appear as drafts. User is redirected to the edit screen
 */

  add_action( 'admin_action_rd_duplicate_post_as_draft', 'matrix_project_as_draft' ); 

  function matrix_project_as_draft()
    {
          global $wpdb;

          /*sanitize_GET POST REQUEST*/
          $post_copy = sanitize_text_field( $_POST["post"] );
          $get_copy = sanitize_text_field( $_GET['post'] );
          $request_copy = sanitize_text_field( $_REQUEST['action'] );

          $opt = get_option('dpp_wpp_page_options');
          $suffix = !empty($opt['dpp_post_suffix']) ? ' -- '.$opt['dpp_post_suffix'] : '';
          $post_status = !empty($opt['dpp_post_status']) ? $opt['dpp_post_status'] : 'draft';
          $redirectit = !empty($opt['dpp_post_redirect']) ? $opt['dpp_post_redirect'] : 'to_list';

            if (! ( isset( $get_copy ) || isset( $post_copy ) || ( isset($request_copy) && 'matrix_project_as_draft' == $request_copy ) ) ) {
            	wp_die('No post!');
            }
            $returnpage = '';

            /* Get post id */
            $post_id = (isset($get_copy) ? $get_copy : $post_copy );

            $post = get_post( $post_id );

            $current_user = wp_get_current_user();
            $new_post_author = $current_user->ID;

            /*Create the post Copy */
            if (isset( $post ) && $post != null) {
                /* Post data array */
                $args = array('comment_status' => $post->comment_status,
                'ping_status' => $post->ping_status,
                'post_author' => $new_post_author,
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
                'post_name' => $post->post_name,
                'post_parent' => $post->post_parent,
                'post_password' => $post->post_password,
                'post_status' => $post_status,
                'post_title' => $post->post_title.$suffix,
                'post_type' => $post->post_type,
                'to_ping' => $post->to_ping,
                'menu_order' => $post->menu_order

               );
               $new_post_id = wp_insert_post( $args );

               $taxonomies = get_object_taxonomies($post->post_type);
               if(!empty($taxonomies) && is_array($taxonomies)):
               foreach ($taxonomies as $taxonomy) {
                  	$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
                  	wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);}
               endif;

               $post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");

               if (count($post_meta_infos)!=0) {

               $sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";

               foreach ($post_meta_infos as $meta_info) {
	                  $meta_key = $meta_info->meta_key;
	                  $meta_value = addslashes($meta_info->meta_value);
	                  $sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
	                  }
	                    $sql_query.= implode(" UNION ALL ", $sql_query_sel);
	                    $wpdb->query($sql_query);
	                  }

                 /*choice redirect */
                 if($post->post_type != 'post'):$returnpage = '?post_type='.$post->post_type;  endif;
                 if(!empty($redirectit) && $redirectit == 'to_list'):wp_redirect( admin_url( 'edit.php'.$returnpage ) );
                 elseif(!empty($redirectit) && $redirectit == 'to_page'):wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
                 else:

                 wp_redirect( admin_url( 'edit.php'.$returnpage ) );
                 endif;
                 exit;

                 } else {

                 wp_die('Error! Post creation failed: ' . $post_id);

                 }
   }

/*
 * Add the duplicate link to action list for post_row_actions
 */
//Thomas 04/14/2020: I don't thinkt his does anything since it's hooking to the wrong filter (post_row_actions vs. page_row_actions)
function duplicate_project_link( $actions, $post ) {

    //print_r($actions);
    //if (current_user_can('edit_posts') || $post->post_type=='movies') {
        $actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=rd_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce' ) . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
   // }
    return $actions;
}

add_filter('page_row_actions', 'duplicate_project_link', 10, 2);
add_shortcode('duplicate_post','duplicate_project_link');

function custom_copy($src, $dst) {

	// open the source directory
	$dir = opendir($src);

	// Make the destination directory if not exist
    @mkdir($dst);

	// Loop through the files in source directory
	foreach (scandir($src) as $file) {

		if (( $file != '.' ) && ( $file != '..' )) {
			if ( is_dir($src . '/' . $file) )
			{

				// Recursively calling custom copy function
				// for sub directory
				custom_copy($src . '/' . $file, $dst . '/' . $file);

			}
			else {
				copy($src . '/' . $file, $dst . '/' . $file);
			}
		}
	}
	closedir($dir);
}



function dateDiffInDays($date1, $date2)
{
    // Calulating the difference in timestamps
    $diff = strtotime($date2) - strtotime($date1);

    // 1 day = 24 hours
    // 24 * 60 * 60 = 86400 seconds
    return abs(round($diff / 86400));
}

function so_duplication_modal($project_post_id) {
  //Creates a modal for project duplication

  $duplicate_url = site_url('wp-content/plugins/so-functionality/duplicate.php');

  $end_date = get_post_meta($project_post_id, 'fw_option:project_date_end'); 
	$endingday = date('m/d/Y',( strtotime($end_date[0])));
  ?>
  <div class="modal fade" id="exampleModalCenter<?php echo $project_post_id; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle<?php echo $project_post_id; ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered cstm-dialog" role="document">
      <div class="modal-content">
        <form  onsubmit="return validationpopup();"  action="<?php echo $duplicate_url ?>" post="get">
          <input type="hidden" name="post" value="<?php echo  $project_post_id; ?>">
          <input type="hidden" name="action" value="rd_duplicate_post_as_draft">
          <input type="hidden" name="duplicate_nonce" value="<?php echo  basename(__FILE__);?>">
          <div class="modal-header">
            <h4 id="exampleModalLongTitle">Duplicate Project</h4>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <label for="project_title"><h6>New Project Title</h6></label>
            <input type="text" name="project_title">
            <div class="modal-title-error"></div>
          </div>
          <div class="modal-body">
            <label for="project_title"><h6>Project Deadline</h6></label>
            <div class="cstm-fld-mt">
            <input type="text" class="deadlinedate" name="project_deadline" value="<?php echo $endingday; ?>">
            <input type="hidden" class="hiddendeadlinevalue<?php echo $project_post_id; ?>" name="hiddendeadlinevalue"   value="<?php echo $endingday; ?>">
          </div>
          <h6>Tasks</h6>
          <div class="modal-tbl">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Task</th>
                  <th>Auto populate</th>
                  <th>Manual Due Date</th>
                  <th>Don't Duplicate</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $postmeta = get_post_meta($project_post_id, 'fw_options');
                  $i = 0;
                  $x = 0;
                  $t = 0;
                  $randomId = uniqid();
                    foreach ($postmeta as $value) {
                      $project_todo_lists_main_date = '';

                      foreach ($value['project_todo_lists'] as  $project_todo_lists ) {
                        if($i == 0){
                          $wordpressdate = get_option('date_format');
                          if($wordpressdate == 'F j, Y'){
                            $date1 = strtr($project_todo_lists['date'], '/', '-');
                            $project_todo_lists_main_date  =  date('m/d/Y', strtotime($date1));
                          }elseif ($wordpressdate == 'Y-m-d') {
                            $date2 = strtr($project_todo_lists['date'], '/', '-');
                            $project_todo_lists_main_date  =  date('Y-m-d', strtotime($date2));
                          }elseif ($wordpressdate == 'Y-m-d') {
                            $date2 = strtr($project_todo_lists['date'], '/', '-');
                            $project_todo_lists_main_date  =  date('Y-m-d', strtotime($date2));
                          }elseif ($wordpressdate == 'm/d/Y') {
                            $date2 = strtr($project_todo_lists['date'], '/', '-');
                            $project_todo_lists_main_date  =  date('Y-m-d', strtotime($date2));
                          }elseif ($wordpressdate == 'd/m/Y') {
                            $date2 = strtr($project_todo_lists['date'], '/', '-');
                            $project_todo_lists_main_date  =  date('d/m/Y', strtotime($date2));
                          }else{
                            $date2 = strtr($project_todo_lists['date'], '/', '-');
                            $project_todo_lists_main_date  =  date($wordpressdate, strtotime($date2));
                          }
                          ?>

                <tr class="row-handler">
                  <td><?php echo $project_todo_lists['title']; ?></td>
                  <td>
                    <div class="rd-fld radio1">
                      <label class="cstm-radio-btn">
                        <input type="radio" id="chackedid_2<?php echo $x.$randomId; ?>"  data-referenceclass="input_class_<?php echo $x.$randomId; ?>" data-relatedelment='fildstudo_<?php echo $x.$randomId; ?>' value="radioone<?php echo $x; ?>" onclick="getradiovalues(this);" name="autopopulate[<?php echo $project_post_id; ?>][<?php echo strtolower(str_replace(' ', '',$project_todo_lists['title'])); ?>]" checked class="radiobtn1">
                        <span class="checkmark"></span>
                      </label>
                      <div class="date-fld datefieldstudo1">
                        <input type="text" readonly name="ship_uniform[Autopopulate][<?php echo strtolower(str_replace(' ', '',$project_todo_lists['title'])); ?>]" id="fildstudo_<?php echo $x.$randomId; ?>" class="txt-fld input_class_<?php echo $x.$randomId; ?> dateone<?php echo $project_post_id; ?>" value="<?php echo  $project_todo_lists_main_date; ?>">
                        <input type="hidden" value="<?php echo $project_todo_lists_main_date; ?>" name="taskdeadlineinput" class="tdeadlineinput<?php echo $project_post_id; ?>" id="todoidhidden<?php echo $x.$randomId; ?>">
                        <input type="hidden" name="posttitle[]" value="<?php echo $project_todo_lists['title']; ?>">
                        <input type="hidden" name="posttitle_name[]" value="<?php echo strtolower(str_replace(' ', '',$project_todo_lists['title'])); ?>">
                      </div>
                    </div>
                    <?php
                      $project_todo_lists_main_date2  =  date('d-m-Y', strtotime($project_todo_lists_main_date));
                      $end_datekk  =  date('d-m-Y', strtotime($end_date[0]));
                      $date1 = new DateTime($project_todo_lists_main_date2);
                      $date2 = new DateTime($end_datekk);
                      $interval = $date1->diff($date2);
                      $days_ = $interval->days;

                      echo "<div class=\"date-distance\">".$days_." Days Before Deadline</div>";
                    ?>
                    </div>
                  </td>
                  <td>
                    <div class="rd-fld radio2">
                      <label class="cstm-radio-btn">
                        <input type="radio" class="radiobtn2" id="chackedid_1_<?php echo $x.$randomId; ?>" value="radioTwo<?php echo $x; ?>" data-referenceclass="input_class_<?php echo $x.$randomId; ?>" onclick="getradiovalues(this);"name="autopopulate[<?php echo $project_post_id; ?>][<?php echo strtolower(str_replace(' ', '',$project_todo_lists['title'])); ?>]" data-relatedelment='fildstudo_2_<?php echo $x.$randomId; ?>' >
                        <span class="checkmark"></span>
                      </label>
                      <div class="date-fld datefieldstudo2"  ><input id="fildstudo_2_<?php echo $x.$randomId; ?>" disabled type="text" value="<?php echo  $project_todo_lists_main_date; ?>" name="submit_final_payment[ManualDueDate][<?php echo strtolower(str_replace(' ', '',$project_todo_lists['title'])); ?>]" class="txt-fld input_class_<?php echo $x.$randomId; ?> projectline"></div>
                    </div>
                  </td>
                  <td>
                    <div class="rd-fld radio3">
                      <label class="cstm-radio-btn">
                        <input type="radio" id="chackedid_<?php echo $x.$randomId; ?>" value="radioThree<?php echo $x; ?>" onclick="getradiovalues(this);"  name="autopopulate[<?php echo $project_post_id; ?>][<?php echo strtolower(str_replace(' ', '',$project_todo_lists['title'])); ?>]" data-referenceclass="input_class_<?php echo $x.$randomId; ?>" data-relatedelment='fildstudo_3_<?php echo $x.$randomId; ?>' >
                        <input type="hidden"  name="dont[dontduplicate][<?php echo strtolower(str_replace(' ', '',$project_todo_lists['title'])); ?>]"   id="fildstudo_3_<?php echo $x.$randomId; ?>" disabled class="txt-fld input_class_<?php echo $x.$randomId; ?> datethree"  value="<?php echo  $project_todo_lists['title']; ?>">
                        <span class="checkmark"></span>
                      </label>
                    </div>
                  </td>
                </tr>

                <?php $x++; }	  $t++;}    $i++;     }   ?>

              </tbody>
            </table>
          </div>
        </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal" <?php echo $project_post_id; ?>>Cancel</button>&nbsp;&nbsp;&nbsp;
            <input class="clone_link btn btn-secondary" type="submit" name="submitbtn" value="Create">
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php
  so_modal_dates($project_post_id);
}

function so_modal_dates($project_post_id) {
  //The JS to update task deadlines when project deadline changes (respecting the amount of days before the deadline)
  ?>
  <script type="text/javascript">
  jQuery(function () {
    jQuery("#exampleModalCenter<?php echo $project_post_id; ?> .deadlinedate").datepicker({
        numberOfMonths: 1,
        container: "#exampleModalCenter<?php echo $project_post_id; ?> .modal-content", //make .modal-content the container element so datepicker scrolls with modal
        autoclose: true,

    }).on("changeDate", function (selected) {
      test_calender = null;
      gg = jQuery(this).val();
      jQuery("#exampleModalCenter<?php echo $project_post_id; ?> .projectline").datepicker('setEndDate', gg);

      var old_value = jQuery("#exampleModalCenter<?php echo $project_post_id; ?> .hiddendeadlinevalue<?php echo $project_post_id; ?>").val();
      var gg = new Date(gg);
      var tdate2 = old_value;
      var tdate2 = new Date(tdate2);
      var Difference_In_Time_1 = gg.getTime() - tdate2.getTime();
      var Difference_In_Days_1 = Difference_In_Time_1 / (1000 * 3600 * 24);
      var rulesid = [];

      jQuery( "#exampleModalCenter<?php echo $project_post_id; ?> .dateone<?php echo $project_post_id; ?>" ).each(function( index ) {
        var fid = this.id;
        rulesid.push(fid);
        var datepickerdate_add_days = jQuery("#"+fid).next().val();

        var addingdays = new Date(datepickerdate_add_days);

        daysmove = addingdays.setDate(addingdays.getDate()+Difference_In_Days_1);

          d = new Date(daysmove);
                month = "" + (d.getMonth() + 1),
                day = "" + d.getDate(),
                year = d.getFullYear();

            if (month.length < 2) month = "0" + month;
            if (day.length < 2) day = "0" + day;

          var maindateforapply = [month, day, year].join("/");

          jQuery("#"+fid).val(maindateforapply);
      });

      if(Difference_In_Days_1 == 0){
        var t = 0;
          jQuery( "#exampleModalCenter<?php echo $project_post_id; ?> .tdeadlineinput<?php echo $project_post_id; ?>" ).each(function( index) { 
            var innervalue = this.id;
                mainruleid = rulesid[t];

            var innserhidenvalue = jQuery("#"+innervalue).val();
                  jQuery("#"+mainruleid).val(innserhidenvalue);
          t++;   });
      }
    });
        jQuery("#exampleModalCenter<?php echo $project_post_id; ?> .projectline").datepicker({
          endDate:jQuery("#exampleModalCenter<?php echo $project_post_id; ?> .deadlinedate").val(),
          container: "#exampleModalCenter<?php echo $project_post_id; ?> .modal-content", //make .modal-content the container element so datepicker scrolls with modal
          autoclose: true,
        });
  });
  </script>
  <?php
}

function so_modal_validation() {
  //The JS to validate the modal form before submission
  ?>
  <script>
    jQuery('.clone_link').on('click', function(){
      var text = jQuery(this).parent('.modal-footer').siblings('.modal-body').children('input[name="project_title"]').val();
      if(text === ''){
        jQuery(".modal-title-error").html("This field is required");
        return false;
      }
      jQuery("input[name=project_title]").parent().append('');

      var dateinput_value = [];
      var dateinput_value2 = [];

      jQuery('.radiobtn1').each(function(){
        var dateinput_value_d='';

        if(jQuery(this).is(':checked')){
          dateinput_value_d = jQuery(this).parent('.cstm-radio-btn').siblings('.date-fld').children('input[name="ship_uniform"]').val();     
        }else{
          dateinput_value_d='';
        }
        if(dateinput_value_d!=''){
          var obj = {};
          obj.Autopopulate = dateinput_value_d;
          dateinput_value.push(obj);
        }
      });

      jQuery('.radiobtn2').each(function(){
        var dateinput_value_two ='';

        if(jQuery(this).is(':checked')){
          dateinput_value_two = jQuery(this).parent('.cstm-radio-btn').siblings('.date-fld').children('input[name="submit_final_payment"]').val();
        }else {
          dateinput_value_two='';
        }
        if(dateinput_value_two!=''){
          var obj2 = {};
          obj2.ManualDueDate = dateinput_value_two;
          dateinput_value2.push(obj2);
        }
      });

      var submit_final_payment = jQuery(this).parent('.modal-footer').siblings('.modal-body').children('input[name="submit_final_payment"]').val();
    });
  </script>
  <?php
}

//SO add 'Duplicate' button to project menu
//overrides similar function in Woffice code
function woffice_get_project_menu($post)
{
  /**
   * Returns the Project Menu
   *
   * @param $post WP_Post
   * @return string
   */


    $html = '<ul class="woffice-tab-layout__nav">';
    $current_user_is_admin = woffice_current_is_admin();
    /* View Link */
    $html .= '<li id="project-tab-view" class="active" data-tab="view">
  <a href="#project-content-view" class="fa-file">' . __("Overview", "woffice") . '</a>
</li>';

    /* Edit Link */
    $project_edit = (function_exists('fw_get_db_post_option')) ? fw_get_db_post_option(get_the_ID(), 'project_edit') : '';
    if ($project_edit != 'no-edit' && is_user_logged_in()) :
        $user_can_edit = woffice_current_user_can_edit_project(get_the_ID());

        if($user_can_edit) {
            $html .= '<li id="project-tab-edit" data-tab="edit">';
            if ($project_edit == 'frontend-edit'):
                $html .= '<a href="#project-content-edit" class="fa-edit">' . __("Edit", "woffice") . '</a>';
            else :
                $html .= '<a href="' . get_edit_post_link($post->ID) . '" class="fa-pencil-square">' . __("Edit", "woffice") . '</a>';
            endif;
            $html .= '</li>';
        }
    endif;

    /* To-do Link */
    // IF TO-DO IS ENABLED
    $project_todo = (function_exists('fw_get_db_post_option')) ? fw_get_db_post_option(get_the_ID(), 'project_todo') : '';
    if ($project_todo):
        $html .= '<li id="project-tab-todo" data-tab="todo">
    <a href="#project-content-todo" class="fa-clipboard-list">' . __("Tasks", "woffice") . '</a>
  </li>';
    endif;

  /* Calendar Link */
  $project_calendar = (function_exists('fw_get_db_post_option')) ? fw_get_db_post_option(get_the_ID(), 'project_calendar') : '';
  if ($project_calendar === true && fw_ext('woffice-event')) :
    $html .= '<li id="project-tab-calendar" data-tab="project-content-calendar">
                <a href="#project-content-calendar" class="fa-calendar-alt">' . __("Calendar", "woffice") . '</a>
            </li>';
  endif;

    /* Files Link */
    // IF THERE IS FILES
    $project_files = (function_exists('fw_get_db_post_option')) ? fw_get_db_post_option(get_the_ID(), 'project_files') : '';
    if (!empty($project_files)):
        $html .= '<li id="project-tab-files" data-tab="files">
    <a href="#project-content-files" class="fa-folder-open">' . __("Files", "woffice") . '</a>
  </li>';
    endif;

    /* Comments Link */
    if (comments_open() && woffice_projects_have_comments()) {
        $html .= '<li id="project-tab-comments" data-tab="comments">
    <a href="#project-content-comments" class="fa-comments">
      ' . __("Messages", "woffice") . '
      <span>' . get_comments_number() . '</span>
    </a>
  </li>';
    }

    //SO start
    $project_post_id=$post->ID;

    if(current_user_can('manage_options') ){
        $html .= '<li id="project-tab-duplicate" data-tab="duplicate">';
        $html .= '<a href="#exampleModalCenter" class="btn btn-primary fa-duplicate" data-toggle="modal" data-target="#exampleModalCenter'.$project_post_id.'" >' . __("Duplicate", "woffice") . '</a>';

        $html .= so_duplication_modal($project_post_id);
        $html .= so_modal_validation();
        $html .= "</li>";
    }
    //SO end

    /* Delete Link */
    $user = wp_get_current_user();
  $user_can_delete = ($post->post_author == $user->ID || $current_user_is_admin);

    /**
     * Filter if the user can delete a project
     *
     * @param bool $user_can_delete If the user can delete or not the project
     * @param WP_Post $post The project post
     * @param WP_user $user The user object
     *
     */
  $user_can_delete = apply_filters( 'woffice_user_can_delete_project', $user_can_delete, $post, $user);

    if ( $user_can_delete ) :
        $html .= '<li id="project-tab-delete">
    <a onclick="return confirm(\'' . __('Are you sure you wish to delete article :', 'woffice') . ' ' . get_the_title() . ' ?\')" href="' . get_delete_post_link(get_the_ID(), '') . '" class="fa-trash">
      ' . __("Delete", "woffice") . '
    </a>
  </li>';
    endif;

    $html .= '</ul>';

    return $html;
  }

// loads and registers custom JS script
function load_custom_scripts() {
    if( !is_admin()){
        wp_register_script( 'custom', '/wp-content/themes/woffice-child-theme/js/custom.js', array('jquery'), WOFFICE_THEME_VERSION);
        wp_enqueue_script('custom');
    }
}
add_action('wp_enqueue_scripts', 'load_custom_scripts', 30);

// Redirect to the comment tab after commenting
// add_action('comment_post_redirect', 'redirect_to_comment_tab');

// function redirect_to_comment_tab() {
//     $relative_path = $_SERVER['HTTP_REFERER'] . $_SERVER['PHP_SELF'];
//     $comment_tab_url = str_replace("/wp-comments-post.php", "/#project-content-comments", $relative_path);
//     return $comment_tab_url;
// }

add_action( 'wp_enqueue_scripts', 'misha_ajax_comments_scripts' );

function misha_ajax_comments_scripts() {

	// just register for now, we will enqueue it below
	wp_register_script( 'ajax_comment', get_stylesheet_directory_uri() . '/js/ajax-comment.js', array('jquery') );

	// let's pass ajaxurl here, you can do it directly in JavaScript but sometimes it can cause problems, so better is PHP
	wp_localize_script( 'ajax_comment', 'misha_ajax_comment_params', array(
		'ajaxurl' => site_url() . '/wp-admin/admin-ajax.php'
	) );

 	wp_enqueue_script( 'ajax_comment' );
}

add_action( 'wp_ajax_ajaxcomments', 'misha_submit_ajax_comment' ); // wp_ajax_{action} for registered user
add_action( 'wp_ajax_nopriv_ajaxcomments', 'misha_submit_ajax_comment' ); // wp_ajax_nopriv_{action} for not registered users

function misha_submit_ajax_comment(){
	/*
	 * Wow, this cool function appeared in WordPress 4.4.0, before that my code was muuuuch mooore longer
	 *
	 * @since 4.4.0
	 */
	$comment = wp_handle_comment_submission( wp_unslash( $_POST ) );
	if ( is_wp_error( $comment ) ) {
		$error_data = intval( $comment->get_error_data() );
		if ( ! empty( $error_data ) ) {
			wp_die( '<p>' . $comment->get_error_message() . '</p>', __( 'Comment Submission Failure' ), array( 'response' => $error_data, 'back_link' => true ) );
		} else {
			wp_die( 'Unknown error' );
		}
	}
 
	/*
	 * Set Cookies
	 */
	$user = wp_get_current_user();
	do_action('set_comment_cookies', $comment, $user);

	/*
	 * If you do not like this loop, pass the comment depth from JavaScript code
	 */
	$comment_depth = 1;
	$comment_parent = $comment->comment_parent;
	while( $comment_parent ){
		$comment_depth++;
		$parent_comment = get_comment( $comment_parent );
		$comment_parent = $parent_comment->comment_parent;
	}

 	/*
 	 * Set the globals, so our comment functions below will work correctly
 	 */
	$GLOBALS['comment'] = $comment;
    $GLOBALS['comment_depth'] = $comment_depth;
    $comment_id = get_comment_ID();

	/*
	 * Here is the comment template, you can configure it for your website
	 * or you can try to find a ready function in your theme files
	 */
	$comment_html = '<li ' . comment_class('', null, null, false ) . ' id="comment-' . get_comment_ID() . '">
		<article class="comment-body" id="div-comment-' . get_comment_ID() . '">
			<footer class="comment-meta">
				<div class="comment-author vcard">
					' . get_avatar( $comment, 100 ) . '
					<b class="fn">' . get_comment_author_link() . '</b> <span class="says">says:</span>
				</div>
				<div class="comment-metadata">
					<a href="' . esc_url( get_comment_link( $comment->comment_ID ) ) . '">' . sprintf('%1$s at %2$s', get_comment_date(),  get_comment_time() ) . '</a>';

					if( $edit_link = get_edit_comment_link() )
						$comment_html .= '<span class="edit-link"><a class="comment-edit-link" href="' . $edit_link . '">Edit</a></span>';

				$comment_html .= '</div>';
				if ( $comment->comment_approved == '0' )
					$comment_html .= '<p class="comment-awaiting-moderation">Your comment is awaiting moderation.</p>';

			$comment_html .= '</footer>
			<div class="comment-content">' . apply_filters( 'comment_text', get_comment_text( $comment ), $comment ) . '</div>
		</article>
    </li>';
    $arr = array($comment_html, $comment_id);
    echo json_encode($arr);

	die();
}

if ( (isset($_GET['action']) && $_GET['action'] != 'logout') || (isset($_POST['login_location']) && !empty($_POST['login_location'])) ) {
    add_filter('login_redirect', 'my_login_redirect', 10, 3);
    function my_login_redirect() {
            $location = $_SERVER['HTTP_REFERER'];
            wp_safe_redirect($location);
            exit();
    }
}

if(!function_exists('woffice_current_user_can_check_task')) {
	/**
	 * Check if the current user can check the given task
	 *
	 * @param array $task
	 * @param WP_Post $project
	 * @param null|bool $allowed_edit_project If the current user has the permissions to edit the project. If null it will be calculated into the function
	 *
	 * @return bool
	 */
	function woffice_current_user_can_check_task( $task, $project, $allowed_edit_project = null ) {

	    if (!is_user_logged_in()) {
            return false;
        }

		if (is_null($allowed_edit_project)) {
			$allowed_edit_project = woffice_current_user_can_edit_project( $project->ID );
		}

		//if ($allowed_edit_project) {
			//$allowed_check = true;
		//} else {
			$allowed_check = (in_array(get_current_user_id(), $task['assigned']));
		//}

	    /**
         * Filter if the current user can check a project task. By default every user who can edit a project, can also
         * check the tasks
         *
         * @param bool $allowed_check If the user can check the task or not
         * @param array $task
         * @param WP_Post $project
         * @param bool $allowed_edit_project If the current user is allowed to edit the project
         */
		return apply_filters( 'woffice_allowed_check_project_task', $allowed_check, $task, $project, $allowed_edit_project );
	}
}


if(!function_exists('woffice_project_notification_members_added')) {
    /**
     * Add BuddyPress notification for the Project, whenever a member is added
     *
     * @throws
     *
     * @param int $post_id
     * @param WP_Post $post
     */
    function woffice_project_notification_members_added($post_id, $post) {

        if ( $post->post_type != 'project' || ! Woffice_Notification_Handler::is_notification_enabled('project-member-assigned')) {
            return;
        }
		
		// Already assigned members
		$already_assigned_members = get_post_meta($post_id, 'cst_project_members', true);
		
		// Assigned members
        $members_assigned = fw_get_db_post_option($post_id, 'project_members');

		if(!empty($already_assigned_members)){
			$result=array_diff($members_assigned,$already_assigned_members);
			if($result){
				cst_woffice_projects_assigned_email($post_id, $result);
			}
			update_post_meta($post_id, 'cst_project_members', $members_assigned);
		}
		else{
			cst_woffice_projects_assigned_email($post_id, $members_assigned);
			update_post_meta($post_id, 'cst_project_members', $members_assigned);
		}


        foreach ($members_assigned as $member_id) {
            bp_notifications_add_notification( array(
                'user_id'           => $member_id,
                'item_id'           => $post_id,
                'secondary_item_id' => get_current_user_id(),
                'component_name'    => 'woffice_project',
                'component_action'  => 'woffice_project_assigned_member',
                'date_notified'     => bp_core_current_time(),
                'is_new'            => 1,
            ) );
        }

    }
}

function cst_woffice_projects_assigned_email($post_id, $members = array()) {
	
	if(!$members){
		return;
	}

	$post_title = get_the_title( $post_id );
	$post_url = get_permalink( $post_id );

	/* Then, We send the email : */
	$subject = $post_title . ' '. __('project update','woffice');

	/**
	 * Filter the subject of the email sent when a new task is assigned to a member
	 *
	 * @param string $subject The subkect string
	 * @param string $post_title The title of the post
	 * @param string $todo['title'] The title of the task
	 */
	$subject = apply_filters('woffice_projects_assigned_email_subject', $subject, $post_title);

	$message = "You've been assigned the following task: ".$post_title;
	$message = str_replace('{project_url}', $post_url, $message);

	// Send email to the user.
	$assigned_ready = (!is_array($members)) ? explode(",",$members) : $members;
	foreach ($assigned_ready as $assigned) {
		$user_info = get_userdata($assigned);
		$user_email = $user_info->user_email;
		$headers = null;

		/**
		 * Filter the headers of the email sent when a new task is assigned to a member
		 *
		 * @param array $headers
		 */
		$headers = apply_filters('woffice_projects_assigned_email_headers', $headers);

		$email = wp_mail($user_email, $subject, $message, $headers);
		
	}
	
}

function cst_woffice_projects_task_complete_email($post_id, $project_todo_lists) {

	$curruser = get_current_user_id();
	$post_title = get_the_title( $post_id );
	$post_url = get_permalink( $post_id );
	$post_author = get_post_field ('post_author', $post_id);
	$subject = $post_title.": Task completed";
/* var_dump($post_title);
var_dump($post_author); */
	$message = "Task for ".$post_title." has been completed";
	$message = str_replace('{project_url}',     $post_url, $message);

	// Send email to the user.


		$user_info = get_userdata($post_author);
		$user_email = $user_info->user_email;
		$headers = null;

		/**
		 * Filter the headers of the email sent when a new task is assigned to a member
		 *
		 * @param array $headers
		 */
		$headers = apply_filters('woffice_projects_assigned_email_headers', $headers);

		$email = wp_mail($user_email, $subject, $message, $headers);
		bp_notifications_add_notification(
							array(
								'user_id'           => $post_author,
								'item_id'           => $post_id,
								'secondary_item_id' => get_current_user_id(),
								'component_name'    => 'woffice_project',
								'component_action'  => 'woffice_project_task_completed',
								'date_notified'     => bp_core_current_time(),
								'is_new'            => 1,
							) 
						);
		foreach ($project_todo_lists as $key=>$todo) {
				$sent_counter = 0;
				$post_title = get_the_title( $post_id );
				$post_url = get_permalink( $post_id );
			

				/**
				 * Filter the subject of the email sent when a new task is assigned to a member
				 *
				 * @param string $subject The subkect string
				 * @param string $post_title The title of the post
				 * @param string $todo['title'] The title of the task
				 */
		
				// Send email to the user.
				$assigned_ready = (!is_array($todo['assigned'])) ? explode(",",$todo['assigned']) : $todo['assigned'];
				foreach ($assigned_ready as $assigned) {
					$user_info = get_userdata($assigned);
					if($curruser == $user_info->ID){
						$user_email = $user_info->user_email;
						$headers = null;

						$message = str_replace('{user_name}', woffice_get_name_to_display($assigned), $message);

						/**
						 * Filter the headers of the email sent when a new task is assigned to a member
						 *
						 * @param array $headers
						 */
						$headers = apply_filters('woffice_projects_assigned_email_headers', $headers);

						$email = wp_mail($user_email, $subject, $message, $headers);
						bp_notifications_add_notification(
							array(
								'user_id'           => $curruser,
								'item_id'           => $post_id,
								'secondary_item_id' => get_current_user_id(),
								'component_name'    => 'woffice_project',
								'component_action'  => 'woffice_project_task_completed',
								'date_notified'     => bp_core_current_time(),
								'is_new'            => 1,
							) 
						);
					}
					
				}
		
		}
	
}

if(!function_exists('woffice_todos_update')) {
    /**
     * We update the To-Dos using AJAX
     */
    function woffice_todos_update()
    {

        if(!check_ajax_referer('woffice_todos') || !isset($_POST['id']) || !isset($_POST['type'])) {
            echo json_encode(array('status' => 'fail'));
            die();
        }

        // We get the ID from the current Project post
        $id = intval($_POST['id']);
        $project_sync = fw_get_db_post_option($id, 'project_calendar');
        // We get the type of update : add / delete / check / order / edit
        $type = $_POST['type'];

        // We get the todos
        $todos = (!isset($_POST['todos']) || empty($_POST['todos'])) ? array() : $_POST['todos'];
        // Deleted ToDo ids
        $todo_ids = isset($_POST['deleted']) ? $_POST['deleted'] : array();
        $excluded_users_keys = array('-1', -1, 'NaN', 'No One');

        // We sanitize our data
        foreach ($todos as $key => $todo) {
            foreach ($todo as $key2 => &$val) {

                // We re-format our assigned array
                $val = ( $val == 'false' ) ? false : $val;
                $val = ( $val == 'true' ) ? true : $val;

                if($key2 == 'assigned' && is_array($todo['assigned'])) {

                    $new_assigned = array();

                    foreach ($todo['assigned'] as $assigned) {

                        /*
                         * Each assigned is either an array if that's an old task:
                         * [6] => Array
                         *   (
                         *       [_id] => ...
                         *       [_avatar] => ....
                         *       [_profile_url] => ...
                         *   )
                         * OR if it's a new task OR an edit
                         * [6] => 7 // and integer sent by the select form
                         */
                        if (is_array($assigned) && !in_array($assigned['_id'], $excluded_users_keys)) {
                            $new_assigned[] = $assigned['_id'];
                        } elseif(!in_array($assigned,$excluded_users_keys)) {
                            $new_assigned[] = $assigned;
                        }

                    }

                    if (isset($todo['_is_new'])) {
                        woffice_projects_new_task_actions($id, $todo);
                    }

                    // We assign the users to the saved to-do
                    $todo['assigned'] = $new_assigned;

                }
    
                if (isset($todo['_is_edited']) || isset($todo['_is_new'])) {
                    $todo['eventable'] = true;
                }
                // We have to save task id since we are creating task based event
                // Task id = post_id '--' random task id
                if ($key2 === '_id' && sizeof(explode('--', $val)) === 1) {
                    $val = $id . '--'. $val;
                }
    
                
                // We remove all the information related to the view, starting by "_"
                if($key2 !== '_id' && substr($key2, 0, 1) == '_')
                    unset($todo[$key2]);

            }

	        if ( $todo['done'] == 'true' && empty( $todo['completion_date'] ) ) {

		        $todo['completion_date'] = time();

	        } else if ( empty($todo['done']) ) {

		        $todo['completion_date'] = 0;

	        }
    
            if ($project_sync === true && isset($todo['eventable'], $todo['date'])) {
                /**
                 * Whenever single todo updated/created
                 *
                 * @param string $type
                 * @param array $todo
                 * @param int $id
                 */
                do_action('woffice_single_todo_update', $type, $todo, $id);
            }
    
            if (isset($todo['eventable'])) {
                unset($todo['eventable']);
            }
            
            $cleaned_todos[$key] = $todo;
        }
        
        if ($project_sync === true && sizeof($todo_ids) > 0) {
            /**
             * Whenever todos deleted
             *
             * @param array $todo_ids
             * @param int $id
             */
            do_action('woffice_todos_deleted', $todo_ids, $id);
        }
        
        // We get our extension instance
        $ext_instance = fw()->extensions->get('woffice-projects');

        // We update the meta
        $projects_assigned_email = woffice_get_settings_option('projects_assigned_email');

        if ($type == 'add' && $projects_assigned_email == "yep") {
            // We send email if needed
            $new_todos_email_checked = $ext_instance->woffice_projects_assigned_email($id, $cleaned_todos);

            // We update the meta finally
            $updated = $ext_instance->woffice_projects_update_postmeta($id, $new_todos_email_checked);
        }
		if ($type == 'check') {
            // We send email if needed
            cst_woffice_projects_task_complete_email($id,$cleaned_todos);
			
      $updated = $ext_instance->woffice_projects_update_postmeta($id, $cleaned_todos);
        } else {
            // Otherwise we just update the meta
            $updated = $ext_instance->woffice_projects_update_postmeta($id, $cleaned_todos);
        }

        // In case of an issue
        if($updated == false) {
            echo json_encode(array('status' => 'fail'));
            die();
        }

        /**
         * Whenever the todos are updated
         *
         * @param string $type
         * @param array $cleaned_todos
         * @param int $id
         */
        do_action('woffice_todo_update', $type, $cleaned_todos, $id);


        // We save the project
        $post = get_post( $id );
        do_action('save_post', $id, $post, true);

        // Update the completion date of the project
	    woffice_project_set_project_completion_date_on_update($id);

        // We return a success to let our user know
        echo json_encode(array(
            'status' => 'success'
        ));

        die();

    }
}

if(!function_exists('woffice_project_format_notifications')) {
    /**
     * Format the notification for BP
     *
     * @param $action
     * @param $item_id
     * @param $secondary_item_id
     * @param $total_items
     * @param string $format
     * @return mixed|void
     */
    function woffice_project_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {

        if ( ! ('woffice_project_comment' === $action || 'woffice_project_assigned_todo' === $action || 'woffice_project_assigned_member' === $action || 'woffice_project_task_completed' === $action) ) {
            return $action;
        }

        // Get the Title
        $post_title = get_the_title( $item_id );

        if ('woffice_project_comment' === $action) {
            $custom_title = sprintf( esc_html__( 'New comment received', 'woffice' ), $post_title );
            $custom_link  = get_permalink( $item_id ) .'#project-content-todo';
            if ( (int) $total_items > 1 ) {
                $custom_text  = sprintf( esc_html__( 'You received %1$s new comments on projects', 'woffice' ), $total_items );
                $custom_link = bp_get_notifications_permalink();
            } else {
                $custom_text  = sprintf( esc_html__( 'Your project "%1$s" received a new comment', 'woffice' ), $post_title );
            }

        }
		
		if ('woffice_project_task_completed' === $action) {
            $custom_title = sprintf( esc_html__( 'Task Completed', 'woffice' ), $post_title );
            $custom_link  = get_permalink( $item_id ) .'#project-content-todo';
            if ( (int) $total_items > 1 ) {
                $custom_text  = sprintf( esc_html__( '%1$s task has been completed', 'woffice' ), $total_items );
                $custom_link = bp_get_notifications_permalink();
            } else {
			   $sender = woffice_get_name_to_display($secondary_item_id);
                $custom_text  = sprintf( esc_html__( '%2$s has completed a task on the project "%1$s"', 'woffice' ), $post_title,  $sender );
            }

        }

        if ('woffice_project_assigned_todo' === $action) {
            $custom_title = sprintf( esc_html__( 'New task received', 'woffice' ), $post_title );
            $custom_link  = get_permalink( $item_id ) .'#project-content-todo';
            if ( (int) $total_items > 1 ) {
                $custom_text  = sprintf( esc_html__( 'You received %1$s new tasks', 'woffice' ), $total_items );
                $custom_link = bp_get_notifications_permalink();
            } else {
                $sender = woffice_get_name_to_display($secondary_item_id);
                $custom_text  = sprintf( esc_html__( '%2$s assigned you a new task on "%1$s"', 'woffice' ), $post_title, $sender );
            }
        }

        if ('woffice_project_assigned_member' === $action) {
            $custom_title = sprintf( esc_html__( 'Added to a project', 'woffice' ), $post_title );
            $custom_link  = get_permalink( $item_id );
            if ( (int) $total_items > 1 ) {
                $custom_text  = sprintf( esc_html__( 'You were added to %1$s new projects', 'woffice' ), $total_items );
                $custom_link = bp_get_notifications_permalink();
            } else {
                $sender = woffice_get_name_to_display($secondary_item_id);
                $custom_text  = sprintf( esc_html__( '%2$s added you to the project "%1$s"', 'woffice' ), $post_title, $sender );
            }
        }

        // WordPress Toolbar
        if ( 'string' === $format ) {
            $message = (!empty($custom_link)) ? '<a href="' . esc_url( $custom_link ) . '" title="' . esc_attr( $custom_title ) . '">' . esc_html( $custom_text ) . '</a>' : $custom_text;
			
            $return = apply_filters( 'woffice_project_format', $message, $custom_text, $custom_link );


        }

        // Deprecated BuddyBar
        else {
            $return = apply_filters( 'woffice_project_format', array(
                'text' => $custom_text,
                'link' => $custom_link
            ), $custom_link, (int) $total_items, $custom_text, $custom_title );
        }

        return $return;

    }
}

if(!function_exists('woffice_clear_project_notifications')) {
    /**
     * Clear project notifications
     */
    function woffice_clear_project_notifications() {

        // One check for speed optimization
        if ( is_singular( 'project' ) ) {

            if (is_user_logged_in() && woffice_bp_is_active('notifications')) {

                global $post;
                $current_user_id = get_current_user_id();

                if ($post->post_author == $current_user_id) {
                    bp_notifications_mark_notifications_by_item_id($current_user_id, $post->ID, 'woffice_project', 'Woffice_project_comment', false, 0);
                }

                bp_notifications_mark_notifications_by_item_id($current_user_id, $post->ID, 'woffice_project', 'woffice_project_assigned_todo', false, 0);

                bp_notifications_mark_notifications_by_item_id($current_user_id, $post->ID, 'woffice_project', 'woffice_project_assigned_member', false, 0);

				bp_notifications_mark_notifications_by_item_id($current_user_id, $post->ID, 'woffice_project', 'woffice_project_task_completed', false, 0);

            }

        }

    }
}

if(!function_exists('wofficeNoticationsGetHandler')) {
    /**
     * AJAX SCRIPT, We fetch the notification for the users
     *
     * @return string (HTML markup)
     */
    function wofficeNoticationsGetHandler()
    {

        $user_id = intval($_POST['user']);

        if (!function_exists('bp_notifications_get_unread_notification_count') || !function_exists('bp_notifications_get_notifications_for_user'))
            return;

        if (bp_notifications_get_unread_notification_count($user_id) > 0) {
            $notifications = bp_notifications_get_notifications_for_user($user_id, "object");
            $notifications = array_reverse($notifications);
            /* Returns :
                [id] => '1'
                [user_id] => '1'
                [item_id] => '10'
                [component_name] => 'activity'
                [component_action] => 'new_at_mention'
                [date_notified] => '2015-11-08 14:50:08'
                [is_new] => '1'
                [content] => 'admin2 mentioned you'
                [href] => '...'
            */
            if (!empty($notifications)) {

                foreach ($notifications as $notification) {
                    // Unread
                    $active = ($notification->is_new == 1) ? 'active' : '';
                    // Icon
                    switch ($notification->component_name) {
                        case "activity":
                            $icon_class = "fa-share";
                            break;
                        case "blogs":
                            $icon_class = "fa-th-large";
                            break;
                        case "forums":
                            $icon_class = "fa-sitemap";
                            break;
                        case "friends":
                            $icon_class = "fa-user";
                            break;
                        case "groups":
                            $icon_class = "fa-users";
                            break;
                        case "messages":
                            $icon_class = "fa-envelope";
                            break;
                        default:
                            $icon_class = "fa-bell";
                    }
                    // Time
                    $time_difference = bp_core_time_since($notification->date_notified);

                    echo '<div class="woffice-notifications-item ' . $active . '">';

                    if (($notification->component_name == 'woffice_wiki' || $notification->component_name == 'woffice_project' || $notification->component_name == 'woffice_blog')
                        && (substr($notification->content, 0, 4) == 'Your')
                        || $notification->component_name == 'woffice_project' && ($notification->component_action == 'woffice_project_assigned_todo' || $notification->component_action == 'woffice_project_assigned_member' || $notification->component_action == 'woffice_project_task_completed') && (substr($notification->content, 0, 4) != 'You ')
                        && $notification->secondary_item_id != 0
                    ) {
                        echo get_avatar($notification->secondary_item_id, 50);
                    } else {
                        // We check for an username in the content :
                        $strings = explode(" ", $notification->content);

                        // We get all the users BUT we limit to 100 queries so it's pretty fast and we save the PHP memory
                        $woffice_wp_users = get_users(array('fields' => array('ID', 'display_name'), 'number' => 100));

                        foreach ($strings as $word) {
                            foreach ($woffice_wp_users as $user) {
                                if ($user->display_name == $word) {
                                    echo get_avatar($user->ID, 50);
                                    break;
                                }
                            }
                        }
                    }


                    // Display notification
                    echo '<a href="' . $notification->href . '" alt="' . $notification->content . '">';
                    echo '<i class="fa component-icon ' . $icon_class . '"></i> ' . $notification->content . ' <span>(' . $time_difference . ')</span>';
                    echo '</a>';

                    echo '<a href="javascript:void(0)" class="mark-notification-read" data-component-action="' . $notification->component_action . '" data-component-name="' . $notification->component_name . '" data-item-id="' . $notification->item_id . '">';
                    echo '<i class="fas fa-times"></i></a>';

                    echo '</div>';

                }

            }
        } else {
            echo '<p class="woffice-notification-empty">' . __("You have", "woffice") . " <b>0</b> " . __("unread notifications.", "woffice") . '</p>';
        }

        exit();

    }
}