<?php
/**
 * REST API: WP_REST_Menu_Items_Controller class
 *
 * @package    WordPress
 * @subpackage REST_API
 */

/**
 * Core class to access nav items via the REST API.
 *
 * @see WP_REST_Posts_Controller
 */
class WP_REST_Menu_Items_Controller extends WP_REST_Posts_Controller {

	/**
	 * Get the post, if the ID is valid.
	 *
	 * @param int $id Supplied ID.
	 *
	 * @return object|WP_Error Post object if ID is valid, WP_Error otherwise.
	 */
	protected function get_post( $id ) {
		return $this->get_nav_menu_item( $id );
	}

	/**
	 * Get the nav menu item, if the ID is valid.
	 *
	 * @param int $id Supplied ID.
	 *
	 * @return object|WP_Error Post object if ID is valid, WP_Error otherwise.
	 */
	protected function get_nav_menu_item( $id ) {
		$post = parent::get_post( $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$nav_item = wp_setup_nav_menu_item( $post );

		return $nav_item;
	}

	/**
	 * Creates a single post.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'rest_post_exists', __( 'Cannot create existing post.' ), array( 'status' => 400 ) );
		}

		$prepared_nav_item = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $prepared_nav_item ) ) {
			return $prepared_nav_item;
		}
		$prepared_nav_item = (array) $prepared_nav_item;

		$nav_menu_item_id = wp_update_nav_menu_item( $prepared_nav_item['menu-id'], $prepared_nav_item['menu-item-db-id'], $prepared_nav_item );

		if ( is_wp_error( $nav_menu_item_id ) ) {
			if ( 'db_insert_error' === $nav_menu_item_id->get_error_code() ) {
				$nav_menu_item_id->add_data( array( 'status' => 500 ) );
			} else {
				$nav_menu_item_id->add_data( array( 'status' => 400 ) );
			}

			return $nav_menu_item_id;
		}

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );
		if ( is_wp_error( $nav_menu_item ) ) {
			$nav_menu_item->add_data( array( 'status' => 404 ) );

			return $nav_menu_item;
		}

		/**
		 * Fires after a single nav menu item is created or updated via the REST API.
		 *
		 * The dynamic portion of the hook name, `$this->post_type`, refers to the post type slug.
		 *
		 * @param object          $nav_menu_item Inserted or updated nav item object.
		 * @param WP_REST_Request $request       Request object.
		 * @param bool            $creating      True when creating a post, false when updating.
		 *                                       SA
		 */
		do_action( "rest_insert_{$this->post_type}", $nav_menu_item, $request, true );

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			$meta_update = $this->meta->update_value( $request['meta'], $nav_menu_item_id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );
		$fields_update = $this->update_additional_fields_for_object( $nav_menu_item, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		/**
		 * Fires after a single nav menu item is completely created or updated via the REST API.
		 *
		 * The dynamic portion of the hook name, `$this->post_type`, refers to the post type slug.
		 *
		 * @param object          $nav_menu_item Inserted or updated nav item object.
		 * @param WP_REST_Request $request       Request object.
		 * @param bool            $creating      True when creating a post, false when updating.
		 */
		do_action( "rest_after_insert_{$this->post_type}", $nav_menu_item, $request, true );

		$response = $this->prepare_item_for_response( $nav_menu_item, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $nav_menu_item_id ) ) );

