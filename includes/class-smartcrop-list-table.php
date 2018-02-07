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

		$this->items = array();

		foreach ( SmartCrop()->get_cron_events() as $event ) {
			$this->items[] = array(
				'attachment_id'  => $event->args[0],
				'thumbnail_size' => $event->args[1],
			);
		}
	}

	public function get_columns() {
		return array(
			'attachment_id'  => _x( 'Attachment', 'column name', 'smartcrop' ),
			'thumbnail_size' => _x( 'Thumbnail Size', 'column name', 'smartcrop' ),
		);
	}

	public function column_attachment_id( $item ) {
		$attachment = get_post( $item['attachment_id'] );

		printf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_post_link( $item['attachment_id'], 'raw' ) ),
			esc_html( $attachment->post_title )
		);
	}

	public function column_thumbnail_size( $item ) {
		printf(
			'<code>%s</code> (%d×%d pixels)',
			esc_html( $item['thumbnail_size']['label'] ),
			$item['thumbnail_size']['width'],
			$item['thumbnail_size']['height']
		);
	}
}