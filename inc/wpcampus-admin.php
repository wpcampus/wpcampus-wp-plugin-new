<?php

/**
 * Holds all of our admin functionality.
 */
class WPCampus_Admin {

	/**
	 * Holds the class instance.
	 *
	 * @access	private
	 * @var		WPCampus_Admin
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @access  public
	 * @return	WPCampus_Admin
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			$className = __CLASS__;
			self::$instance = new $className;
		}
		return self::$instance;
	}

	/**
	 * Warming up the engine.
	 */
	protected function __construct() {

		// Add our meta boxes
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 1, 2 );

		// Adds custom user contact methods
		add_filter( 'user_contactmethods', array( $this, 'add_user_contact_methods' ), 1, 2 );

		// Prints our user meta
		add_action( 'show_user_profile', array( $this, 'print_user_meta' ), 1 );
		add_action( 'edit_user_profile', array( $this, 'print_user_meta' ), 1 );

		// Saves our user meta
		add_action( 'personal_options_update', array( $this, 'save_user_meta' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_meta' ) );

		// Manually convert interest form entries to CPT
		add_action( 'admin_init', array( $this, 'get_involved_manual_convert_to_post' ) );

	}

	/**
	 * Method to keep our instance from being cloned.
	 *
	 * @access	private
	 * @return	void
	 */
	private function __clone() {}

	/**
	 * Method to keep our instance from being unserialized.
	 *
	 * @access	private
	 * @return	void
	 */
	private function __wakeup() {}

	/**
	 * Adds our admin meta boxes.
	 */
	public function add_meta_boxes( $post_type, $post ) {

		// Add a meta box to link to the podcast guide
		add_meta_box(
			'wpcampus-podcast-guide',
			sprintf( __( '%s Podcast Guide', 'wpcampus' ), 'WPCampus' ),
			array( $this, 'print_meta_boxes' ),
			'podcast',
			'side',
			'high'
		);

	}

	/**
	 * Print our meta boxes.
	 */
	public function print_meta_boxes( $post, $metabox ) {
		switch( $metabox[ 'id' ] ) {

			case 'wpcampus-podcast-guide':
				?><div style="background:rgba(0,115,170,0.07);padding:18px;color:#000;margin:-6px -12px -12px -12px;">Be sure to read our <a href="https://docs.google.com/document/d/1GG8-qb4OQ3TzDyB1UI00GvRw-agyIO1AT8WUPuyDgHg/edit#heading=h.8dr748uym2qn" target="_blank">WPCampus Podcast Guide</a> to help walk you through the process and ensure proper setup.</div><?php
				break;

		}
	}

	/**
	 * Adds custom user contact methods.
	 *
	 * @param   array - $methods - Array of contact methods and their labels
	 * @param   WP_User - $user - WP_User object
	 * @return  array - filtered methods
	 */
	public function add_user_contact_methods( $methods, $user ) {

		// Add Slack username
		$methods['slack_username'] = sprintf( __( '%1$s %2$s Username', 'wpcampus' ), 'WPCampus', 'Slack' );

		return $methods;
	}

	/**
	 * Prints our user meta.
	 *
	 * @param   WP_User - $profile_user - The current WP_User object
	 */
	public function print_user_meta( $profile_user ) {

		// Get "add subjects" values
		$wpc_add_subjects = get_user_meta( $profile_user->ID, 'wpc_add_subjects', true );

		// Add a nonce field for verification
		wp_nonce_field( 'wpcampus_save_user_meta', 'wpcampus_save_user_meta' );

		?>
		<div style="background:#e3e4e5;padding:20px;">
			<h2><?php printf( __( 'For %s', 'wpcampus' ), 'WPCampus' ); ?></h2>
			<p style="font-size:1rem;color:#800;margin-bottom:0;"><strong>Be sure to provide your Slack username in the "Contact Info" section.</strong></p>
			<table class="form-table">
				<tbody>
					<?php

					// We need subjects info
					$subjects = get_taxonomy( 'subjects' );

					// Make sure the current user can edit the user and assign terms before proceeding
					if ( ! current_user_can( 'edit_user', $profile_user->ID ) || ! current_user_can( $subjects->cap->assign_terms ) ) {
						return;
					}

					// Get the subjects terms
					$subjects = get_terms( array(
						'taxonomy'      => 'subjects',
						'hide_empty'    => false,
						'orderby'       => 'name',
						'order'         => 'ASC',
						'fields'        => 'all',
					) );
					if ( ! empty( $subjects ) ) {

						// Get the subjects assigned to this user
						$user_subjects = wp_get_object_terms( $profile_user->ID, 'subjects', array( 'fields' => 'ids' ) );
						if ( is_wp_error( $user_subjects ) || empty( $user_subjects ) || ! is_array( $user_subjects ) ) {
							$user_subjects = array();
						}

						?>
						<tr>
							<th><label for="wpc-subjects"><?php _e( 'I am a subject matter expert on the following topics:', 'wpcampus' ); ?></label></th>
							<td>
								<?php foreach ( $subjects as $subject ) { ?>
									<input type="checkbox" name="wpc_subjects[]" id="wpc-subject-<?php echo esc_attr( $subject->term_id ); ?>" value="<?php echo esc_attr( $subject->term_id ); ?>" <?php checked( in_array( $subject->term_id, $user_subjects ) ); ?> /> <label for="wpc-subject-<?php echo esc_attr( $subject->term_id ); ?>"><?php echo $subject->name; ?></label><br />
								<?php } ?>
							</td>
						</tr>
						<?php
					}

					?>
					<tr>
						<th><label for="wpc_add_subjects"><?php _e( 'Add Subject(s)', 'wpcampus' ); ?></label></th>
						<td>
							<input type="text" name="wpc_add_subjects" id="wpc_add_subjects" value="<?php echo ! empty( $wpc_add_subjects ) ? esc_attr( $wpc_add_subjects ) : ''; ?>" class="regular-text" /><br />
							<span class="description">If you would like to add to the subjects list, please provide your subjects in a comma separated list. Once we approve the subjects, you will be able to assign them to yourself.</span>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php

	}

	/**
	 * Saves our user meta.
	 *
	 * @param   int - $user_id - The ID of the user to save the terms for
	 */
	public function save_user_meta( $user_id ) {

		// First, verify our nonce
		if ( ! isset( $_POST['wpcampus_save_user_meta'] )
			|| ! wp_verify_nonce( $_POST['wpcampus_save_user_meta'], 'wpcampus_save_user_meta' ) ) {
			return;
		}

		// Update the "add subjects"
		if ( isset( $_POST['wpc_add_subjects'] ) ) {
			update_user_meta( $user_id, 'wpc_add_subjects', $_POST['wpc_add_subjects'] );
		}

		// In order to update user subjects, we need taxonomy info
		$subjects = get_taxonomy( 'subjects' );

		// Make sure the current user can edit the user and assign terms before proceeding
		if ( ! current_user_can( 'edit_user', $user_id ) || ! current_user_can( $subjects->cap->assign_terms ) ) {
			return;
		}

		// Get the saved subjects
		$saved_subjects = isset( $_POST['wpc_subjects'] ) ? $_POST['wpc_subjects'] : '';

		// If not empty...
		if ( ! empty( $saved_subjects ) ) {

			// Make sure its an array
			if ( ! is_array( $saved_subjects ) ) {
				$saved_subjects = explode( ',', $saved_subjects );
			}

			// Make sure its all integers
			$saved_subjects = array_map( 'intval', $saved_subjects );

		}

		// Set the terms for the user
		wp_set_object_terms( $user_id, $saved_subjects, 'subjects', false );

		// Clean the term cache
		clean_object_term_cache( $user_id, 'subjects' );

	}

	/**
	 * Manually convert interest form entries to CPT.
	 *
	 * @TODO create an admin button for this?
	 */
	public function get_involved_manual_convert_to_post() {

		// NOT USING NOW
		return;

		// ID for interest form
		$form_id = 1;

		// What entry should we start on?
		$entry_offset = 0;

		// How many entries?
		$entry_count = 50;

		// Get interest entries
		$entries = GFAPI::get_entries( $form_id, array( 'status' => 'active' ), array(), array( 'offset' => $entry_offset, 'page_size' => $entry_count ) );
		if ( ! empty( $entries ) ) {

			// Get form data
			$form = GFAPI::get_form( $form_id );

			// Process each entry
			foreach( $entries as $entry ) {

				// Convert this entry to a post
				wpcampus_forms()->convert_entry_to_post( $entry, $form );

			}

		}

	}

}

/**
 * Returns the instance of our main WPCampus_Admin class.
 *
 * Will come in handy when we need to access the
 * class to retrieve data throughout the plugin.
 *
 * @access	public
 * @return	WPCampus_Admin
 */
function wpcampus_admin() {
	return WPCampus_Admin::instance();
}

// Let's get this show on the road
wpcampus_admin();