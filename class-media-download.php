<?php

require_once ABSPATH . 'wp-admin/includes/image.php';
class AAGK_Media_Download
{
    // Early plugin initialization.
    public static function bootstrap()
    {
        add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
        add_action( 'admin_bar_menu', function ( &$wp_admin_bar ) {
            $wp_admin_bar->add_menu( array(
                'parent' => 'appearance',
                'id'     => 'media-library',
                'title'  => __( 'Media' ),
                'href'   => admin_url( '/upload.php' ),
            ) );
        } );
    }
    
    public static function is_upload_screen_admin()
    {
        $current_post = get_post();
        // check if current screen attachment then we can load the media js and css files
        if ( $current_post && $current_post->post_type == 'attachment' ) {
            return true;
        }
        if ( !function_exists( 'get_current_screen' ) || !($screen = get_current_screen()) || $screen->base != 'upload' ) {
            return false;
        }
        return true;
    }
    
    public static function admin_init()
    {
        add_action( 'print_media_templates', array( __CLASS__, 'inject_js' ) );
        add_action( 'admin_action_mlfd_single_download_action', array( __CLASS__, 'mlfd_single_download_action_handler' ) );
        add_action( 'attachment_submitbox_misc_actions', array( __CLASS__, 'add_media_button_download' ) );
        add_action( 'admin_notices', function () {
            if ( !self::is_upload_screen_admin() ) {
                return;
            }
            if ( defined( 'DISABLE_NAG_NOTICES' ) && DISABLE_NAG_NOTICES ) {
                return;
            }
            if ( self::f()->can_use_premium_code() ) {
                return;
            }
            if ( get_transient( 'mldf-upgrade-nag' ) ) {
                return;
            }
            ?>
                <div class="notice notice-info is-dismissible notice-mldf">
                    <h3><?php 
            _e( 'Thanks for using Media Library File Download!', 'media-download' );
            ?></h3>
                    <p><?php 
            printf( __( 'Want more features? Upgrade to <a href="%s">PRO</a>', 'media-download' ), esc_attr( self::f()->get_upgrade_url() ) );
            ?></p>
                    <ul>
                        <li><?php 
            _e( 'Bulk downloads', 'media-download' );
            ?></li>
                        <li><?php 
            _e( 'Unlimited downloads', 'media-download' );
            ?></li>
                        <li><?php 
            _e( 'Download from thumbnail', 'media-download' );
            ?></li>
                        <li><?php 
            _e( 'Download from list view', 'media-download' );
            ?></li>

                    </ul>
                </div>
            <?php 
        } );
        add_action( 'admin_enqueue_scripts', function () {
            if ( !self::is_upload_screen_admin() ) {
                return;
            }
            if ( !current_user_can( 'upload_files' ) ) {
                return;
            }
            add_thickbox();
            wp_enqueue_script( 'aagk-media-download', plugins_url( 'media-download.js', __FILE__ ), array( 'jquery', 'thickbox' ) );
            wp_enqueue_style( 'aagk-media-download', plugins_url( 'media-download.css', __FILE__ ) );
            $current_post = get_post();
            $mime_attachment_type = '';
            // check if current screen attachment add get the file ext
            
            if ( $current_post && $current_post->post_type == 'attachment' ) {
                $mime_attachment_type = $current_post->post_mime_type;
                $file_url = wp_get_attachment_url( $current_post->ID );
                $filetype = wp_check_filetype( $file_url );
                $mime_attachment_type = $filetype['ext'];
            }
            
            wp_localize_script( 'aagk-media-download', 'AAGK', array(
                'MLFD' => array(
                'endpoint'              => add_query_arg( 'mlfd-action', 'download', admin_url( 'upload.php' ) ),
                'endpointBulkDownloads' => add_query_arg( 'mlfd-action-bulk', 'download', admin_url( 'upload.php' ) ),
                'downloads'             => get_option( 'aagk-mfld-downloads', 0 ),
                'softlimit'             => 10,
                'ispro'                 => self::f()->can_use_premium_code(),
                'nag'                   => array(
                'title'    => __( 'Nice!', 'media-download' ),
                'subtitle' => __( 'You have downloaded %d files!', 'media-download' ),
                'message'  => __( "If you're enjoying this plugin consider upgrading to PRO:", 'media-download' ),
                'features' => array(
                __( 'Unlimited downloads', 'media-download' ),
                __( 'Bulk downloads', 'media-download' ),
                __( 'Download from thumbnail', 'media-download' ),
                __( 'Download from list view', 'media-download' )
            ),
                'upgrade'  => __( 'Upgrade now', 'media-download' ),
                'url'      => self::f()->get_upgrade_url(),
            ),
                'ui'                    => array(
                'download'           => __( 'Download Archive', 'media-download' ),
                'replace'            => __( 'Replace', 'media-download' ),
                'replacefile'        => __( 'Replace the current file with one of the same type', 'media-download' ),
                'mimeAttachmentType' => $mime_attachment_type,
            ),
            ),
            ) );
        } );
        self::handle_download_request();
        self::handle_replace_request();
        self::handle_notice_dimiss();
        self::handle_bulk_download_request();
        add_filter(
            'plugin_action_links_' . self::f()->get_plugin_basename(),
            function (
            $actions,
            $plugin_file,
            $plugin_data,
            $context
        ) {
            
            if ( !self::f()->can_use_premium_code() ) {
                $style = 'font-weight: bold; color: #ff7ba5;';
                $actions['go-pro media-download-free'] = sprintf( '<a style="%s" href="%s">Go PRO</a>', $style, esc_url( self::f()->get_upgrade_url() ) );
            }
            
            return $actions;
        },
            10,
            4
        );
        add_action( 'admin_head-upload.php', array( __CLASS__, 'show_bulk_media_download_dom' ) );
    }
    
