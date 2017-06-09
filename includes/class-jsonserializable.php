<?php

if ( ! interface_exists( 'JsonSerializable' ) ) {
	define( 'WP_JSON_SERIALIZE_COMPATIBLE', true );
	interface JsonSerializable {
		public function jsonSerialize();
	}
}
