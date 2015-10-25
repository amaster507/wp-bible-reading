<?php
/*
Plugin Name: AMD Bible Reading Widget
Plugin URI:  http://amasterdesigns.com/wordpress-daily-bible-reading-plugin/
Description: Easily turn any page into a daily bible reading plan with the shortcode [amd_bible_daily] and add a widget with a snippet of the daily passage
Version:     1.0
Author:      A Master Designs
Author URI:  http://www.amasterdesigns.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

global $amdbible_db_version;
$amdbible_db_version - '1.0';

/**
 * Install plugin and create tables.
 */
function amdbible_install(){
	
	global $wpdb;
	global $amdbible_db_version;
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
	$charset_collate = $wpdb->get_charset_collate();
	
	$table_name = $wpdb->prefix . "amdbible_key_eng";
	$sql = "CREATE TABLE $table_name (
		b int(11) NOT NULL COMMENT 'Book Num',
		n text NOT NULL COMMENT 'Name',
		t varchar(2) NOT NULL COMMENT 'Which Testament this book is in',
		g tinyint(3) unsigned NOT NULL COMMENT 'A genre ID to identify the type of book this is',
		PRIMARY KEY  (b)
	) $charset_collate;";
	dbDelta( $sql );
	
	$table_name = $wpdb->prefix . "amdbible_key_abbr_eng";
	$sql = "CREATE TABLE $table_name (
		id smallint(5) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Abbreviation ID',
		a varchar(255) NOT NULL,
		b smallint(5) unsigned NOT NULL COMMENT 'ID of book that is abbreviated',
		p tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether an abbreviation is the primary one for the book',
		PRIMARY KEY  (id)
	) $charset_collate;";
	dbDelta( $sql );
	
	$table_name = $wpdb->prefix . "amdbible_key_genre_eng";
	$sql = "CREATE TABLE $table_name (
		g tinyint(3) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Genre ID',
		n varchar(255) NOT NULL COMMENT 'Name of genre',
		PRIMARY KEY  (g)
	) $charset_collate;";
	dbDelta( $sql );
	
	$table_name = $wpdb->prefix . "amdbible_kjv";
	$sql = "CREATE TABLE $table_name (
		id int(8) unsigned zerofill NOT NULL,
		b int(11) NOT NULL COMMENT 'Book Num',
		c int(11) NOT NULL COMMENT 'Chapter Num',
		v int(11) NOT NULL COMMENT 'Verse Num',
		t text NOT NULL COMMENT 'Text',
		PRIMARY KEY  (id)
	) $charset_collate;";
	dbDelta( $sql );
	
	$table_name = $wpdb->prefix . "amdbible_plans";
	$sql = "CREATE TABLE $table_name (
		id int(8) unsigned AUTO_INCREMENT NOT NULL,
		p tinyint(1) unsigned NOT NULL COMMENT 'Bible Reading Plan',
		d smallint(2) unsigned NOT NULL COMMENT 'Day of the year 1 to 366',
		sv int(8) unsigned zerofill NOT NULL COMMENT 'Start Verse',
		ev int(8) unsigned zerofill NOT NULL COMMENT 'End Verse',
		PRIMARY KEY  (id)
	) $charset_collate;";
	dbDelta( $sql );
	
	add_option( 'amdbible_db_version', $amdbible_db_version );
	
}

//create tables upon plugin activation
register_activation_hook( __FILE__, 'amdbible_install' );

/**
 * Install dataset into custom tables.
 */
function amdbible_install_data(){
	global $wpdb;
	require_once(plugin_dir_path( __FILE__ )."insert-keys.php");
	require_once(plugin_dir_path( __FILE__ )."insert-kjv.php");
	require_once(plugin_dir_path( __FILE__ )."insert-plans.php");
}

//insert data upon plugin activation
register_activation_hook( __FILE__, 'amdbible_install_data' );

/**
 * Deactivate plugin.
 */
function amdbible_deactivate() {
	global $wpdb;
	$tables = array(
		$wpdb->prefix."amdbible_key_eng",
		$wpdb->prefix."amdbible_key_abbr_eng",
		$wpdb->prefix."amdbible_key_genre_eng",
		$wpdb->prefix."amdbible_cross_reference",
		$wpdb->prefix."amdbible_kjv",
		$wpdb->prefix."amdbible_plans"
	);
	$tables = implode(",",$tables);
	$wpdb->query("DROP TABLE IF EXISTS ".$tables);
}

//delete custom tables upon plugin deactivation
register_deactivation_hook( __FILE__, 'amdbible_deactivate' );

/**
 * Get the actual ending verse useful when end of chapter denoted using 999.
 *
 * @param int $ev Ending Verse.
 *
 * @return int Ending Verse Number.
 */
