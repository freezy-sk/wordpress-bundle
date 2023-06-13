<?php

namespace Metabolism\WordpressBundle\Factory;

use Metabolism\WordpressBundle\Entity\Post;

class PostFactory {

	/**
	 * Create entity from post_type
	 * @param null $id
	 * @param bool $post_type
	 * @return bool|Post|\WP_Error
	 */
	public static function create($id=null, $post_type = false){

		if(empty($id)){

			return false;
		}
		elseif( is_array($id) ) {

			if( isset($id['ID']) )
				$id = $id['ID'];
			else
				return false;

			if(  isset($id['post_type']))
				$post_type = $id['post_type'];
		}
		if( is_object($id) ) {

			if( $id instanceof \WP_Post ) {

				$post = $id;
				$id = $post->ID;
				$post_type = $post->post_type;
			}
			else{

				return false;
			}
		}
		elseif( is_string($id) ) {

			$id = intval($id);

			if( !$id )
				return false;
		}

		if( !$post_type )
			$post_type = get_post_type($id);

		if( !$post_type )
			return false;

		$post_status = get_post_status( $id );

		switch($post_status){

			case '':
			case false:
			case 'trash':
			case 'auto-draft':
				return false;

			case 'private':

				if( (!is_user_logged_in() || !current_user_can( 'read_private_posts' )) )
					return false;
				break;

			case 'draft':
			case 'pending':
			case 'inherit':
			case 'future':

			if( (!is_user_logged_in() || !current_user_can( 'edit_posts' )) )
				return false;
			break;
		}

		return Factory::create($id, $post_type, 'post');
	}
}
