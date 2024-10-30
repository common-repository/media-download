jQuery( document ).ready( function() {


	function nagit() {
		// Maybe display the nag.
		if ( ! AAGK.MLFD.ispro && ( ( AAGK.MLFD.downloads % AAGK.MLFD.softlimit ) == 0 ) ) {
			jQuery( '#mlfd-download-nag' ).remove();

			$modal = jQuery( '<div>' )
			$modal.attr( 'id', 'mlfd-download-nag' );

			$title = jQuery( '<h3>' );
			$title.text( AAGK.MLFD.nag.subtitle.replace( '%d', AAGK.MLFD.downloads ) );

			$body = jQuery( '<p>' );
			$body.text( AAGK.MLFD.nag.message.replace( '%d', AAGK.MLFD.downloads ) );

			$features = jQuery( '<ol>' );
			jQuery( AAGK.MLFD.nag.features ).each( function( _, f ) {
				$li = jQuery( '<li>' );
				$li.text( f );

				$features.append( $li );
			} );

			$upgrade = jQuery( '<a>' );
			$upgrade.text( AAGK.MLFD.nag.upgrade );
			$upgrade.attr( 'href', AAGK.MLFD.nag.url );

			$modal.append( $title );
			$modal.append( $body );
			$modal.append( $features );
			$modal.append( $upgrade );

			$modal.hide();

			jQuery( 'body' ).append( $modal );

			tb_show( AAGK.MLFD.nag.title, '?TB_inline&inlineId=mlfd-download-nag&width=300&height=230' );
		}
	}

	// Download this media.
	jQuery( document ).on( 'click', '.mlfd-download', function( e ) {
		// Increment the download counter.
		AAGK.MLFD.downloads++;

		nagit();

		window.location = AAGK.MLFD.endpoint + '&id=' + wp.media.frames['edit'].model.id;
	} );

	// Download Bulk media.
	jQuery( document ).on( 'click', '.media-export-library', function( e ) {
		// Increment the download counter.
		AAGK.MLFD.downloads++;

		nagit();

		window.location = AAGK.MLFD.endpointBulkDownloads;
	} );
	
	// Replace media click.
	jQuery( document ).on( 'click', '.mlfd-replace', function( e ) {
		jQuery( '#mlfd-replace-ui' ).remove();
		let mediaPostId;
		if( jQuery('input#post_ID').length > 0 ) {
			mediaPostId = jQuery('input#post_ID').val();
		}

		$modal = jQuery( '<div>' )
		$modal.attr( 'id', 'mlfd-replace-ui' );

		$title = jQuery( '<h3>' );
		$title.text( AAGK.MLFD.ui.replace );

		$body = jQuery( '<p>' );
		$body.text( AAGK.MLFD.ui.replacefile );

		$form = jQuery( '<form>' );
		$form.attr( 'method', 'post' );
		$form.attr( 'enctype', 'multipart/form-data' );

		$form.append( '<input type="file" name="mlfd-replace-file" id="mlfd-replace-file">' );
		if( AAGK.MLFD.ui.mimeAttachmentType ) {
			$form.find( '[type="file"]' ).attr( 'accept', '.' + AAGK.MLFD.ui.mimeAttachmentType );
		} else {
			$form.find( '[type="file"]' ).attr( 'accept', '.' + wp.media.frames['edit'].model.attributes.filename.split('.').pop() );
		}

		$form.append( '<input type="hidden" name="mlfd-replace-id" id="mlfd-replace-id">' );
		if( mediaPostId ) {
			$form.find( '#mlfd-replace-id' ).attr( 'value', mediaPostId );
			$form.append( '<input type="hidden" name="mlfd-post-type-single" id="mlfd-replace-id" value="single_post">' );
		} else {

			$form.find( '[type="hidden"]' ).attr( 'value', wp.media.frames['edit'].model.id );
		}

		$form.append( '<input class="button media-button button-primary button-large" type="submit" value="' + AAGK.MLFD.ui.replace + '" name="submit">' );

		$modal.append( $title );
		$modal.append( $body );
		$modal.append( $form );

		$modal.hide();

		jQuery( 'body' ).append( $modal );

		tb_show( AAGK.MLFD.ui.replace, '?TB_inline&inlineId=mlfd-replace-ui&width=300&height=230' );
	} );

	

	// Dismiss the nag
	jQuery( document ).on( 'click', '.notice-mldf .notice-dismiss', function( e ) {
		jQuery.post( window.location, {
			'notice-mldf-dismiss': 1
		} );
	} );
} );