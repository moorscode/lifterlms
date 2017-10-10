<?php
/**
 * WPDB database interactions
 * @since    [version]
 * @version  [version]
 */

// Restrict direct access
if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class LLMS_Abstract_Database_Store {

	protected $id = null;

	/**
	 * Object properties
	 * @var  array
	 */
	private $data = array();

	protected $date_created = 'created';
	protected $date_updated = 'updated';

	/**
	 * Array of table column name => format
	 * @var  array
	 */
	protected $columns = array();

	/**
	 * Primary Key column name => format
	 * @var  array
	 */
	protected $primary_key = array(
		'id' => '%d',
	);

	/**
	 * Database Table Name
	 * @var  string
	 */
	protected $table = '';

	/**
	 * Database Table Prefix
	 * @var  string
	 */
	protected $table_prefix = 'lifterlms_';

	/**
	 * Constructor
	 * @since    [version]
	 * @version  [version]
	 */
	public function __construct() {

		if ( ! $this->id ) {

			// if created dates supported, add current time to the data on construction
			if ( $this->date_created ) {
				$this->set( $this->date_created, current_time( 'mysql' ), false );
			}

			if ( $this->date_updated ) {
				$this->set( $this->date_updated, current_time( 'mysql' ), false );
			}

		}

	}

	/**
	 * Get object data
	 * @param    string     $key  key to retrieve
	 * @return   mixed
	 * @since    [version]
	 * @version  [version]
	 */
	public function __get( $key ) {

		return $this->data[ $key ];

	}

	/**
	 * Get object data
	 * @param    string     $key    key to retrieve
	 * @param    boolean    $cache  if true, save data to to the object for future gets
	 * @return   mixed
	 * @since    [version]
	 * @version  [version]
	 */
	public function get( $key, $cache = true ) {

		if ( ! isset( $this->data[ $key ] ) && $this->id ) {
			$res = $this->read( $key );
			if ( $cache ) {
				$this->set( $key, $res );
			}
			return $res;
		}
		return $this->$key;

	}

	/**
	 * Set object data
	 * @param    string    $key  column name
	 * @param    mixed     $val  column value
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	public function __set( $key, $val ) {

		$this->data[ $key ] = $val;

	}

	/**
	 * General setter
	 * @param    string     $key   column name
	 * @param    mixed      $val   column value
	 * @param    boolean    $save  if true, immediately persists to database
	 * @return   self
	 * @since    [version]
	 * @version  [version]
	 */
	public function set( $key, $val, $save = false ) {

		$this->$key = $val;
		if ( $save ) {
			$update = array( $key => $val );
			// if update date supported, add an updated date
			if ( $this->date_updated ) {
				$update[ $this->date_updated ] = current_time( 'mysql' );
			}
			$this->update( $update );
		}

		return $this; // allow chaining like $this->set( $key, $val )->save();

	}

	/**
	 * Setup an object with an array of data
	 * @param    array     $data  key => val
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	public function setup( $data ) {

		foreach ( $data as $key => $val ) {
			$this->set( $key, $val, false );
		}

	}

	/**
	 * Create the item in the database
	 * @return   int|false
	 * @since    [version]
	 * @version  [version]
	 */
	private function create() {

		if ( ! $this->data ) {
			return false;
		}

		global $wpdb;
		$format = array_map( array( $this, 'get_column_format' ), array_keys( $this->data ) );
		$res = $wpdb->insert( $this->get_table(), $this->data, $format );
		if ( 1 === $res ) {
			return $wpdb->insert_id;
		}
		return false;

	}

	/**
	 * Delete the object from the database
	 * @return   [type]     [description]
	 * @since    [version]
	 * @version  [version]
	 */
	public function delete() {

		if ( ! $this->id ) {
			return false;
		}

		global $wpdb;
		$where = array_combine( array_keys( $this->primary_key ), array( $this->id ) );
		$res = $wpdb->delete( $this->get_table(), $where, array_values( $this->primary_key ) );
		if ( $res ) {
			$this->id = null;
			$this->data = array();
			return true;
		}
		return false;

	}

	/**
	 * Read object data from the database
	 * @param    array|string  $keys   key name (or array of keys) to retrieve from the database
	 * @return   array|false           key=>val array of data or false when record not found
	 * @since    [version]
	 * @version  [version]
	 */
	private function read( $keys ) {

		global $wpdb;
		if ( is_array( $keys ) ) {
			$keys = implode( ', ', $keys );
		}
		$res = $wpdb->get_row( $wpdb->prepare( "SELECT {$keys} FROM {$this->get_table()} WHERE id = %d", $this->id ), ARRAY_A );
		return ! $res ? false : $res;

	}

	/**
	 * Update object data in the database
	 * @param    array     $data  data to update as key=>val
	 * @return   bool
	 * @since    [version]
	 * @version  [version]
	 */
	private function update( $data ) {

		global $wpdb;
		$format = array_map( array( $this, 'get_column_format' ), array_keys( $data ) );
		$where = array_combine( array_keys( $this->primary_key ), array( $this->id ) );
		$res = $wpdb->update( $this->get_table(), $data, $where, $format, array_values( $this->primary_key ) );
		return $res ? true : false;

	}

	/**
	 * Load the whole object from the database
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	protected function hydrate() {

		if ( $this->id ) {
			$res = $this->read( array_keys( $this->columns ) );
			if ( $res ) {
				$this->data = array_merge( $this->data, $res );
			}
		}

		return $this; // allow chaining

	}

	/**
	 * Save object to the database
	 * Creates is it doesn't already exist, updates if it does
	 * @return   boolean
	 * @since    [version]
	 * @version  [version]
	 */
	public function save() {

		if ( ! $this->id ) {

			$id = $this->create();
			if ( $id ) {
				$this->id = $id;
				return true;
			}
			return false;

		} else {

			return $this->update( $this->data );

		}


	}

	/**
	 * Retrieve the format for a column
	 * @param    string    $key  column name
	 * @return   string
	 * @since    [version]
	 * @version  [version]
	 */
	private function get_column_format( $key ) {

		if ( isset ( $this->columns[ $key ] ) ) {
			return $this->columns[ $key ];
		}
		return '%s';

	}

	/**
	 * Get the ID of the object
	 * @return   int
	 * @since    [version]
	 * @version  [version]
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the table Name
	 * @return   string
	 * @since    [version]
	 * @version  [version]
	 */
	private function get_table() {

		global $wpdb;
		return $wpdb->prefix . $this->table_prefix . $this->table;

	}

	/**
	 * Retrive object as an array
	 * @return   array
	 * @since    [version]
	 * @version  [version]
	 */
	public function to_array() {

		return array_merge( $this->primary_key, $this->data );

	}

}