    /**
     * add buttons on single view media by list
     *
     *
     * @param WP_Post $post WP_Post object for the current attachment.
     */
    public static function add_media_button_download( $post )
    {
        $download_url = add_query_arg( array(
            'action' => 'mlfd_single_download_action',
            'post'   => $post->ID,
            'nonce'  => wp_create_nonce( 'mlfd-image-' . $post->ID ),
        ), admin_url() );
        ?>
    
        <div class="misc-pub-section misc-pub-attachment">
        <span class="copy-to-clipboard-container">
            <a class="button edit-media" href="<?php 
        echo  esc_url( $download_url ) ;
        ?>" rel="permalink"><?php 
        echo  esc_html__( 'Download', 'media-download' ) ;
        ?></a>
            <span class="success hidden" aria-hidden="true"><?php 
        esc_html__( 'Download', 'media-download' );
        ?></span>&nbsp;&nbsp;
        
            <button type="button" class="mlfd-replace button media-button button-primary button-large"><?php 
        esc_html_e( 'Replace', 'media-download' );
        ?></button>
        </span>
    
        </div>
    <?php 
    }
    
    // add download media buttons for list view media
    public static function add_media_row_download_actions( $actions, $post )
    {
        
        if ( current_user_can( 'upload_files' ) ) {
            $download_url = add_query_arg( array(
                'action' => 'mlfd_single_download_action',
                'post'   => $post->ID,
                'nonce'  => wp_create_nonce( 'mlfd-image-' . $post->ID ),
            ), admin_url() );
            $actions['mlfd_single_download_action'] = '<a href="' . esc_url( $download_url ) . '" rel="permalink">' . esc_html__( ' Download', 'media-download' ) . '</a>';
        }
        
        return $actions;
    }
    
