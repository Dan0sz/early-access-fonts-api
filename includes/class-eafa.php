<?php
/* * * * * * * * * * * * * * * * * * * * * *
 * @author   : Daan van den Bergh
 * @url      : https://ffw.press/
 * @copyright: (c) Daan van den Bergh
 * @license  : GPL2v2 or later
 * * * * * * * * * * * * * * * * * * * * * */

defined('ABSPATH') || exit;

class EAFA
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        $eaf_api = new EAFA_API();

        $eaf_api->register_routes();
    }
}