function amdbible_ev($ev){
	global $wpdb;
	return $wpdb->get_var("SELECT id FROM {$wpdb->prefix}amdbible_kjv WHERE id BETWEEN 0 AND $ev ORDER BY id DESC LIMIT 1");
}

/**
 * Get the name of the Book of the Bible.
 *
 * @param int $b Book Number.
 *
 * @return string Name of the Book of the Bible.
 */
function amdbible_bk_name($b){
	global $wpdb;
	return $wpdb->get_var("SELECT n FROM {$wpdb->prefix}amdbible_key_eng WHERE b ='$b'");
}

/**
 * Get the passage from the database.
 *
 * @param int $sv Starting Verse.
 * @param int $ev Ending Verse.
 *
 * @return array Array of objects containing verse info formatted as (id), (b)ook number, (c)hapter number, (v)erse number, and (t)ext.
 */
function amdbible_passage($sv,$ev){
	global $wpdb;
	$query = "
		SELECT *
		FROM {$wpdb->prefix}amdbible_kjv
		WHERE id BETWEEN $sv AND $ev
	";
	return $wpdb->get_results($query);
}

/**
 * Get the reference using starting verse and optional ending verse.
 *
 * @param int $sv Starting Verse.
 * @param int|null $ev Ending Verse.
 *
 * @return string Bible Reference.
 */
function amdbible_reference($sv,$ev = null){
	$sb = amdbible_bk_name(substr($sv,0,2));
	$sc = intval(substr($sv,2,3));
	$sv = intval(substr($sv,5,3));
	if(!is_null($ev) && !empty($ev) && $sv!=$ev){
		$ev = amdbible_ev($ev);
		$eb = amdbible_bk_name(substr($ev,0,2));
		$ec = intval(substr($ev,2,3));
		$ev = intval(substr($ev,5,3));
		if($sb!=$eb){
			return $sb." ".$sc.":".$sv." - ".$eb." ".$ec.":".$ev;
		} else {
			if($sc!=$ec){
				return $sb." ".$sc.":".$sv." - ".$ec.":".$ev;
			} else {
				return $sb." ".$sc.":".$sv."-".$ev;
			}
		}
	} else {
		return $sb." ".$sc.":".$sv;
	}
}

/**
 * Format the Bible Passage.
 *
 * @param array $passages Array of objects containing verse info formatted as (id), (b)ook number, (c)hapter number, (v)erse number, and (t)ext.
 * @param boolean|int $limit False or number of items to limit.
 * @param null|string $limit_type Values 'words' or 'verses' to set limit type.
 * @param boolean $show_book Show book of the Bible.
 * @param boolean $show_chapt Show chapter of the book.
 * @param boolean $show_verse_num Show verse number of the chapter.
 *
 * @return string HTML formatted Bible text with book, chapter, and/or verse numbers if set to true.
 */
function amdbible_format_passage($passages,$limit = false,$limit_type = null,$show_book = true,$show_chapt = true,$show_verse_num = true){
	if($limit===true){
		$limit = false;
	}
	if($limit){
		$content = "<div class='amdbible_snippet'>";
	} else {
		$content = "<div class='amdbible_passage'>";
	}
	$b = $c = $v = null;
	$count = 0;
	$snippet = false;
	foreach($passages as $verse){
		if($limit && $count >= $limit){
			break;
			$snippet = true;
		}
		if($verse->c != $c){
			if($verse->b != $b){
				if($b !== null){
					$content .= "</p>";
				}
				if($show_book){
					$content .= "<p class='amdbible_book'>".amdbible_bk_name($verse->b)."</p>";
				}
			}
			if($show_chapt){
				$content .= "<p><span class='amdbible_chapter'>".$verse->c."</span>";
			} else {
				$content .= "<p>";
			}
		}
		if($show_verse_num){
			$content .= "<span class='amdbible_verse'>".$verse->v."</span> ";
		}
		if($limit && $limit_type == 'words'){
			$words_in_verse = str_word_count($verse->t);
			$count = $count+$words_in_verse;
			if($count > $limit){
				$trim  = $words_in_verse - ($count - $limit);
				$words = str_word_count($verse->t, 2);
				$pos = array_keys($words);
				$verse->t = substr($verse->t, 0, $pos[$trim]);
			}
		}
		$content .= "<span class='amdbible_text'>".$verse->t."</span> ";
		$b = $verse->b;
		$c = $verse->c;
		$v = $verse->v;
		if($limit && $limit_type == 'verses'){
			$count++;
		}
	}
	if($snippet){
		$content .= ". . .";
	}
	$content .= "</p></div>";
	return $content;
}

