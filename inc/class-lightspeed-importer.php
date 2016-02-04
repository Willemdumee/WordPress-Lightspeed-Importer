<?php
/**
 * Lightspeed Importer Importer imports Lightspeed products
 *
 * @package WordPress
 * @subpackage Importer
 */
if (class_exists( 'WP_Importer' )) {
    /**
     * Class Lightspeed_Import
     */
    class Lightspeed_Importer extends WP_Importer
    {
        /**
         * @var Lightspeed_Importer
         * @since 1.4
         */
        private static $instance;

        /**
         * @var
         */
        var $language;
        var $posts = array();
        var $file;
        var $log = array();

        var $defaults = array(
            'lightspeed_publish_state' => 'draft',
            'lightspeed_post_type'     => 'post',
            'lightspeed_language'      => 'EN'
        );

        public static function instance() {
            if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Lightspeed_Importer ) ) {
                self::$instance = new Lightspeed_Importer;

                add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );

            }
            return self::$instance;

        }

        /**
         * Registered callback function for the Lightspeed Importer
         *
         * Manages the separate stages of the import process
         */
        function dispatch()
        {
            $this->header();
            // This all from the WordPress Importer plugin, handles most cases
            $step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
            switch ($step) {
                case 0:
                    $this->greet();
                    break;
                case 1:
                    check_admin_referer( 'import-lightspeed-upload' );
                    $this->language = ( isset( $_POST['lightspeed_language'] ) && ( $_POST['lightspeed_language'] !== '' ) ? $_POST['lightspeed_language'] : $this->defaults['lightspeed_language'] );
                    $result = $this->import();
                    if (is_wp_error( $result )) {
                        echo $result->get_error_message();
                    }
                    break;
                case 2:
                    check_admin_referer( 'import-lightspeed' );
                    $this->fetch_attachments = ( ! empty( $_POST['fetch_attachments'] ) && $this->allow_fetch_attachments() );
                    $this->id                = (int) $_POST['import_id'];
                    // Provides the actual file upload form
                    $file = get_attached_file( $this->id );
                    set_time_limit( 0 );
                    $this->import( $file );
                    break;
            }
            $this->footer();
        }

        /**
         *
         */
        function header()
        {
            echo '<div class="wrap">';
            echo '<h2>' . __( 'Import Products from Lightspeed' ) . '</h2>';
        }

        /**
         *
         */
        function greet()
        {
            $html = '';
            $html .= '<div class="narrow">';
            $html .= '<p>' . __( 'Lightspeed importer allows you to import Lightspeed products as posts from a Lightspeed CSV product export file into your WordPress database install.' );
            echo $html;
            $this->import_upload_form( "admin.php?import=lightspeed&amp;step=1" );
            echo '</div>';
        }

        /**
         * @param $action
         */
        function import_upload_form( $action )
        {
            $bytes      = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
            $size       = size_format( $bytes );
            $upload_dir = wp_upload_dir();
            if ( ! empty( $upload_dir['error'] )) :
                ?>
                <div class="error">
                <p><?php _e( 'Before you can upload your import file, you will need to fix the following error:' ); ?></p>

                <p><strong><?php echo $upload_dir['error']; ?></strong></p></div><?php
            else :
                ?>
                <div class="widefat form-table">
                    <div class="wrap">
                        <h3>Import Lightspeed CSV-file</h3>

                        <form enctype="multipart/form-data" id="import-upload-form" method="post"
                              action="<?php echo esc_attr( wp_nonce_url( $action, 'import-lightspeed-upload' ) ); ?>">
                            <p>
                                <label
                                    for="lightspeed_post_type"><?php _e( 'Select the post type you want to sue for the import' ); ?>
                                    <br/>
                                    <select name="lightspeed_post_type" id="lightspeed_post_type">
                                        <option value="">-- post type --</option>
                                        <?php
                                        $post_types = get_post_types( '', 'names' );

                                        foreach ($post_types as $post_type) {
                                            echo '<option value="' . $post_type . '">' . $post_type . '</option>';
                                        }; ?>
                                    </select>
                                </label>
                            </p>

                            <p>
                                <label
                                    for="lightspeed_lamguage"><?php _e( 'Select the language of the import file' ); ?>
                                    <br/>
                                    <select name="lightspeed_language" id="lightspeed_language">
                                        <option value="">-- Language --</option>
                                        <option value="NL">Dutch (NL)</option>
                                        <option value="EN">English (EN)</option>
                                        <option value="FR">French (FR)</option>
                                        <option value="DE">German (DE)</option>
                                        <option value="IT">Italian (IT)</option>
                                        <option value="AL">Alian (AL)</option>
                                    </select>
                                </label>
                            </p>

                            <p>
                                <label for="upload"><?php _e( 'Choose a .csv file from your computer:' ); ?>
                                    <small class="description">(<?php printf( __( 'Maximum size: %s' ), $size ); ?>)
                                    </small>
                                    <br/><input type="file" id="upload" name="import" size="25"/></label>
                                <input type="hidden" name="action" value="save"/>
                                <input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>"/>
                            </p>
                            <p class="submit"><input type="submit" class="submit button-primary"
                                                     value="Upload file and import"/></p>
                        </form>
                    </div>
                </div>
            <?php
            endif;
        }

        /**
         * The main controller for the actual import stage. Contains all the import steps.
         *
         * @param none
         *
         * @return none
         */
        function import()
        {
            $file = wp_import_handle_upload();

            if (isset( $file['error'] )) {
                echo $file['error'];
                return false;
            }

            $result = $this->post( $file );

            if (is_wp_error( $result )) {
                return $result;
            }

            wp_import_cleanup($file['id']);
            do_action('import_done', 'Lightspeed');
            echo '<h3>' . __('All done.') . '</h3>';
        }

        function post( $file )
        {
            $file   = $file['file'];
            if (empty( $file )) {
                $this->log['error'][] = 'No file uploaded, aborting.';
                //$messages             = $this->print_messages();
                //echo $messages[0];
                //echo $messages[1];
                return false;
            }
            $output = '<ol>';

            // Load the CSV processor
            require_once( CTRL_LI_PLUGIN_DIR . 'inc/CSVParser.php' );

            $time_start = microtime( true );
            $csv        = new File_CSV_DataSource;

            if ( ! $csv->load( $file )) {
                $this->log['error'][] = 'Failed to load file, aborting.';
                $messages             = $this->print_messages();
                echo $messages[0];
                echo $messages[1];
                return false;
            }

            // pad shorter rows with empty values
            $csv->symmetrize();

            // WordPress sets the correct timezone for date functions somewhere
            // in the bowels of wp_insert_post(). We need strtotime() to return
            // correct time before the call to wp_insert_post().
            $tz = get_option( 'timezone_string' );
            if ($tz && function_exists( 'date_default_timezone_set' )) {
                date_default_timezone_set( $tz );
            }

            $skipped  = 0;
            $imported = 0;
            $comments = 0;
            $languagecheck = false;
            $languageInCsv = false;
            foreach ($csv->connect() as $csv_data) {
                // check if selected language is present in file, otherwise abort.
                if (false == $languagecheck) {
                    $languageInCsv = $this->checkForLanguage($csv_data);

                    if ($languageInCsv === false) {
                        break;
                    }
                    $languagecheck = true;
                }

                if ($output .= $this->create_post( $csv_data )) {
                    $imported ++;
                } else {
                    $skipped ++;
                }
            }

            if (file_exists( $file )) {
                @unlink( $file );
            }

            $exec_time = microtime( true ) - $time_start;

            if ($skipped) {
                $this->log['notice'][] = "<b>Skipped {$skipped} posts (most likely due to empty title, body and excerpt).</b>";
            }
            $this->log['notice'][] = sprintf( "<b>Imported {$imported} posts and {$comments} comments in %.2f seconds.</b>",
                $exec_time );
            $output .= '</ol>';
            if ($languageInCsv === false) {
                $output = sprintf("<p>The language {$this->language} is not present in current CSV file.</p>");
            }

            $output .= '<p>' . $this->log['notice'][0] . '</p>';
            echo $output;
            return true;
        }

        function checkForLanguage($csv_data) {
            /* TODO create language as object variable; */
            return (isset($csv_data[$this->language . '_Title_Short']) ? true : false);
        }

        function create_post( $data )
        {
            $output = "<li>" . __( 'Importing post...' );
            $data   = array_merge( $this->defaults, $data );

            $status = ( isset( $_POST['lightspeed_publish_state'] ) ? $_POST['lightspeed_publish_state'] : $this->defaults['lightspeed_publish_state'] );

            $type = ( isset( $_POST['lightspeed_post_type'] ) && ( $_POST['lightspeed_post_type'] !== '' ) ? $_POST['lightspeed_post_type'] : $this->defaults['lightspeed_post_type'] );

            $language = ( isset( $_POST['lightspeed_language'] ) && ( $_POST['lightspeed_language'] !== '' ) ? $_POST['lightspeed_language'] : $this->defaults['lightspeed_language'] );

            $categories = array();
            // Lightspeed has a different column for every category
            if ($data[$language . '_Category_1'] != '') {
                $categories[] = $this->create_category($data[$language . '_Category_1']);
            }
            if ($data[$language . '_Category_2'] != '') {
                $categories[] = $this->create_category($data[$language . '_Category_2']);
            }
            if ($data[$language . '_Category_3'] != '') {
                $categories[] = $this->create_category($data[$language . '_Category_3']);
            }

            $new_post = array(
                'post_name'    => wp_strip_all_tags( $data[$language . '_Title_Short'] ),
                'post_title'   => wp_strip_all_tags( $data[$language . '_Title_Long'] ),
                'post_content' => convert_chars( $data[$language . '_Description_Short'] ),
                'post_status'  => $status,
                'post_type'    => $type,
                'post_category' => $categories
            );

            // We don't need to store this in the Custom Meta
            unset( $data['Body (HTML)'] );

            // pages don't have tags or categories
            if ('page' !== $type) {
                $new_post['tags_input'] = $data['Tags'];
            }

            if ($id = post_exists( $new_post['post_title'], $new_post['post_content'] )) {

                $new_post['ID'] = (int) $id;

                // Update Post
                $id = wp_update_post( $new_post );

                if (is_wp_error( $id )) {
                    return $id;
                }
                if ( ! $id) {
                    $output .= "Couldn't get post ID";
                    return false;
                }

                // Add Custom Fields
                //foreach($data as $key => $value) { update_post_meta($id, sanitize_user('Shopify '.$key), esc_attr($value)); }

                $output .= 'Updated !' . ' <a href="' . get_permalink( $id ) . '" target="blank">View ' . $data['Title'] . '</a>';
            } else { // A post does not yet exist.

                // Create Post
                $id = wp_insert_post( $new_post );

                // Add Custom Fields
                foreach ($data as $key => $value) {
                    add_post_meta( $id, sanitize_user( 'Shopify ' . $key ), esc_attr( $value ) );
                }
                $output .= 'Done !' . ' <a href="' . get_permalink( $id ) . '">View ' . $data['Title'] . '</a>';
            }

            // If you want to import categories, here we go!
            if (isset( $_POST['shopify_importer_import_categories'] ) && $_POST['shopify_importer_import_categories'] == 'yes') {
                $categories = explode( ',', $data['Vendor'] );
                if (0 != count( $categories )) {
                    wp_create_categories( $categories, $id );
                }
            }

            $output .= '</li>';

            return $output;
        }

        function create_category($term)
        {
            $category = term_exists($term, 'category');
            if ($category !== 0 && $category !== null) {
                return $category['term_id'];
            }
            $category = wp_create_category($term);
            return $category;
        }

        /**
         *
         */
        function load_textdomain() {
            load_plugin_textdomain( 'ctrl-lightspeed-importer', false, CTRL_LI_PLUGIN_DIR . '/languages' );
        }
    }
}