		return $response;
	}

	/**
	 * Updates a single nav menu item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$valid_check = $this->get_nav_menu_item( $request['id'] );
		if ( is_wp_error( $valid_check ) ) {
			return $valid_check;
		}

		$prepared_nav_item = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $prepared_nav_item ) ) {
			return $prepared_nav_item;
		}

		$prepared_nav_item = (array) $prepared_nav_item;

		$nav_menu_item_id = wp_update_nav_menu_item( $prepared_nav_item['menu-id'], $prepared_nav_item['menu-item-db-id'], $prepared_nav_item );

		if ( is_wp_error( $nav_menu_item_id ) ) {
			if ( 'db_update_error' === $nav_menu_item_id->get_error_code() ) {
				$nav_menu_item_id->add_data( array( 'status' => 500 ) );
			} else {
				$nav_menu_item_id->add_data( array( 'status' => 400 ) );
			}

			return $nav_menu_item_id;
		}

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );
		if ( is_wp_error( $nav_menu_item ) ) {
			$nav_menu_item->add_data( array( 'status' => 404 ) );

			return $nav_menu_item;
		}

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php */
		do_action( "rest_insert_{$this->post_type}", $nav_menu_item, $request, false );

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			$meta_update = $this->meta->update_value( $request['meta'], $nav_menu_item->ID );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );
		$fields_update = $this->update_additional_fields_for_object( $nav_menu_item, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php */
		do_action( "rest_after_insert_{$this->post_type}", $nav_menu_item, $request, false );

		$response = $this->prepare_item_for_response( $nav_menu_item, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Prepares a single post for create or update.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return stdClass|WP_Error
	 */
	protected function prepare_item_for_database( $request ) {
		$menu_item_db_id = $request['id'];
		$menu_item_obj   = $this->get_nav_menu_item( $menu_item_db_id );
		// Need to persist the menu item data. See https://core.trac.wordpress.org/ticket/28138 .
		if ( ! is_wp_error( $menu_item_obj ) ) {
			// Correct the menu position if this was the first item. See https://core.trac.wordpress.org/ticket/28140 .
			$position = ( 0 === $menu_item_obj->menu_order ) ? 1 : $menu_item_obj->menu_order;

			$prepared_nav_item = array(
				'menu-item-db-id'       => $menu_item_db_id,
				'menu-item-object-id'   => $menu_item_obj->object_id,
				'menu-item-object'      => $menu_item_obj->object,
				'menu-item-parent-id'   => $menu_item_obj->menu_item_parent,
				'menu-item-position'    => $position,
				'menu-item-title'       => $menu_item_obj->title,
				'menu-item-url'         => $menu_item_obj->url,
				'menu-item-description' => $menu_item_obj->description,
				'menu-item-attr-title'  => $menu_item_obj->attr_title,
				'menu-item-target'      => $menu_item_obj->target,
				'menu-item-classes'     => implode( ' ', $menu_item_obj->classes ), // stored in the database as array.
				'menu-item-xfn'         => $menu_item_obj->xfn,
				'menu-item-status'      => $menu_item_obj->post_status,
				'menu-id'               => $this->get_menu_id( $menu_item_db_id ),
			);
		} else {
			$prepared_nav_item = array(
				'menu-id'               => 0,
				'menu-item-db-id'       => 0,
				'menu-item-object-id'   => 0,
				'menu-item-object'      => '',
				'menu-item-parent-id'   => 0,
				'menu-item-position'    => 0,
				'menu-item-type'        => 'custom',
				'menu-item-title'       => '',
				'menu-item-url'         => '',
				'menu-item-description' => '',
				'menu-item-attr-title'  => '',
				'menu-item-target'      => '',
				'menu-item-classes'     => '',
				'menu-item-xfn'         => '',
				'menu-item-status'      => 'publish',
			);
		}

		$mapping = array(
			'menu-id'               => 'menu_id',
			'menu-item-db-id'       => 'id',
			'menu-item-object-id'   => 'object_id',
			'menu-item-object'      => 'object',
			'menu-item-parent-id'   => 'parent',
			'menu-item-position'    => 'menu_order',
			'menu-item-type'        => 'type',
			'menu-item-url'         => 'url',
			'menu-item-description' => 'description',
			'menu-item-attr-title'  => 'attr_title',
			'menu-item-target'      => 'target',
			'menu-item-classes'     => 'classes',
			'menu-item-xfn'         => 'xfn',
			'menu-item-status'      => 'status',
		);

		$schema = $this->get_item_schema();

		foreach ( $mapping as $original => $api_request ) {
			if ( ! empty( $schema['properties'][ $api_request ] ) && isset( $request[ $api_request ] ) ) {
				$prepared_nav_item[ $original ] = rest_sanitize_value_from_schema( $request[ $api_request ], $schema['properties'][ $api_request ] );
			}
		}

		// Nav menu title.
		if ( ! empty( $schema['properties']['title'] ) && isset( $request['title'] ) ) {
			if ( is_string( $request['title'] ) ) {
				$prepared_nav_item['menu-item-title'] = $request['title'];
			} elseif ( ! empty( $request['title']['raw'] ) ) {
				$prepared_nav_item['menu-item-title'] = $request['title']['raw'];
			}
		}

		// Check if object id existing before saving.
		if ( ! $prepared_nav_item['menu-item-object'] && $prepared_nav_item['menu-item-object-id'] ) {
			if ( 'taxonomy' === $prepared_nav_item['menu-item-type'] ) {
				$original = get_term( (int) $prepared_nav_item['menu-item-object-id'] );
				if ( empty( $original ) ) {
					return new WP_Error( 'rest_term_invalid_id', __( 'Invalid term ID.' ), array( 'status' => 400 ) );
				}
				$prepared_nav_item['menu-item-object'] = get_term_field( 'taxonomy', $original );
			} elseif ( 'post_type' === $prepared_nav_item['menu-item-type'] ) {
				$original = get_post( (int) $prepared_nav_item['menu-item-object-id'] );
				if ( empty( $original ) ) {
					return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post  ID.' ), array( 'status' => 400 ) );
				}
				$prepared_nav_item['menu-item-object'] = get_post_type( $original );
			}
		}

		// Check if menu item is type custom, then title and url are required.
		if ( 'custom' === $prepared_nav_item['menu-item-type'] ) {
			if ( '' === $prepared_nav_item['menu-item-title'] ) {
				return new WP_Error( 'rest_title_required', __( 'Title require if menu item of type custom.' ), array( 'status' => 400 ) );
			}
			if ( empty( $prepared_nav_item['menu-item-url'] ) ) {
				return new WP_Error( 'rest_url_required', __( 'URL require if menu item of type custom.' ), array( 'status' => 400 ) );
			}
		}

		// If menu if is set, valid position and parent.
		if ( ! empty( $prepared_nav_item['menu-id'] ) ) {
			if ( ! is_nav_menu( $prepared_nav_item['menu-id'] ) ) {
				return new WP_Error( 'invalid_menu_id', __( 'Invalid menu ID.' ), array( 'status' => 400 ) );
			}

			$menu_items = (array) wp_get_nav_menu_items( $prepared_nav_item['menu-id'], array( 'post_status' => 'publish,draft' ) );
			if ( 0 === (int) $prepared_nav_item['menu-item-position'] ) {
				$last_item                               = array_pop( $menu_items );
				$prepared_nav_item['menu-item-position'] = ( $last_item && isset( $last_item->menu_order ) ) ? 1 + $last_item->menu_order : count( $menu_items );
			}

			$menu_item_ids = array();
			foreach ( $menu_items as $menu_item ) {
				$menu_item_ids[] = $menu_item->ID;
				if ( $menu_item->ID !== (int) $menu_item_db_id ) {
					if ( (int) $prepared_nav_item['menu-item-position'] === (int) $menu_item->menu_order ) {
						return new WP_Error( 'invalid_menu_order', __( 'Invalid menu position.' ), array( 'status' => 400 ) );
					}
				}
			}

			if ( $prepared_nav_item['menu-item-parent-id'] ) {
				if ( ! is_nav_menu_item( $prepared_nav_item['menu-item-parent-id'] ) ) {
					return new WP_Error( 'invalid_menu_item_parent', __( 'Invalid menu item parent.' ), array( 'status' => 400 ) );
				}
				if ( $menu_item_ids && ! in_array( $prepared_nav_item['menu-item-parent-id'], $menu_item_ids, true ) ) {
					return new WP_Error( 'invalid_item_parent', __( 'Invalid menu item parent.' ), array( 'status' => 400 ) );
				}
			}
		}

		foreach ( array( 'object_id', 'menu_item_parent', 'nav_menu_term_id' ) as $key ) {
			// Note we need to allow negative-integer IDs for previewed objects not inserted yet.
			$prepared_nav_item[ $key ] = intval( $prepared_nav_item[ $key ] );
		}

		foreach ( array( 'type', 'object', 'target' ) as $key ) {
			$prepared_nav_item[ $key ] = sanitize_key( $prepared_nav_item[ $key ] );
		}

		foreach ( array( 'xfn', 'classes' ) as $key ) {
			$value = $prepared_nav_item[ $key ];
			if ( ! is_array( $value ) ) {
				$value = explode( ' ', $value );
			}
			$prepared_nav_item[ $key ] = implode( ' ', array_map( 'sanitize_html_class', $value ) );
		}

		$prepared_nav_item['original_title'] = sanitize_text_field( $prepared_nav_item['original_title'] );

		// Apply the same filters as when calling wp_insert_post().

		/** This filter is documented in wp-includes/post.php */
		$prepared_nav_item['title'] = wp_unslash( apply_filters( 'title_save_pre', wp_slash( $prepared_nav_item['title'] ) ) );

		/** This filter is documented in wp-includes/post.php */
		$prepared_nav_item['attr_title'] = wp_unslash( apply_filters( 'excerpt_save_pre', wp_slash( $prepared_nav_item['attr_title'] ) ) );

		/** This filter is documented in wp-includes/post.php */
		$prepared_nav_item['description'] = wp_unslash( apply_filters( 'content_save_pre', wp_slash( $prepared_nav_item['description'] ) ) );

		if ( '' !== $prepared_nav_item['url'] ) {
			$prepared_nav_item['url'] = esc_url_raw( $prepared_nav_item['url'] );
			if ( '' === $prepared_nav_item['url'] ) {
				return new WP_Error( 'invalid_url', __( 'Invalid URL.' ) ); // Fail sanitization if URL is invalid.
			}
		}
		if ( 'publish' !== $prepared_nav_item['status'] ) {
			$prepared_nav_item['status'] = 'draft';
		}

		$prepared_nav_item['_invalid'] = (bool) $prepared_nav_item['_invalid'];

		$prepared_nav_item = (object) $prepared_nav_item;

		/**
		 * Filters a post before it is inserted via the REST API.
		 *
		 * The dynamic portion of the hook name, `$this->post_type`, refers to the post type slug.
		 *
		 * @param stdClass        $prepared_post An object representing a single post prepared
		 *                                       for inserting or updating the database.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( "rest_pre_insert_{$this->post_type}", $prepared_nav_item, $request );
	}

	/**
	 * Prepares a single post output for response.
	 *
	 * @param object          $post    Post object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $post, $request ) {
		$fields = $this->get_fields_for_response( $request );

		// Base fields for every post.
		$menu_item = wp_setup_nav_menu_item( $post );

		if ( in_array( 'id', $fields, true ) ) {
			$data['id'] = $menu_item->ID;
		}

		if ( in_array( 'title', $fields, true ) ) {
			add_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );

			$data['title'] = array(
				'raw'      => $menu_item->post_title,
				'rendered' => $menu_item->title,
			);

			remove_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
		}

		if ( in_array( 'status', $fields, true ) ) {
			$data['status'] = $menu_item->post_status;
		}

		if ( in_array( 'url', $fields, true ) ) {
			$data['url'] = $menu_item->url;
		}

		if ( in_array( 'attr_title', $fields, true ) ) {
			$data['attr_title'] = $menu_item->attr_title; // Same as post_excerpt.
		}

		if ( in_array( 'description', $fields, true ) ) {
			$data['description'] = $menu_item->description; // Same as post_content.
		}

		if ( in_array( 'type', $fields, true ) ) {
			$data['type'] = $menu_item->type; // Using 'item_type' since 'type' already exists.
		}
		if ( in_array( 'type_label', $fields, true ) ) {
			$data['type_label'] = $menu_item->type_label; // Using 'item_type_label' to match up with 'item_type' - IS READ ONLY!
		}

		if ( in_array( 'object', $fields, true ) ) {
			$data['object'] = $menu_item->object;
		}

		if ( in_array( 'object_id', $fields, true ) ) {
			$data['object_id'] = absint( $menu_item->object_id ); // Usually is a string, but lets expose as an integer.
		}

		if ( in_array( 'parent', $fields, true ) ) {
			$data['parent'] = absint( $menu_item->menu_item_parent ); // Same as post_parent, expose as integer.
		}

		if ( in_array( 'menu_order', $fields, true ) ) {
			$data['menu_order'] = absint( $menu_item->menu_order ); // Same as post_parent, expose as integer.
		}

		if ( in_array( 'menu_id', $fields, true ) ) {
			$data['menu_id'] = $this->get_menu_id( $menu_item->ID );
		}

		if ( in_array( 'target', $fields, true ) ) {
			$data['target'] = $menu_item->target;
		}

		if ( in_array( 'classes', $fields, true ) ) {
			$data['classes'] = (array) $menu_item->classes;
		}

		if ( in_array( 'xfn', $fields, true ) ) {
			$data['xfn'] = explode( ' ', $menu_item->xfn );
		}

		if ( in_array( 'meta', $fields, true ) ) {
			$data['meta'] = $this->meta->get_value( $menu_item->ID, $request );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$links = $this->prepare_links( $menu_item );
		$response->add_links( $links );

		if ( ! empty( $links['self']['href'] ) ) {
			$actions = $this->get_available_actions( $menu_item, $request );

			$self = $links['self']['href'];

			foreach ( $actions as $rel ) {
				$response->add_link( $rel, $self );
			}
		}

		/**
		 * Filters the post data for a response.
		 *
		 * The dynamic portion of the hook name, `$this->post_type`, refers to the post type slug.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param object           $post     Post object.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( "rest_prepare_{$this->post_type}", $response, $post, $request );
	}

	/**
	 * Prepares links for the request.
	 *
	 * @param object $menu_item Menu object.
	 *
	 * @return array Links for the given post.
	 */
	protected function prepare_links( $menu_item ) {
		$links = parent::prepare_links( $menu_item );

		if ( 'post_type' === $menu_item->type && ! empty( $menu_item->object_id ) ) {
			$post_type_object = get_post_type_object( $menu_item->object );
			if ( $post_type_object->show_in_rest ) {
				$rest_base                           = ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $post_type_object->name;
				$url                                 = rest_url( sprintf( 'wp/v2/%s/%d', $rest_base, $menu_item->object_id ) );
				$links['https://api.w.org/object'][] = array(
					'href'       => $url,
					'post_type'  => $menu_item->type,
					'embeddable' => true,
				);
			}
		} elseif ( 'taxonomy' === $menu_item->type && ! empty( $menu_item->object_id ) ) {
			$taxonomy_object = get_taxonomy( $menu_item->object );
			if ( $taxonomy_object->show_in_rest ) {
				$rest_base                           = ! empty( $taxonomy_object->rest_base ) ? $taxonomy_object->rest_base : $taxonomy_object->name;
				$url                                 = rest_url( sprintf( 'wp/v2/%s/%d', $rest_base, $menu_item->object_id ) );
				$links['https://api.w.org/object'][] = array(
					'href'       => $url,
					'taxonomy'   => $menu_item->type,
					'embeddable' => true,
				);
			}
		}

		return $links;
	}

	/**
	 * Retrieve Link Description Objects that should be added to the Schema for the posts collection.
	 *
	 * @return array
	 */
	protected function get_schema_links() {
		$links   = parent::get_schema_links();
		$href    = rest_url( "{$this->namespace}/{$this->rest_base}/{id}" );
		$links[] = array(
			'rel'          => 'https://api.w.org/object',
			'title'        => __( 'Get linked object.' ),
			'href'         => $href,
			'targetSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'object' => array(
						'type' => 'integer',
					),
				),
			),
		);

		return $links;
	}

	/**
	 * Retrieves the term's schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => $this->post_type,
			'type'    => 'object',
		);

		$schema['properties']['title'] = array(
			'description' => __( 'The title for the object.' ),
			'type'        => 'object',
			'context'     => array( 'view', 'edit', 'embed' ),
			'arg_options' => array(
				'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
				'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
			),
			'properties'  => array(
				'raw'      => array(
					'description' => __( 'Title for the object, as it exists in the database.' ),
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
				'rendered' => array(
					'description' => __( 'HTML title for the object, transformed for display.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		$schema['properties']['id'] = array(
			'description' => __( 'Unique identifier for the object.' ),
			'type'        => 'integer',
			'default'     => 0,
			'minimum'     => 0,
			'context'     => array( 'view', 'edit', 'embed' ),
			'readonly'    => true,
		);

		$schema['properties']['menu_id'] = array(
			'description' => __( 'Unique identifier for the menu.' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'context'     => array( 'edit' ),
			'default'     => 0,
		);

		$schema['properties']['type_label'] = array(
			'description' => __( 'Name of type.' ),
			'type'        => 'string',
			'context'     => array( 'view', 'edit', 'embed' ),
			'readonly'    => true,
		);

		$schema['properties']['type'] = array(
			'description' => __( 'Type of menu item' ),
			'type'        => 'string',
			'enum'        => array( 'taxonomy', 'post_type', 'post_type_archive', 'custom' ),
			'default'     => 'custom',
			'context'     => array( 'view', 'edit', 'embed' ),
		);

		$schema['properties']['status'] = array(
			'description' => __( 'A named status for the object.' ),
			'type'        => 'string',
			'enum'        => array_keys( get_post_stati( array( 'internal' => false ) ) ),
			'default'     => 'publish',
			'context'     => array( 'view', 'edit', 'embed' ),
		);

		$schema['properties']['link'] = array(
			'description' => __( 'URL to the object.' ),
			'type'        => 'string',
			'format'      => 'uri',
			'context'     => array( 'view', 'edit', 'embed' ),
			'readonly'    => true,
		);

		$schema['properties']['parent'] = array(
			'description' => __( 'The ID for the parent of the object.' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'default'     => 0,
			'context'     => array( 'view', 'edit', 'embed' ),
		);

		$schema['properties']['attr_title'] = array(
			'description' => __( 'The title attribute of the link element for this menu item .' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'string',
		);

		$schema['properties']['classes'] = array(
			'description' => __( 'The array of class attribute values for the link element of this menu item .' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$schema['properties']['description'] = array(
			'description' => __( 'The description of this menu item.' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'string',
		);

		$schema['properties']['menu_order'] = array(
			'description' => __( 'The DB ID of the nav_menu_item that is this item\'s menu parent, if any . 0 otherwise . ' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'default'     => 0,
		);
		$schema['properties']['object']     = array(
			'description' => __( 'The type of object originally represented, such as "category," "post", or "attachment."' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'string',
		);

		$schema['properties']['object_id'] = array(
			'description' => __( 'The DB ID of the original object this menu item represents, e . g . ID for posts and term_id for categories .' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'default'     => 0,
		);

		$schema['properties']['target'] = array(
			'description' => __( 'The target attribute of the link element for this menu item . The family of objects originally represented, such as "post_type" or "taxonomy."' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'string',
		);

		$schema['properties']['type_label'] = array(
			'description' => __( 'The singular label used to describe this type of menu item.' ),
			'context'     => array( 'view', 'embed' ),
			'type'        => 'string',
			'readonly'    => true,
		);

		$schema['properties']['url'] = array(
			'description' => __( 'The URL to which this menu item points .' ),
			'type'        => 'string',
			'format'      => 'uri',
			'context'     => array( 'view', 'edit', 'embed' ),
		);

		$schema['properties']['xfn'] = array(
			'description' => __( 'The XFN relationship expressed in the link of this menu item . ' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$schema['properties']['_invalid'] = array(
			'description' => __( '      Whether the menu item represents an object that no longer exists .' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'boolean',
			'readonly'    => true,
		);

		$schema['properties']['meta'] = $this->meta->get_field_schema();

		$schema_links = $this->get_schema_links();

		if ( $schema_links ) {
			$schema['links'] = $schema_links;
		}

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Retrieves the query params for the posts collection.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['menu_order'] = array(
			'description' => __( 'Limit result set to posts with a specific menu_order value.' ),
			'type'        => 'integer',
		);

		$query_params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.' ),
			'type'        => 'string',
			'default'     => 'asc',
			'enum'        => array( 'asc', 'desc' ),
		);

		$query_params['orderby'] = array(
			'description' => __( 'Sort collection by object attribute.' ),
			'type'        => 'string',
			'default'     => 'menu_order',
			'enum'        => array(
				'author',
				'date',
				'id',
				'include',
				'modified',
				'parent',
				'relevance',
				'slug',
				'include_slugs',
				'title',
				'menu_order',
			),
		);

		return $query_params;
	}

	/**
	 * Determines the allowed query_vars for a get_items() response and prepares
	 * them for WP_Query.
	 *
	 * @param array           $prepared_args Optional. Prepared WP_Query arguments. Default empty array.
	 * @param WP_REST_Request $request       Optional. Full details about the request.
	 *
	 * @return array Items query arguments.
	 */
	protected function prepare_items_query( $prepared_args = array(), $request = null ) {
		$query_args = parent::prepare_items_query( $prepared_args, $request );

		// Map to proper WP_Query orderby param.
		if ( isset( $query_args['orderby'] ) && isset( $request['orderby'] ) ) {
			$orderby_mappings = array(
				'id'            => 'ID',
				'include'       => 'post__in',
				'slug'          => 'post_name',
				'include_slugs' => 'post_name__in',
				'menu_order'    => 'menu_order',
			);

			if ( isset( $orderby_mappings[ $request['orderby'] ] ) ) {
				$query_args['orderby'] = $orderby_mappings[ $request['orderby'] ];
			}
		}

		return $query_args;
	}

	/**
	 * Get menu id of current menu item.
	 *
	 * @param int $menu_item_id Menu item id.
	 *
	 * @return int
	 */
	protected function get_menu_id( $menu_item_id ) {
		$menu_ids = wp_get_post_terms( $menu_item_id, 'nav_menu', array( 'fields' => 'ids' ) );
		$menu_id  = 0;
		if ( $menu_ids && ! is_wp_error( $menu_ids ) ) {
			$menu_id = array_shift( $menu_ids );
		}

		return $menu_id;
	}
}
