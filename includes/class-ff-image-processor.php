<?php
/**
 * FF_Image_Processor Class
 * 
 * Handles the processing of images from Fluent Forms submissions.
 * 
 * @package FluentFormsEntriesImageMigrator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define our background processor class.
 */
class FF_Image_Processor {
	/**
	 * Total items to process.
	 *
	 * @var int
	 */
	private $total_items = 0;
	
	/**
	 * Items that have been processed.
	 *
	 * @var int
	 */
	private $processed_items = 0;
	
	/**
	 * Batch size for processing.
	 *
	 * @var int
	 */
	private $batch_size = 50;
	
	/**
	 * Target form ID to process.
	 *
	 * @var int
	 */
	private $form_id = 0;
	
	/**
	 * Flag to track if processing is active.
	 *
	 * @var bool
	 */
	private $is_processing = false;
	
	/**
	 * Constructor to set up default values.
	 */
	public function __construct() {
		// Get the form ID from the URL if available
		$this->set_current_form_id();
	}
	
	/**
	 * Set the current form ID from URL parameters if available.
	 *
	 * @return void
	 */
	private function set_current_form_id() {
		// Check if form_id is in the URL parameters
		if ( isset( $_GET['form_id'] ) && is_numeric( $_GET['form_id'] ) ) {
			$this->form_id = intval( $_GET['form_id'] );
		}
	}
	
