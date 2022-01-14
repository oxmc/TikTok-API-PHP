<?php
namespace Sovit\TikTok;

use \CurlHandle;
use \WpOrg\Requests\Session;
use \WpOrg\Requests\Hooks;

/**
 * TikTok API Class
 */
class Api {
    /**
     * API Base url
     * @var String
     */
    const BASE_URL = "https://www.tiktok.com";

    private Session $session;
    /**
     * Config
     *
     * @var Array
     */
    private $_config = [];

    /**
     * Cache Engine
     *
     * @var Object
     */
    private $cacheEngine;

    /**
     * If Cache is enabled
     *
     * @var boolean
     */
    private $cacheEnabled = false;

    private const default_config = [
        "user-agent" => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36',
        "proxy-host" => false,
        "proxy-port" => false,
        "proxy-username" => false,
        "proxy-password" => false,
        "cache-timeout" => 3600
    ];

    private const default_headers = [
        'Referer' => 'https://www.tiktok.com/foryou?lang=en'
    ];

    /**
     * Class Constructor
     *
     * @param array $config API Config
     * @param boolean $cacheEngine
     * @return void
     */
    function __construct($config = array(), $cacheEngine = false) {
        $this->_config = array_merge(self::default_config, ["cookie_file" => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tiktok.txt'], $config);
        $hooks = new Hooks();
        $hooks->register('curl.before_request', function(CurlHandle $ch) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->_config['cookie_file']);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->_config['cookie_file']);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        });

        // Proxy config
        $proxy = false;
        if ($this->_config['proxy-host'] && $this->_config['proxy-port']) {
            // Authed proxy
            if ($this->_config['proxy-username'] && $this->_config['proxy-password']) {
                $proxy = [
                    $this->_config['proxy-host'] . ':' . $this->_config['proxy-port'],
                    $this->_config['proxy-username'],
                    $this->_config['proxy-password'],
                ];
            // Unauth proxy
            } else {
                $proxy = $this->_config['proxy-host'] . ':' . $this->_config['proxy-port'];
            }
        }

        // Session handler for HTTP requests
        $session_options = [
            'useragent' => $this->_config['user-agent'],
            'proxy' => $proxy,
            'hooks' => $hooks,
            'timeout' => 15,
            'connect_timeout' => 15
        ];