/**
 * Link the css file.
 */
function amdbible_css(){
	wp_register_style('amdbible-passage',plugins_url('/amdbible-passage.css', __FILE__ ),array(),'20151001','all');
	wp_enqueue_style('amdbible-passage');
}
// add action to link css file
add_action('wp_enqueue_scripts','amdbible_css');

/**
 * Display the HTML form for selecting the daily passage.
 *
 * @param object $date Datetime object.
 *
 * @return string HTML form for daily selection.
 */
function amdbible_date_form($date){
	$prev = date("z",$date);
	$next = date("z",$date)+2;
	ob_start();
?>
<div style="width:15%; float:left;">
	<form action="" method="get">
		<button type="submit" name="d" value="<?php echo $prev; ?>">&lt;</button>
	</form>
</div>
<div style="width:70%; float:left; text-align:center">
	<form action="" method="get">
		<p><input name="date" type="date" value="<?php echo date("Y-m-d",$date); ?>" onChange="this.form.submit();" /></p>
	</form>
</div>
<div style="width:15%; float:left;">
	<form action="" method="get">
		<button type="submit" name="d" value="<?php echo $next; ?>" style="float:right">&gt;</button>
	</form>
</div>
<div style="width=0%; height:0%; clear:left;"></div>
<?php

	$content = ob_get_contents();
	ob_end_clean();
	return $content;
}

/**
 * Display the HTML form for selecting the daily passage.
 *
 * @param int $day Day of the year 1-366.
 *
 * @return array passage array containing info for each passage in plan an complete array containing all of the passages together in one array.
 */
function amdbible_daily_passage($day){
	global $wpdb;
	$return  = array("complete"=>array(),"passages"=>array());
	$query = "
	    SELECT *
	    FROM {$wpdb->prefix}amdbible_plans
	    WHERE p='1' AND d={$day}
	";
	$plan_info = $wpdb->get_results($query);
	$passages = array();
	foreach($plan_info as $p_data){
		$sv = $p_data->sv;
		$ev = $p_data->ev;
		$passage = amdbible_passage($sv,$ev);
		$return["passages"][] = array(
			"sv"=>$sv,
			"ev"=>$ev,
			"passage"=>$passage
		);
		$return["complete"] = array_merge($return["complete"],$passage);
	}
	return $return;
}
/**
 * Display the Bible Reading Plan in place of shortcode
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string content to be displayed in place of shortcode
 */
function amdbible_shortcode_daily( $atts ) {
	global $wpdb;
	$txt = "";
	//accept a parameter of d for day of the year value between 1 and 366
	if(isset($_GET["d"]) && $_GET["d"] && $_GET["d"]>=1 && $_GET["d"]<=366){
		$day = $_GET["d"];
		$diff = $day-date('z')-1;
		if($diff>=0){$diff = "+".$diff;}
		$date = strtotime("$diff days");
	//accept the a parameter of date where format is yyyy-mm-dd
	} else if(isset($_GET["date"]) && $_GET["date"] && $_GET["date"]==date("Y-m-d",strtotime($_GET["date"]))){
		$date = strtotime($_GET["date"]);
	//esle set the date to current day
	} else {
		$date = time();
	}
	//offset day of year by 1 to conform with 1-366 instead of 0-365
	$day = date('z',$date)+1;
	
	
	$data = amdbible_daily_passage($day);
	$passages = $data["complete"];
	$references = "";
	foreach($data["passages"] as $passage){
		$references .= amdbible_reference($passage["sv"],$passage["ev"])." ";
	}
	$txt .= amdbible_date_form($date);
	$txt .= "<p class='amdbible_title'>".date("D., M. j, Y",$date)." &mdash; ".$references."</p>";
	$txt .= amdbible_format_passage($passages);
	$txt .= amdbible_date_form($date);

return $txt;
}
add_shortcode( 'amd_bible_daily', 'amdbible_shortcode_daily' );

add_action( 'widgets_init', function(){
     register_widget( 'amdbible_widget' );
});	
/**
 * Adds My_Widget widget.
 */
