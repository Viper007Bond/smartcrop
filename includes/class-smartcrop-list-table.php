<?php

class SmartCrop_List_Table extends WP_List_Table {
	public function __construct( $args = array() ) {
		parent::__construct(
			array(
				'plural'   => 'smartcrops',
				'singular' => 'smartcrop',
			)
		);
	}

	public function prepare_items() {
		// Why do I have to do this?
		$this->_column_headers = array( $this->get_columns(), array(), array(), $this->get_primary_column_name() );

		$per_page = 10;
		$start    = ( $this->get_pagenum() - 1 ) * $per_page;

		$cron_events = SmartCrop()->get_cron_events();

		$this->items = array();
		foreach ( $cron_events as $cron_event ) {
			$this->items[] = array(
				'attachment_id'  => $cron_event->args[0],
				'thumbnail_size' => $cron_event->args[1],
			);
		}
		$this->items = array_slice( $this->items, $start, $per_page );

		$this->set_pagination_args(
			array(
				'total_items' => count( $cron_events ),
				'per_page'    => $per_page,
			)
		);
	}

	public function get_columns() {
		return array(
			'attachment_id'  => _x( 'Attachment', 'column name', 'smartcrop' ),
			'thumbnail_size' => _x( 'Thumbnail Size', 'column name', 'smartcrop' ),
		);
	}

	public function column_attachment_id( $item ) {
		$attachment = get_post( $item['attachment_id'] );

		$attachment_title = ( $attachment->post_title ) ?
			$attachment->post_title :
			sprintf( __( 'Attachment #%d', 'smartcrop' ), $attachment->ID );

		printf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_post_link( $attachment->ID, 'raw' ) ),
			esc_html( $attachment_title )
		);
	}

	public function column_thumbnail_size( $item ) {
		printf(
			__( '<code>%1$s</code> (%2$d×%3$d pixels)', 'smartcrop' ),
			esc_html( $item['thumbnail_size']['label'] ),
			$item['thumbnail_size']['width'],
			$item['thumbnail_size']['height']
		);
	}

	public function no_items() {
		_e( 'No images in the queue.', 'smartcrop' );
	}
}