        $this->session = new Session(self::BASE_URL, self::default_headers, [], $session_options);
        if ($cacheEngine) {
            $this->cacheEnabled = true;
            $this->cacheEngine = $cacheEngine;
        }
    }

    /**
     * Get Challenge function
     * Accepts challenge name and returns challenge detail object or false on failure
     *
     * @param string $challenge
     * @return object
     */
    public function getChallenge($challenge) {
        $cacheKey = 'challenge-' . $challenge;
        if ($this->cacheEnabled) {
            if ($this->cacheEngine->get($cacheKey)) {
                return $this->cacheEngine->get($cacheKey);
            }
        }
        $challenge = urlencode($challenge);
        $request = $this->remote_call("/node/share/tag/{$challenge}");
        $result = Helper::setMeta($request->http_success, $request->code, $request->data->statusCode);
        if ($result['meta']->success) {
            $result = array_merge($result, [
                'info' => $request->data->challengeInfo
            ]);
            if ($this->cacheEnabled) {
                $this->cacheEngine->set($cacheKey, $result, $this->_config['cache-timeout']);
            }
        }
        return (object) $result;
    }
    /**
     * Get Challenge Feed
     * Accepts challenge name and returns challenge feed object or false on faliure
     *
     * @param string $challenge_name
     * @param integer $maxCursor
     * @return object
     */
    public function getChallengeFeed(string $challenge_name, $maxCursor = 0) {
        $cacheKey = 'challenge-' . $challenge_name . '-' . $maxCursor;
        if ($this->cacheEnabled) {
            if ($this->cacheEngine->get($cacheKey)) {
                return $this->cacheEngine->get($cacheKey);
            }
        }
        $challenge_result = $this->getChallenge($challenge_name);
        if ($challenge_result->meta->success) {
            $param = [
                "type"      => 3,
                "secUid"    => "",
                "id"        => $challenge_result->info->challenge->id,
                "count"     => 30,
                "minCursor" => 0,
                "maxCursor" => $maxCursor,
                "shareUid"  => "",
                "lang"      => "",
                "verifyFp"  => "",
            ];
            $request = $this->remote_call("/node/video/feed", 'GET', true, $param);
            $result = Helper::setMeta($request->http_success, $request->code, $request->data->statusCode);
            if ($result['meta']->success) {
                $result = array_merge($result, [
                    "info"       => (object) [
                        'type'   => 'challenge',
                        'detail' => (object) [
                            'challenge' => $challenge_result->info->challenge,
                            'stats' => $challenge_result->info->stats
                        ]
                    ],
                    "items"      => Helper::parseData($request->data->body->itemListData),
                    "hasMore"    => $request->data->body->hasMore,
                    "minCursor"  => $request->data->body->minCursor,
                    "maxCursor"  => $request->data->body->maxCursor
                ]);
                if ($this->cacheEnabled) {
                    $this->cacheEngine->set($cacheKey, $result, $this->_config['cache-timeout']);
                }
                return (object) $result;
            }
        }
        return (object) $challenge_result;
    }
    /**
     * Trending Video Feed
     * Accepts $maxCursor offset and returns trending video feed object or false on failure
     *
     * @param integer $maxCursor
     * @return object
     */
    public function getTrendingFeed($maxCursor = 0)
    {
        $params = [
            "type"      => 5,
            "secUid"    => "",
            "id"        => 1,
            "count"     => 30,
            "minCursor" => 0,
            "maxCursor" => $maxCursor,
            "shareUid"  => "",
            "lang"      => 'en',
            "verifyFp"  => Helper::verify_fp()
        ];
        $cacheKey = 'trending-' . $maxCursor;
        if ($this->cacheEnabled) {
            if ($this->cacheEngine->get($cacheKey)) {
                return $this->cacheEngine->get($cacheKey);
            }
        }
        $request = $this->remote_call("/node/video/feed", 'GET', true, $params);
        $result = Helper::setMeta($request->http_success, $request->code, $request->data->statusCode);
        if ($result['meta']->success) {
            $result = array_merge($result, [
                "info"       => (object) [
                    'type'   => 'trending',
                    'detail' => false,
                ],
                "items"      => Helper::parseData($request->data->body->itemListData),
                "hasMore"    => $request->data->body->hasMore,
                "minCursor"  => $request->data->body->minCursor,
                "maxCursor"  => $request->data->body->maxCursor
            ]);
        }
        if ($this->cacheEnabled) {
            $this->cacheEngine->set($cacheKey, $result, $this->_config['cache-timeout']);
        }
        return (object) $result;
    }
    /**
     * Get User detail
     * Accepts tiktok username and returns user detail object or false on failure
     *
     * @param string $username
     * @return object
     */
    public function getUser(string $username): object {
        $cacheKey = 'user-' . $username;
        if ($this->cacheEnabled) {
            if ($this->cacheEngine->get($cacheKey)) {
                return $this->cacheEngine->get($cacheKey);
            }
        }
        $username = urlencode($username);
        $request = $this->remote_call("/@{$username}", 'GET', false);
        $result = Helper::setMeta($request->http_success, $request->code, null);
        if ($result['meta']->success) {
            $json_string = Helper::string_between($request->data, "window['SIGI_STATE']=", ";window['SIGI_RETRY']=");
            $jsonData = json_decode($json_string);
            if (isset($jsonData->UserModule)) {
                $result = array_merge($result, [
                    'userinfo' => (object) [
                        'user' => $jsonData->UserModule->users->{$username},
                        'stats' => $jsonData->UserModule->stats->{$username},
                    ]
                ]);
            }
            if ($this->cacheEnabled) {
                $this->cacheEngine->set($cacheKey, $result, $this->_config['cache-timeout']);
            }
        }
        return (object) $result;
    }
    /**
     * Get user feed
     * Accepts username and $maxCursor pagination offset and returns user video feed object or false on failure
     *
     * @param string $username
     * @param integer $maxCursor
     * @return object
     */
    public function getUserFeed(string $username, $maxCursor = 0): object {
        if (empty($username)) {
            throw new \Exception("Invalid Username");
        }
        $cacheKey = 'user-feed-' . $username . '-' . $maxCursor;
        if ($this->cacheEnabled) {
            if ($this->cacheEngine->get($cacheKey)) {
                return $this->cacheEngine->get($cacheKey);
            }
        }
        $user_result = $this->getUser($username);
        if ($user_result->meta->success) {
            $userinfo = $user_result->userinfo;
            $param = [
                "type"      => 1,
                "secUid"    => "",
                "id"        => $userinfo->user->id,
                "count"     => 30,
                "minCursor" => "0",
                "maxCursor" => 0,
                "shareUid"  => "",
                "lang"      => "",
                "verifyFp"  => Helper::verify_fp()
            ];
            $request = $this->remote_call("/node/video/feed", 'GET', true, $param);
            $result = Helper::setMeta($request->http_success, $request->code, $request->data->statusCode);
            if ($result['meta']->success) {
                $result = array_merge($result, [
                    "info"       => (object) [
                        'type'   => 'user',
                        'detail' => $userinfo,
                    ],
                    "items"      => Helper::parseData($request->data->body->itemListData),
                    "hasMore"    => $request->data->body->hasMore,
                    "minCursor"  => $request->data->body->minCursor,
                    "maxCursor"  => $request->data->body->maxCursor,
                ]);
                if ($this->cacheEnabled) {
                    $this->cacheEngine->set($cacheKey, $result, $this->_config['cache-timeout']);
                }
                return (object) $result;
            }
        }
        return (object) $user_result;
    }
    /**
     * Get video by video id
     * Accept video ID and returns video detail object or false on failure
     *
     * @param string $video_id
     * @return object
     */
    public function getVideoByID(string $video_id): object {
        $url = is_numeric($video_id) ? 'https://m.tiktok.com/v/' . $video_id . '.html' : 'https://vm.tiktok.com/' . $video_id;
        return $this->getVideoByUrl($url);
    }
    /**
     * Get Video By URL
     * Accepts tiktok video url and returns video detail object or false on failure
     *
     * @param string $url
     * @return object
     */
    public function getVideoByUrl(string $url): object {
        // Disable base path first
        //$this->session->url = null;
        $cacheKey = Helper::normalize($url);
        if ($this->cacheEnabled) {
            if ($this->cacheEngine->get($cacheKey)) {
                return $this->cacheEngine->get($cacheKey);
            }
        }
        if (!preg_match("/https?:\/\/([^\.]+)?\.tiktok\.com/", $url)) {
            throw new \Exception("Invalid VIDEO URL");
        }

        $request = $this->remote_call($url, 'GET', false);
        $result = Helper::setMeta($request->http_success, $request->code, null);
        if ($result['meta']->success) {
            $json_string = Helper::string_between($request->data, "window['SIGI_STATE']=", ";window['SIGI_RETRY']=");
            $jsonData = json_decode($json_string);
            if (isset($jsonData->ItemModule, $jsonData->ItemList, $jsonData->UserModule)) {
                $id = $jsonData->ItemList->video->keyword;
                $item = $jsonData->ItemModule->{$id};
                $username = $item->author;
                $result = array_merge($result, [
                    'info'       => (object) [
                        'type'   => 'video',
                        'detail' => (object) [
                            "url" => $url,
                            "user" => $jsonData->UserModule->users->{$username},
                            "stats" => $item->stats
                        ],
                    ],
                    "items"      => [$item],
                    "hasMore"    => false,
                    "minCursor"  => '0',
                    "maxCursor"  => '0'
                ]);
                if ($this->cacheEnabled) {
                    $this->cacheEngine->set($cacheKey, $result, $this->_config['cache-timeout']);
                }
                return (object) $result;
            }
        }
        return (object) $result;
    }
    /**
     * Make remote call
     * Private method that will make remote HTTP requests, parse result as JSON if $isJson is set to true
     * returns false on failure
     *
     * @param string $url
     * @param boolean $isJson
     * @return object
     */
    private function remote_call(string $endpoint, $method = 'GET', $isJson = true, $params = [], $headers = []): object {
        try {
            $request = $this->session->request($endpoint, $headers, $params, $method);
            return (object) [
                'http_success' => $request->success,
                'code' => $request->status_code,
                'data' => $isJson ? $request->decode_body(false) : $request->body
            ];
        } catch (\WpOrg\Requests\Exception $error) {
            return (object) [
                'http_success' => false,
                'code' => 504,
                'data' => null
            ];
        }
    }
}
