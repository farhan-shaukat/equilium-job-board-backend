<?php

namespace THJB\Api;

class RestApiBase
{
    public $post_type;

    protected $api_base;

    public $response_data;

    public $meta_arr;// found posts, posts per page, max num pages, current page

    public function __construct($api_base)
    {
        $this->post_type = 'post';
        $this->api_base = $api_base;
        $this->response_data = [];
        $this->meta_arr = [];
    }

    public function register_routes()
    {

    }

    public function index(\WP_REST_Request $request)
    {
        $args = $this->get_query_args($request);

        if ($args["s"]){
            $args['meta_query'] = array(
                array(
                    'key' => 'reference_number',
                    'value' => $args["s"],
                    'compare' => '=',
                )
            );
            $search = $args["s"];
        }

        unset($args["s"]);

        $posts_query2 = new \WP_Query($args);

		if ($search != ""){
			if ( $posts_query2->have_posts() ) {
				foreach ($posts_query2->posts as $post) {
					$this->response_data[] = $this->prepare_item($post);
				}
			}
		}

        // print_r($args);
        unset($args["meta_query"]);

        // if (is_user_logged_in()){
        // }

        $args["s"] = $search;

        // print_r($args);

        $posts_query = new \WP_Query($args);

        if ( $posts_query->have_posts() ) {
            foreach ($posts_query->posts as $post) {
                $this->response_data[] = $this->prepare_item($post);
            }
        }

        if ($search != ""){
            $postsFound = $posts_query->found_posts + $posts_query2->found_posts;
        } else {
            $postsFound = $posts_query->found_posts;
        }

        $this->meta_arr = [
            'found_posts'    => $postsFound,
            'page'           => (int) $request['page'],
            'max_page'       => $posts_query->max_num_pages,
            'per_page'       => (int) $request['per_page'],
        ];

        return $this->get_data();
    }

    protected function get_query_args($params)
    {
        $args = [
            'post_type'         => $this->post_type,
            'post_status'       => 'publish',
        ];

        if ( ! empty($params['per_page']) ) {
            $args['posts_per_page'] = (int) $params['per_page'];
        }

        if ( ! empty($params['page']) ) {
            $args['paged'] = (int) $params['page'];
        }

        return $args;
    }

    protected function get_data()
    {
        $response = [
            'meta' => $this->meta_arr,
            'data' => $this->response_data
        ];

        return $response;
    }

    protected function prepare_item($post)
    {
        return [
            'id'   => $post->ID,
            'name' => $post->post_title
        ];
    }

}