	/**
	 * Initialize the class by adding hooks and actions.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_ajax_ff_process_images_start', array( $this, 'start_processing' ) );
		add_action( 'wp_ajax_ff_process_images_status', array( $this, 'get_status' ) );
		add_action( 'wp_ajax_ff_process_images_batch', array( $this, 'process_batch' ) );
		add_action( 'wp_ajax_ff_save_batch_size', array( $this, 'save_batch_size' ) );
		
		// Add our button to the Fluent Forms interface.
		add_action( 'fluentform/after_form_navigation', array( $this, 'add_process_button' ) );
		
		// Hook for new submissions.
		add_action( 'fluentform_before_insert_submission', array( $this, 'process_new_submission' ), 10, 3 );
		
		// Hook for CSV import completion.
		add_action( 'fluentform_after_import_submission', array( $this, 'after_import_submission' ), 10, 2 );

		// Enqueue admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}
	
	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on Fluent Forms pages
		if ( strpos( $hook, 'fluent-form' ) === false ) {
			return;
		}

		// Register and enqueue styles
		wp_register_style(
			'ffeim-admin-styles',
			FF_IMAGE_PROCESSOR_PLUGIN_URL . 'assets/css/ffeim-admin.css',
			array(),
			FF_IMAGE_PROCESSOR_VERSION
		);
		wp_enqueue_style( 'ffeim-admin-styles' );

		// Register and enqueue scripts
		wp_register_script(
			'ffeim-admin-scripts',
			FF_IMAGE_PROCESSOR_PLUGIN_URL . 'assets/js/ffeim-admin.js',
			array( 'jquery' ),
			FF_IMAGE_PROCESSOR_VERSION,
			true
		);

		// Get saved batch size or use default
		$saved_batch_size = get_option( 'ff_image_processor_batch_size', $this->batch_size );

		// Localize script for JS use
		wp_localize_script(
			'ffeim-admin-scripts',
			'ffeim_vars',
			array(
				'nonce'                 => wp_create_nonce( 'ff_process_images' ),
				'form_id'               => $this->form_id,
				'starting_text'         => __( 'Starting...', 'ff-entries-image-migrator' ),
				'process_button_text'   => __( 'Process Images to Media Library', 'ff-entries-image-migrator' ),
				'processing_started_text' => __( 'Processing started...', 'ff-entries-image-migrator' ),
				'processing_text'       => __( 'Processing images...', 'ff-entries-image-migrator' ),
				'complete_text'         => __( 'complete', 'ff-entries-image-migrator' ),
				'error_text'            => __( 'Error:', 'ff-entries-image-migrator' ),
				'connection_error_text' => __( 'Error connecting to server. Please try again.', 'ff-entries-image-migrator' ),
				'complete_message'      => __( 'Processing complete! All images have been added to the media library.', 'ff-entries-image-migrator' ),
			)
		);
		
		wp_enqueue_script( 'ffeim-admin-scripts' );
	}
	
	/**
	 * Add the processing button to the UI
	 *
	 * @return void
	 */
	public function add_process_button() {
		$current_form_id = isset( $_GET['form_id'] ) ? intval( $_GET['form_id'] ) : 0;
		
		// Only show button if we're on a valid form page and have a form ID
		if ( empty( $current_form_id ) || $current_form_id !== $this->form_id ) {
			return;
		}
		
		// Get the total count of unprocessed image entries.
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_submissions';
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE form_id = %d",
				$this->form_id
			)
		);
		
		// Check if we have any imported CSV data count.
		$csv_count    = get_option( 'ff_image_processor_csv_count', 0 );
		$total_count  = $count;
		
		if ( $csv_count > $count ) {
			$total_count = $csv_count;
		}
		
		// Get saved batch size or use default.
		$saved_batch_size = get_option( 'ff_image_processor_batch_size', $this->batch_size );
		
		?>
		<div class="ffeim-wrapper">
			<div class="ffeim-batch-size-container">
				<label for="ffeim-batch-size" class="ffeim-batch-size-label"><?php esc_html_e( 'Batch Size:', 'ff-entries-image-migrator' ); ?></label>
				<input type="number" id="ffeim-batch-size" class="ffeim-batch-size-input" min="10" max="200" value="<?php echo esc_attr( $saved_batch_size ); ?>">
				<span class="ffeim-batch-size-hint">
					<?php esc_html_e( '(10-200 entries per batch, higher values process faster but may timeout)', 'ff-entries-image-migrator' ); ?>
				</span>
			</div>

			<button id="ffeim-start-process" class="ffeim-process-button button button-primary">
				<?php esc_html_e( 'Process Images to Media Library', 'ff-entries-image-migrator' ); ?>
			</button>
			
			<div id="ffeim-status-container" class="ffeim-status-container">
				<div class="ffeim-progress-bar-wrapper">
					<div class="ffeim-progress-bar">
						<div class="ffeim-progress-bar-inner"></div>
					</div>
				</div>
				<div class="ffeim-status-text">
					<p><?php esc_html_e( 'Preparing to process images...', 'ff-entries-image-migrator' ); ?></p>
				</div>
				<div class="ffeim-status-counts">
					<span class="ffeim-processed-count"><?php esc_html_e( 'Processed:', 'ff-entries-image-migrator' ); ?> <span class="ffeim-count">0</span></span>
					<span class="ffeim-total-count"><?php esc_html_e( 'Total:', 'ff-entries-image-migrator' ); ?> <span class="ffeim-count"><?php echo esc_html( $total_count ); ?></span></span>
				</div>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Save user's batch size preference
	 *
	 * @return void
	 */
	public function save_batch_size() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ff_process_images' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'ff-entries-image-migrator' ) ) );
			return;
		}
		
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : $this->batch_size;
		
		// Validate batch size.
		if ( $batch_size < 10 ) {
			$batch_size = 10;
		} elseif ( $batch_size > 200 ) {
			$batch_size = 200;
		}
		
		update_option( 'ff_image_processor_batch_size', $batch_size );
		wp_send_json_success();
	}
	
	/**
	 * Start the image processing
	 *
	 * @return void
	 */
	public function start_processing() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ff_process_images' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'ff-entries-image-migrator' ) ) );
			return;
		}
		
		// Prevent multiple simultaneous processes.
		if ( $this->is_processing ) {
			wp_send_json_error( array( 'message' => __( 'A process is already running.', 'ff-entries-image-migrator' ) ) );
			return;
		}
		
		$form_id    = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : $this->form_id;
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : $this->batch_size;
		
		// Set up the process.
		$this->form_id      = $form_id;
		$this->batch_size   = $batch_size;
		$this->is_processing = true;
		
		// Get total count from the database.
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_submissions';
		$this->total_items = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE form_id = %d",
				$this->form_id
			)
		);
		
		// Check if we have any imported CSV data count.
		$csv_count = get_option( 'ff_image_processor_csv_count', 0 );
		
		if ( $csv_count > $this->total_items ) {
			$this->total_items = $csv_count;
		}
		
		$this->processed_items = 0;
		
		// Store process info.
		update_option( 'ff_image_processor_is_running', true );
		update_option( 'ff_image_processor_total', $this->total_items );
		update_option( 'ff_image_processor_processed', 0 );
		
		wp_send_json_success(
			array(
				'total'     => $this->total_items,
				'processed' => 0,
				'message'   => __( 'Processing started.', 'ff-entries-image-migrator' ),
			)
		);
	}
	
	/**
	 * Get the current processing status
	 *
	 * @return void
	 */
	public function get_status() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ff_process_images' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'ff-entries-image-migrator' ) ) );
			return;
		}
		
		$is_running = get_option( 'ff_image_processor_is_running', false );
		$total      = get_option( 'ff_image_processor_total', 0 );
		$processed  = get_option( 'ff_image_processor_processed', 0 );
		
		wp_send_json_success(
			array(
				'is_running'   => $is_running,
				'total'        => $total,
				'processed'    => $processed,
				'is_completed' => ( $processed >= $total ),
			)
		);
	}
	
	/**
	 * Process a batch of submissions
	 */
	public function process_batch() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ff_process_images' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'ff-entries-image-migrator' ) ) );
			return;
		}
		
		$form_id    = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : $this->form_id;
		$offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : $this->batch_size;
		
		// Get submissions from database.
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_submissions';
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, response FROM {$table} WHERE form_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$form_id, $batch_size, $offset
			)
		);
		
		$processed = 0;
		$total_processed = get_option( 'ff_image_processor_processed', 0 );
		$total = get_option( 'ff_image_processor_total', 0 );
		
		// Process each entry.
		foreach ( $entries as $entry ) {
			$this->process_entry( $entry );
			$processed++;
		}
		
		// Update progress.
		$total_processed += $processed;
		update_option( 'ff_image_processor_processed', $total_processed );
		
		$is_completed = ( $total_processed >= $total || count( $entries ) < $batch_size );
		
		if ( $is_completed ) {
			update_option( 'ff_image_processor_is_running', false );
		}
		
		wp_send_json_success(
			array(
				'next_offset' => $offset + $batch_size,
				'processed' => $total_processed,
				'total' => $total,
				'is_completed' => $is_completed,
			)
		);
	}
	
	/**
	 * Process a single entry
	 * 
	 * @param object $entry The submission entry
	 */
	private function process_entry( $entry ) {
		if ( empty( $entry ) || empty( $entry->response ) ) {
			return;
		}
		
		$response = json_decode( $entry->response, true );
		
		if ( empty( $response ) || ! is_array( $response ) ) {
			return;
		}
		
		// Process all fields to find any that contain image URLs
		foreach ( $response as $field_name => $field_value ) {
			// Skip empty values or arrays
			if ( empty( $field_value ) || is_array( $field_value ) ) {
				continue;
			}
			
			// Check if this field contains an image URL
			$field_value = trim( $field_value );
			
			// Skip if already processed or not an image URL
			if ( strpos( $field_value, '/wp-content/uploads/' ) !== false || ! $this->is_image_url( $field_value ) ) {
				continue;
			}
			
			// Download and add to media library
			$attachment_id = $this->add_remote_image_to_media_library( $field_value, $entry->id );
			
			if ( $attachment_id ) {
				// Update the submission with the new media library URL
				$attachment_url = wp_get_attachment_url( $attachment_id );
				
				if ( $attachment_url ) {
					$response[$field_name] = $attachment_url;
					
					// Update the entry in the database
					global $wpdb;
					$table = $wpdb->prefix . 'fluentform_submissions';
					
					$wpdb->update(
						$table,
						array( 'response' => json_encode( $response ) ),
						array( 'id' => $entry->id ),
						array( '%s' ),
						array( '%d' )
					);
				}
			}
		}
	}
	
	/**
	 * Process new submissions as they come in
	 * 
	 * @param array $formData The form data
	 * @param int $formId The form ID
	 * @param object $form The form object
	 */
	public function process_new_submission( $formData, $formId, $form ) {
		// Only process for our target form
		if ( $formId != $this->form_id ) {
			return $formData;
		}
		
		// Process all fields to find any that contain image URLs
		foreach ( $formData as $field_name => $field_value ) {
			// Skip empty values or arrays
			if ( empty( $field_value ) || is_array( $field_value ) ) {
				continue;
			}
			
			// Check if this field contains an image URL
			$field_value = trim( $field_value );
			
			// Skip if already processed or not an image URL
			if ( strpos( $field_value, '/wp-content/uploads/' ) !== false || ! $this->is_image_url( $field_value ) ) {
				continue;
			}
			
			// Download and add to media library
			$attachment_id = $this->add_remote_image_to_media_library( $field_value, 0 );
			
			if ( $attachment_id ) {
				// Update the submission with the new media library URL
				$attachment_url = wp_get_attachment_url( $attachment_id );
				
				if ( $attachment_url ) {
					$formData[$field_name] = $attachment_url;
				}
			}
		}
		
		return $formData;
	}
	
	/**
	 * Handle post-import processing
	 * 
	 * @param int $form_id The form ID
	 * @param int $count The number of imported items
	 */
	public function after_import_submission( $form_id, $count ) {
		// Only process for our target form
		if ( $form_id != $this->form_id ) {
			return;
		}
		
		// Store the count of imported items for later processing
		update_option( 'ff_image_processor_csv_count', $count );
	}
	
	/**
	 * Check if a URL points to an image based on file extension
	 * 
	 * @param string $url The URL to check
	 * @return bool True if the URL has an image extension, false otherwise
	 */
	private function is_image_url( $url ) {
		$image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg' );
		$path_parts = pathinfo( $url );
		
		if ( isset( $path_parts['extension'] ) ) {
			return in_array( strtolower( $path_parts['extension'] ), $image_extensions );
		}
		
		return false;
	}
	
	/**
	 * Verify if a downloaded file is a valid image
	 * 
	 * @param string $temp_file Path to the temporary file
	 * @return bool True if the file is a valid image, false otherwise
	 */
	private function is_valid_image( $temp_file ) {
		if ( ! file_exists( $temp_file ) ) {
			return false;
		}
		
		$filetype = wp_check_filetype( basename( $temp_file ) );
		$valid_image_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp' );
		
		// Additional validation - try to get image size
		$image_size = @getimagesize( $temp_file );
		
		return in_array( $filetype['type'], $valid_image_types ) && $image_size !== false;
	}
	
	/**
	 * Add a remote image to the WordPress media library
	 * 
	 * @param string $image_url The URL of the remote image
	 * @param int $entry_id The submission entry ID
	 * @return int|bool The attachment ID if successful, false otherwise
	 */
	private function add_remote_image_to_media_library( $image_url, $entry_id ) {
		// Skip if the URL is empty or already in the media library
		if ( empty( $image_url ) || strpos( $image_url, '/wp-content/uploads/' ) !== false ) {
			return false;
		}
		
		// First, check if the URL appears to be an image by extension
		if ( ! $this->is_image_url( $image_url ) ) {
			return false;
		}
		
		// Include required files
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}
		
		// Get the file content and create a temporary file
		$temp_file = download_url( $image_url );
		
		if ( is_wp_error( $temp_file ) ) {
			return false;
		}
		
		// Verify the downloaded file is actually an image
		if ( ! $this->is_valid_image( $temp_file ) ) {
			@unlink( $temp_file ); // Delete the temp file
			return false;
		}
		
		// Get the filename from the URL
		$filename = basename( parse_url( $image_url, PHP_URL_PATH ) );
		
		// Create file array for wp_handle_sideload
		$file = array(
			'name'     => $filename,
			'tmp_name' => $temp_file
		);
		
		// Move the temporary file to the uploads directory
		$result = wp_handle_sideload( $file, array( 'test_form' => false ) );
		
		// Remove the temporary file
		@unlink( $temp_file );
		
		if ( isset( $result['error'] ) ) {
			return false;
		}
		
		// Create attachment post
		$attachment = array(
			'post_mime_type' => $result['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $result['url']
		);
		
		$attachment_id = wp_insert_attachment( $attachment, $result['file'] );
		
		// Generate attachment metadata
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $result['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );
		
		return $attachment_id;
	}
}