class amdbible_widget extends WP_Widget {
	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'amdbible_widget', // Base ID
			__('Daily Bible Snippet', 'text_domain'), // Name
			array( 'description' => __( 'Widget to Display Snippet of Daily Bible Reading', 'text_domain' ), ) // Args
		);
	}
	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		//get the current daily passage
		$day = date('z',time())+1;
		$passages = amdbible_daily_passage($day);
		$references = "";
		foreach($passages["passages"] as $passage){
			$references .= amdbible_reference($passage["sv"],$passage["ev"])." ";
		}
		if(isset($instance['ref_title'])){
			$ref_title = $instance['ref_title'];
		} else {
			$ref_title = '0';
		}
		if($ref_title=='1'){
			$instance['title'] = $references;
		}
		//start outputting content
     	echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
		}
		//check and get variables
		if(isset($instance['limit'])){
			$limit = $instance['limit'];
		} else {
			$limit = 50;
		}
		if(isset($instance['limit_type'])){
			$limit_type = $instance['limit_type'];
		} else {
			$limit_type = 'words';
		}
		if(isset($instance['read_more_text'])){
			$read_more_text = $instance['read_more_text'];
		} else {
			$read_more_text = __( 'Read More', 'text_domain' );
		}
		echo amdbible_format_passage($passages["complete"],$limit,$limit_type,false,false,false);
		if(isset($instance['full_page'])){
			echo '<a href="'.$instance['full_page'].'" >',$read_more_text,'</a>';
		}
		echo $args['after_widget'];
	}
	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		// Check values
		if(isset($instance['ref_title'])){
			$ref_title = esc_attr($instance['ref_title']);
		} else {
			$ref_title = '0';
		}
		if(isset($instance['title'])){
			$title = esc_attr($instance['title']);
		} else {
			$title = __( 'New title', 'text_domain' );
		}
		if(isset($instance['limit_type'])){
			$limit_type = esc_attr($instance['limit_type']);
		} else {
			$limit_type = 'words';
		}
		if(isset($instance['limit'])){
			$limit = esc_attr($instance['limit']);
		} else {
			$limit = __( '50', 'text_domain' );
		}
		if(isset($instance['full_page'])){
			$full_page = esc_attr($instance['full_page']);
		} else {
			$full_page = '';
		}
		if(isset($instance['read_more_text'])){
			$read_more_text = esc_attr($instance['read_more_text']);
		} else {
			$read_more_text = __( 'Continue Reading', 'text_domain' );
		}
		
		?>
		<p>
			<input id="<?php echo $this->get_field_id('ref_title'); ?>" name="<?php echo $this->get_field_name('ref_title'); ?>" type="checkbox" value="1" <?php checked( '1', $ref_title ); ?> />
			<label for="<?php echo $this->get_field_id('ref_title'); ?>"><?php _e('Use Reference for Title?', 'wp_widget_plugin'); ?></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wp_widget_plugin'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('limit_type'); ?>"><?php _e('Limit Type:', 'wp_widget_plugin'); ?></label>
			<select name="<?php echo $this->get_field_name('limit_type'); ?>" id="<?php echo $this->get_field_id('limit_type'); ?>" class="widefat">
				<?php
					$options = array('words', 'verses');
					foreach ($options as $option) {
						echo '<option value="' . $option . '" id="' . $option . '"', $limit_type == $option ? ' selected="selected"' : '', '>', $option, '</option>';
					}
				?>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Limit:', 'wp_widget_plugin'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="number" value="<?php echo $limit; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('full_page'); ?>"><?php _e('Full Reading Page:', 'wp_widget_plugin'); ?></label>
			<select name="<?php echo $this->get_field_name('full_page'); ?>" id="<?php echo $this->get_field_id('full_page'); ?>" class="widefat">
				<option value="" ></option>
				<?php
					$pages = get_pages();
					foreach ($pages as $page) {
						echo '<option value="' . get_page_link($page->ID) . '" id="' . $page->ID . '"', $full_page == get_page_link($page->ID) ? ' selected="selected"' : '', '>', $page->post_title, '</option>';

					}
				?>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('read_more_text'); ?>"><?php _e('Read More Text:', 'wp_widget_plugin'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('read_more_text'); ?>" name="<?php echo $this->get_field_name('read_more_text'); ?>" type="text" value="<?php echo $read_more_text; ?>" />
		</p>
		<?php 
	}
	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['ref_title'] = ( ! empty( $new_instance['ref_title'] ) ) ? strip_tags( $new_instance['ref_title'] ) : '0';
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['limit_type'] = ( ! empty( $new_instance['limit_type'] ) ) ? strip_tags( $new_instance['limit_type'] ) : 'words';
		$instance['limit'] = ( ! empty( $new_instance['limit'] ) ) ? strip_tags( $new_instance['limit'] ) : '50';
		$instance['full_page'] = ( ! empty( $new_instance['full_page'] ) ) ? strip_tags( $new_instance['full_page'] ) : '';
		$instance['read_more_text'] = ( ! empty( $new_instance['read_more_text'] ) ) ? strip_tags( $new_instance['read_more_text'] ) : __( 'Continue Reading', 'text_domain' );
		return $instance;
	}
} // class My_Widget


?>