    // add action handler for single list download button
    public static function mlfd_single_download_action_handler()
    {
        
        if ( isset( $_GET['nonce'], $_GET['post'] ) && wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'mlfd-image-' . sanitize_text_field( wp_unslash( $_GET['post'] ) ) ) ) {
            $post_id = sanitize_text_field( wp_unslash( $_GET['post'] ) );
            if ( empty($post_id) ) {
                wp_die( esc_html__( 'Post not set. Please try again.', 'media-download' ) );
            }
            $post = get_post( (int) $post_id );
            $return_page = '';
            if ( is_object( $post ) && $post->post_type !== 'post' ) {
                $return_page = '?post_type=' . $post->post_type;
            }
            if ( !($attachment = $post) ) {
                return;
            }
            if ( 'attachment' !== $attachment->post_type ) {
                return;
            }
            if ( !($path = get_attached_file( $attachment->ID )) ) {
                return;
            }
            update_option( 'aagk-mfld-downloads', get_option( 'aagk-mfld-downloads', 0 ) + 1 );
            header( 'Content-Type: ' . $attachment->post_mime_type );
            header( 'Content-Length: ' . filesize( $path ) );
            header( 'Content-Disposition: attachment; filename=' . basename( $path ) );
            header( 'Content-Transfer-Encoding: binary' );
            header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
            readfile( $path );
            exit;
            
            if ( isset( $_GET['edit_post_page'] ) ) {
                wp_safe_redirect( admin_url( 'upload.php?post=' . (int) $post_id . '&action=edit' ), 302 );
                exit;
            } else {
                wp_safe_redirect( admin_url( 'upload.php' . $return_page ), 302 );
                exit;
            }
        
        } else {
            wp_die( esc_html__( 'Invalid nonce. Please try again.', 'media-download' ) );
        }
    
    }
    
    // Add a Default Bulk Action list
    public static function add_default_bulk_actions( $bulk_array )
    {
        $bulk_array['media_download_bulk_action'] = __( 'Download Archive', 'media-download' );
        return $bulk_array;
    }
    
    // add handler for bulk download images
    public static function list_bulk_action_handler( $redirect, $doaction, $object_ids )
    {
        // let's remove query args first
        $redirect = remove_query_arg( array( 'media_download_bulk_action' ), $redirect );
        // do something for "Bulk downloads" bulk action
        
        if ( 'media_download_bulk_action' === $doaction ) {
            $paths = array();
            foreach ( $object_ids as $id ) {
                if ( !($attachment = get_post( $id )) ) {
                    continue;
                }
                if ( 'attachment' !== $attachment->post_type ) {
                    continue;
                }
                if ( !($path = get_attached_file( $attachment->ID )) ) {
                    continue;
                }
                $paths[$attachment->ID] = $path;
            }
            
            if ( $paths ) {
                $zipfile = wp_tempnam();
                
                if ( false && class_exists( 'ZipArchive' ) ) {
                    $zip = new ZipArchive();
                    $zip->open( $zipfile, ZipArchive::CREATE );
                    foreach ( $paths as $id => $path ) {
                        $zip->addFile( $path, "{$id}-" . basename( $path ) );
                    }
                    $zip->close();
                } else {
                    require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
                    mbstring_binary_safe_encoding();
                    $archive = new PclZip( $zipfile );
                    $archive->add( $paths, PCLZIP_OPT_REMOVE_ALL_PATH );
                    reset_mbstring_encoding();
                }
                
                header( 'Content-Type: application/zip' );
                header( 'Content-Length: ' . filesize( $zipfile ) );
                header( 'Content-Disposition: attachment; filename=media-library' . time() . '.zip' );
                header( 'Content-Transfer-Encoding: binary' );
                header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
                readfile( $zipfile );
                unlink( $zipfile );
                exit;
            }
            
            // do not forget to add query args to URL because we will show notices later
            $redirect = add_query_arg(
                'media_download_bulk_action',
                // just a parameter for URL
                count( $object_ids ),
                // how many posts have been selected
                $redirect
            );
        }
        
        return $redirect;
    }
    
    public static function inject_js()
    {
        // Maybe one day we can use proper injection
        // https://core.trac.wordpress.org/ticket/50185
        // can we now use 'wp-media-grid-ready' to expose media library modal for modification?
        if ( !current_user_can( 'upload_files' ) ) {
            return;
        }
        ?>
            <script>
                (function() {
                    // Alter the attachment view template files.
                    var original   = jQuery( '#tmpl-attachment-details' ).html();
                    var original_2 = jQuery( '#tmpl-attachment-details-two-column' ).html();

                    var prefix_download = '<button type="button" class="mlfd-download button media-button button-primary button-large"><?php 
        esc_html_e( 'Download', 'media-download' );
        ?></button>';

                    var prefix_replace = '<button type="button" class="mlfd-replace button media-button button-primary button-large"><?php 
        esc_html_e( 'Replace', 'media-download' );
        ?></button>';                    

                    jQuery( '#tmpl-attachment-details-two-column' ).html(
                        original_2.replace( '<div class="compat-meta">',

                            '<div class="media-download">' + prefix_download + prefix_replace + '</div'

                            + '<div class="compat-meta">' )
                    );

                    
                }());
            </script>
        <?php 
    }
    
    // The download handler.
    private static function handle_download_request()
    {
        if ( empty($_GET['mlfd-action']) ) {
            return;
        }
        if ( empty($_GET['id']) ) {
            return;
        }
        if ( 'download' !== $_GET['mlfd-action'] ) {
            return;
        }
        if ( !current_user_can( 'upload_files' ) ) {
            return;
        }
        if ( !($attachment = get_post( $_GET['id'] )) ) {
            return;
        }
        if ( 'attachment' !== $attachment->post_type ) {
            return;
        }
        if ( !($path = get_attached_file( $attachment->ID )) ) {
            return;
        }
        update_option( 'aagk-mfld-downloads', get_option( 'aagk-mfld-downloads', 0 ) + 1 );
        header( 'Content-Type: ' . $attachment->post_mime_type );
        header( 'Content-Length: ' . filesize( $path ) );
        header( 'Content-Disposition: attachment; filename=' . basename( $path ) );
        header( 'Content-Transfer-Encoding: binary' );
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        readfile( $path );
        exit;
    }
    
    // The download Bulk handler.
    private static function handle_bulk_download_request()
    {
        if ( empty($_GET['mlfd-action-bulk']) ) {
            return;
        }
        if ( 'download' !== $_GET['mlfd-action-bulk'] ) {
            return;
        }
        if ( !current_user_can( 'upload_files' ) ) {
            return;
        }
        $query_images_args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
        );
        $query_images = new WP_Query( $query_images_args );
        $paths = array();
        foreach ( $query_images->posts as $image ) {
            $paths[] = get_attached_file( $image->ID );
        }
        
        if ( $paths ) {
            $site_title = self::handle_url_web( get_bloginfo( 'url' ) );
            $folder_name = 'mlfd-' . str_replace( ' ', '-', $site_title ) . '.zip';
            // enable output of HTTP headers
            $options = new ZipStream\Option\Archive();
            $options->setSendHttpHeaders( true );
            $options->setLargeFileSize( 30000000 );
            // Send http headers (default is false)
            $options->setSendHttpHeaders( true );
            // default is false
            $options->setFlushOutput( true );
            $options->setLargeFileMethod( ZipStream\Option\Method::STORE() );
            $options->setLargeFileMethod( ZipStream\Option\Method::DEFLATE() );
            // create a new zipstream object
            $zip = new ZipStream\ZipStream( strtolower( $folder_name ), $options );
            foreach ( $paths as $id => $path ) {
                try {
                    $zip->addFileFromPath( basename( $path ), $path );
                } catch ( Exception $e ) {
                }
            }
            // finish the zip stream
            $zip->finish();
            exit;
        }
    
    }
    
    public static function handle_url_web( $input )
    {
        // Remove the http://, www., and slash(/) from the URL
        // If URI is like, eg. www.abc.com/
        $input = trim( $input, '/' );
        // If not have http:// or https:// then prepend it
        if ( !preg_match( '#^http(s)?://#', $input ) ) {
            $input = 'http://' . $input;
        }
        $urlParts = parse_url( $input );
        // Remove www.
        $domains_back = preg_replace( '/^www\\./', '', $urlParts['host'] );
        return preg_replace( "/\\..*\$/", '', $domains_back );
    }
    
    // Simple nag disable.
    private static function handle_notice_dimiss()
    {
        if ( isset( $_POST['notice-mldf-dismiss'] ) && current_user_can( 'upload_files' ) ) {
            set_transient( 'mldf-upgrade-nag', true, MONTH_IN_SECONDS );
        }
    }
    
    // The simple replace handler.
    private static function handle_replace_request()
    {
        header( "Cache-Control: no-cache, must-revalidate" );
        header( "Pragma: no-cache" );
        header( "Expires: Sat, 26 Jul 1997 05:00:00 GMT" );
        // Date in the past
        if ( empty($_POST['mlfd-replace-id']) ) {
            return;
        }
        if ( empty($_FILES['mlfd-replace-file']) ) {
            return;
        }
        if ( !current_user_can( 'upload_files' ) ) {
            return;
        }
        if ( !($attachment = get_post( $_POST['mlfd-replace-id'] )) ) {
            return;
        }
        if ( 'attachment' !== $attachment->post_type ) {
            return;
        }
        if ( !($path = get_attached_file( $attachment->ID )) ) {
            return;
        }
        if ( $_FILES['mlfd-replace-file']['type'] != $attachment->post_mime_type ) {
            wp_die( __( '<button onclick="history.back()">Go Back</button> Please upload a file of the same type. For example, if original was a png, upload a png.', 'media-download' ) );
        }
        if ( !wp_check_filetype( $_FILES['mlfd-replace-file']['name'] ) ) {
            wp_die( __( '<button onclick="history.back()">Go Back</button> Please upload a file of the same type. For example, if original was a png, upload a png', 'media-download' ) );
        }
        $file = $_FILES['mlfd-replace-file']['tmp_name'];
        $move_file_status = move_uploaded_file( $file, $path );
        
        if ( !$move_file_status ) {
            wp_die( __( 'Could not replace file.', 'media-download' ) );
        } else {
            
            if ( !class_exists( 'RegenerateThumbnails_Regenerator' ) ) {
                require_once __DIR__ . '/lib/regenerate-thumbnails/class-regeneratethumbnails-regenerator.php';
                $regenerator = RegenerateThumbnails_Regenerator::get_instance( $attachment->ID );
                $regenerator->regenerate( array(
                    'only_regenerate_missing_thumbnails' => false,
                ) );
                $imageId = $attachment->ID;
                $imagePath = wp_get_original_image_path( $imageId );
                if ( $imagePath && file_exists( $imagePath ) ) {
                    $status = wp_generate_attachment_metadata( $imageId, $imagePath );
                }
                $update_result = $regenerator->update_usages_in_posts();
            }
            
            // show success replace message for single view list image replace
            if ( isset( $_POST['mlfd-post-type-single'] ) ) {
                self::show_admin_notice_on_replace();
            }
        }
    
    }
    
    // show success replace message for single view list image replace
    private static function show_admin_notice_on_replace()
    {
        $class = 'notice fs-notice-body fs-notice success notice-success is-dismissible';
        $message = __( 'File replaced successfully! Hard refresh your browser to see your updated file.', 'media-download' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }
    
    // Return Freemius object.
    private static function f()
    {
        return aagk_mlfd_fs();
    }
    
    public static function show_bulk_media_download_dom()
    {
        if ( !current_user_can( 'upload_files' ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( $screen->id != 'upload' ) {
            return;
        }
        ?>
		<script type="text/javascript">
			 jQuery(function() {
				jQuery(".page-title-action").after( jQuery('<a href="#" class="button media-export-library"><?php 
        esc_html_e( 'Export Media Library', 'media-download' );
        ?></a>') );
			}); 	
		</script>
	
		<?php 
    }

}
