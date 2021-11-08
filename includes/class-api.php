<?php
/* * * * * * * * * * * * * * * * * * * * * *
 * @author   : Daan van den Bergh
 * @url      : https://ffw.press/
 * @copyright: (c) Daan van den Bergh
 * @license  : GPL2v2 or later
 * * * * * * * * * * * * * * * * * * * * * */

defined('ABSPATH') || exit;

class EAFA_API extends WP_REST_Controller
{
    /**
     * Array consists of Endpoint Name => Function Name.
     */
    const EAF_API_ENDPOINTS = [
        'early-access' => 'early_access',
        'icons'        => 'icons'
    ];

    /**
     * Array of different user agents, to make sure we capture all available file types of an icon.
     */
    const MI_USER_AGENTS = [
        'eot'   => 'Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0; GTB7.4; InfoPath.2; SV1; .NET CLR 3.3.69573; WOW64; en-US)',
        'woff2' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:93.0) Gecko/20100101 Firefox/93.0',
        'woff'  => 'Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5355d Safari/8536.25',
        'ttf'   => 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_8; de-at) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1',
        'svg'   => 'Mozilla/5.0(iPad; U; CPU iPhone OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B314 Safari/531.21.10gin_lib.cc'
    ];

    const EAF_STYLESHEET_LABEL     = 'stylesheet';
    const EAF_VER_LABEL            = 'ver';
    const EAF_GOOGLE_API_URL       = 'https://fonts.googleapis.com/earlyaccess/';
    const MI_GOOGLE_API_URL        = 'https://fonts.googleapis.com/icon?family=';
    const EAF_CACHED_REQUEST_LABEL = 'eaf_api_cached_request_%s';
    const MI_CACHED_REQUEST_LABEL  = 'icon_api_cached_request_%s';

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

        $stylesheet_contents = $this->fetch_stylesheet_contents(self::EAF_GOOGLE_API_URL . $this->stylesheet . '?ver=' . $this->version);
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
            'id'           => strtolower(str_replace(' ', '-', $font_family)),
            'early_access' => true,
            'family'       => $font_family,
            'variants'     => array_values($variants), // Reset the array keys, otherwise it'll become a StdClass instead of an array.
            'subsets'      => []
        ];

        update_option(sprintf(self::EAF_CACHED_REQUEST_LABEL, $this->stylesheet), $api_object);

        return $api_object;
    }

    /**
     * 
     */
    public function icons()
    {
        $cached_request = $this->fetch_cached_request();

        if ($cached_request) {
            return $cached_request;
        }

        $this->stylesheet = ucwords(str_replace(['-', '+'], ' ', $this->stylesheet));

        foreach (self::MI_USER_AGENTS as $file_type => $user_agent) {
            $stylesheet_contents[$file_type] = $this->fetch_stylesheet_contents(self::MI_GOOGLE_API_URL . str_replace(' ', '+', $this->stylesheet) . '&ver=' . $this->version, ['user-agent' => $user_agent]);
        }

        foreach ($stylesheet_contents as $contents) {
            $stylesheet_arrays[] = $this->build_array_from_stylesheet($contents);
        }

        foreach ($stylesheet_arrays as $array) {
            $variants[] = $this->build_variants_array($array);
        }

        $merged_variants = [];

        foreach ($variants as $variant) {
            $merged_variants = array_merge($merged_variants, reset($variant));
        }

        $variants    = [];
        $variants[0] = $merged_variants;

        $font_family = array_column($variants, 'fontFamily')[0] ?? '';

        if ($font_family) {
            $font_family = trim($font_family, '\'\"');
        }

        if (!$font_family) {
            return wp_send_json_error([]); // TODO: Include status code?
        }

        $api_object = [
            'id'       => strtolower(str_replace(' ', '-', $font_family)),
            'family'   => $font_family,
            'variants' => array_values($variants),
            'subsets'  => []
        ];

        update_option(sprintf(self::MI_CACHED_REQUEST_LABEL, $this->stylesheet), $api_object);

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
    private function fetch_stylesheet_contents($url, $params)
    {
        $params = array_merge(['sslverify' => false], $params);

        $response = wp_remote_get($url, $params);

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
