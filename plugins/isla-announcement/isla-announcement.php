<?php
/*
Plugin Name: Isla Fae Announcements
Description: Display Announcements using html5 <marquee>
Version: 1.0
Author: Herbert Scrackle
License: GPL2
 */

//https://code.tutsplus.com/tutorials/building-a-simple-announcements-plugin-for-wordpress--wp-27661

define('ISLA_ANNOUNCEMENTS_PATH', plugin_dir_url( __FILE__ ));

function isla_frontend_scripts($hook) {

    wp_enqueue_style( 'announcements-style', ISLA_ANNOUNCEMENTS_PATH . 'css/announcements.css');

}
add_action( 'wp_enqueue_scripts', 'isla_frontend_scripts' );

function isla_backend_scripts($hook) {
    global $post;

    if( ( !isset($post) || $post->post_type != 'isla-announcements' ))
        return;
    wp_enqueue_style( 'jquery-ui-fresh', ISLA_ANNOUNCEMENTS_PATH . 'css/jquery-ui-fresh.css');
    wp_enqueue_script( 'announcements', ISLA_ANNOUNCEMENTS_PATH . 'js/announcements.js', array( 'jquery', 'jquery-ui-datepicker' ) );
}
add_action( 'admin_enqueue_scripts', 'isla_backend_scripts' );


function isla_register_announcements() {

    $labels = array(
        'name' => _x( 'Announcements', 'post type general name' ),
        'singular_name' => _x( 'Announcement', 'post type singular name' ),
        'add_new' => _x( 'Add New', 'Announcement' ),
        'add_new_item' => __( 'Add New Announcement' ),
        'edit_item' => __( 'Edit Announcement' ),
        'new_item' => __( 'New Announcement' ),
        'view_item' => __( 'View Announcement' ),
        'search_items' => __( 'Search Announcements' ),
        'not_found' =>  __( 'No Announcements found' ),
        'not_found_in_trash' => __( 'No Announcements found in Trash' ),
        'parent_item_colon' => ''
    );

    $args = array(
        'labels' => $labels,
        'singular_label' => __('Announcement', 'simple-announcements'),
        'public' => true,
        'capability_type' => 'post',
        'rewrite' => false,
        'supports' => array('title', 'editor'),
    );
    register_post_type('isla-announcements', $args);
}
add_action('init', 'isla_register_announcements');


function isla_add_metabox() {
    add_meta_box( 'isla_metabox_id', 'Scheduling', 'isla_metabox', 'isla-announcements', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'isla_add_metabox' );

function isla_metabox( $post ) {
    $values = get_post_custom( $post->ID );
    $start_date = isset( $values['isla_start_date'] ) ? esc_attr( $values['isla_start_date'][0] ) : '';
    $end_date = isset( $values['isla_end_date'] ) ? esc_attr( $values['isla_end_date'][0] ) : '';
    wp_nonce_field( 'isla_metabox_nonce', 'metabox_nonce' );
?>
<p>
    <label for="start_date">Start date</label>
    <input type="text" name="isla_start_date" id="isla_start_date" value="<?php echo $start_date; ?>" />
</p>
<p>
    <label for="end_date">End date</label>
    <input type="text" name="isla_end_date" id="isla_end_date" value="<?php echo $end_date; ?>" />
</p>
<?php
}

function isla_metabox_save( $post_id ) {
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return $post_id;

    if( !isset( $_POST['metabox_nonce'] ) || !wp_verify_nonce( $_POST['metabox_nonce'], 'isla_metabox_nonce' ) )
        return $post_id;

    if( !current_user_can( 'edit_post' ) )
        return $post_id;

    // Make sure data is set
    if( isset( $_POST['isla_start_date'] ) ) {

        $valid = 0;
        $old_value = get_post_meta($post_id, 'isla_start_date', true);

        if ( $_POST['isla_start_date'] != '' ) {

            $date = $_POST['isla_start_date'];
            $date = explode( '-', (string) $date );
            $valid = checkdate($date[1],$date[2],$date[0]);
        }

        if ($valid)
            update_post_meta( $post_id, 'isla_start_date', $_POST['isla_start_date'] );
        elseif (!$valid && $old_value)
            update_post_meta( $post_id, 'isla_start_date', $old_value );
        else
            update_post_meta( $post_id, 'isla_start_date', '');
    }

    if ( isset( $_POST['isla_end_date'] ) ) {

        if( $_POST['isla_start_date'] != '' ) {

            $old_value = get_post_meta($post_id, 'isla_end_date', true);

            $date = $_POST['isla_end_date'];
            $date = explode( '-', (string) $date );
            $valid = checkdate($date[1],$date[2],$date[0]);
        }
        if($valid)
            update_post_meta( $post_id, 'isla_end_date', $_POST['isla_end_date'] );
        elseif (!$valid && $old_value)
            update_post_meta( $post_id, 'isla_end_date', $old_value );
        else
            update_post_meta( $post_id, 'isla_end_date', '');
    }
}
add_action( 'save_post', 'isla_metabox_save' );


function isla_filter_where( $where = '' ) {
    // ...where dates are blank
    $where .= " OR (mt1.meta_key = 'isla_start_date' AND CAST(mt1.meta_value AS CHAR) = '') OR (mt2.meta_key = 'isla_end_date' AND CAST(mt2.meta_value AS CHAR) = '')";
    return $where;
}

function isla_display_announcement() {

    global $wpdb;

    $today = date('Y-m-d');
    $args = array(
        'post_type' => 'isla-announcements',
        'posts_per_page' => 0,
        'meta_key' => 'isla_end_date',
        'orderby' => 'meta_value_num',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => 'isla_start_date',
                'value' => $today,
                'compare' => '<=',
            ),
            array(
                'key' => 'isla_end_date',
                'value' => $today,
                'compare' => '>=',
            )
        )
    );

    // Add a filter to do complex 'where' clauses...
    add_filter( 'posts_where', 'isla_filter_where' );

    $query = new WP_Query( $args );

    // Take the filter away again so this doesn't apply to all queries.
    remove_filter( 'posts_where', 'isla_filter_where' );


    $announcements = $query->posts;

    if($announcements) :
?>

<marquee behavior="scroll" scrollamount="10" direction="right">
    <?php
        foreach ($announcements as $announcement) {
    ?>
    <?php echo do_shortcode(wpautop(($announcement->post_content))); ?>
    <?php
        }
    ?>
</marquee>

<?php
    endif;
}
add_action('wp_footer', 'isla_display_announcement');


?>