<?php
/* * * * * * * * * * * * * * * * * * * * * *
 * @author   : Daan van den Bergh
 * @url      : https://ffw.press/
 * @copyright: (c) Daan van den Bergh
 * @license  : GPL2v2 or later
 * * * * * * * * * * * * * * * * * * * * * */

defined('ABSPATH') || exit;

class EAFA_API_Fonts extends WP_REST_Controller
{
    /**
     * Array consists of Endpoint Name => Function Name.
     */
    const EAF_API_ENDPOINTS = [
        'early-access' => 'early_access'
    ];

    const EAF_STYLESHEET_LABEL     = 'stylesheet';
    const EAF_VER_LABEL            = 'ver';
    const EAF_GOOGLE_API_URL       = 'https://fonts.googleapis.com/earlyaccess/';
    const EAF_CACHED_REQUEST_LABEL = 'eaf_api_cached_request_%s';

    /** @var string $namespace */
    protected $namespace = 'omgf/v1';

    /** @var string $rest_base */
    protected $rest_base = '/fonts';

    /** @var string $url */
    private $stylesheet = '';

    /** @var string $version */
    private $version = '';

    /**
     * @return void 
     */
    public function __construct()
    {
        $this->namespace  = 'omgf/v1';
        $this->rest_base  = '/fonts';
    }

    /**
     * @return void 
     */
    public function register_routes()
    {
        foreach (self::EAF_API_ENDPOINTS as $endpoint => $function) {
            register_rest_route(
                $this->namespace,
                $this->rest_base . '/' . $endpoint,
                [
                    [
                        'methods'             => 'GET',
                        'callback'            => [$this, $function],
                        'permission_callback' => [$this, 'permissions_check']
                    ],
                    'schema' => null
                ]
            );
        }
    }

    /**
     * This is an open API. Always allow access. Security should be handled by the API.
     * 
     * @return true 
     */
    public function permissions_check()
    {
        $_GET             = filter_input_array(INPUT_GET, [self::EAF_STYLESHEET_LABEL => FILTER_SANITIZE_STRING, self::EAF_VER_LABEL => FILTER_SANITIZE_STRING]);
        $this->stylesheet = $_GET[self::EAF_STYLESHEET_LABEL];
        $this->version    = $_GET[self::EAF_VER_LABEL];

        return isset($this->stylesheet);
    }

    /**
     * 
     */
    public function early_access()
    {
        $cached_request = $this->fetch_cached_request();

        if ($cached_request) {
            return $cached_request;
        }

        $stylesheet_contents = $this->fetch_stylesheet_contents();
        $stylesheet_array    = $this->build_array_from_stylesheet($stylesheet_contents);
        $variants            = $this->build_variants_array($stylesheet_array);

        $font_family = array_column($variants, 'fontFamily')[0] ?? '';

        if ($font_family) {
            $font_family = trim($font_family, '\'\"');
        }

        if (!$font_family) {
            return wp_send_json_error([]); // TODO: Include status code?
        }

        $api_object = [
            'id' => strtolower(str_replace(' ', '-', $font_family)),
            'early_access' => true,
            'family' => $font_family,
            'variants' => array_values($variants), // Reset the array keys, otherwise it'll become an StdClass instead of an array.
            'subsets' => []
        ];

        update_option(sprintf(self::EAF_CACHED_REQUEST_LABEL, $this->stylesheet), $api_object);

        return $api_object;
    }

    /**
     * @return array|false
     */
    private function fetch_cached_request()
    {
        return get_option(sprintf(self::EAF_CACHED_REQUEST_LABEL, $this->stylesheet));
    }

    /**
     * @return mixed 
     */
    private function fetch_stylesheet_contents()
    {
        $response = wp_remote_get(
            self::EAF_GOOGLE_API_URL . $this->stylesheet . '?ver=' . $this->version,
            [
                'sslverify' => false
            ]
        );

        if (is_wp_error($response)) {
            return json_decode(wp_remote_retrieve_body($response));
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * @param string $contents 
     * @return array|false 
     */
    private function build_array_from_stylesheet($contents)
    {
        return preg_split('~(?>@font-face\s*{\s*|\G(?!\A))(\S+)\s*:\s*([^;]+);\s*~', $contents, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param array $array 
     * @return array 
     */
    private function build_variants_array($array)
    {
        $i        = 0;
        $variants = [];

        foreach ($array as $key => &$value) {
            // Check end of block OR check if match is comment block.
            if (strpos($value, "}") === 0 || strpos($value, "/*") === 0) {
                $i++;

                continue;
            }

            // Convert snake-case to camelCase
            $value = lcfirst(str_replace('-', '', ucwords($value, '-')));

            if ($value == 'src') {
                preg_match_all('/url\(([^\)]+)\)/', $array[$key + 1], $urls);

                if (!$urls) {
                    continue; // TODO: Throw error.
                }

                array_shift($urls);
                $urls = array_merge(...$urls);

                foreach ($urls as $url) {
                    $extension                = pathinfo($url, PATHINFO_EXTENSION);
                    $variants[$i][$extension] = $url;
                }

                unset($array[$key + 1]);

                continue;
            }

            $variants[$i][$value] = $array[$key + 1];
            unset($array[$key + 1]);
        }

        foreach ($variants as &$variant) {
            $font_style    = $variant['fontStyle'] == 'normal' ? '' : $variant['fontStyle'];
            $variant['id'] = $variant['fontWeight'] . $font_style;
        }

        return $variants;
    }
}
