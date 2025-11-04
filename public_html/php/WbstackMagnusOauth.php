<?php

class WbstackMagnusOauth {

    public const platformIngressHostAndPort = "platform-nginx.default.svc.cluster.local:8080";
    public const platformApiBackendHost = "api-app-backend.default.svc.cluster.local";

    /**
     * @var bool
     */
    private static $isSetup;

    /**
     * @var string
     */
    private static $consumerName;

    /**
     * @var string
     */
    private static $consumerVersion;

    /**
     * @var array
     */
    private static $grants;

    /**
     * @var string
     */
    private static $callbackUrlTail;

    public static function getVersionedMediawikiBackendHost() {
        $host = getenv('PLATFORM_MW_BACKEND_HOST'); // default fallback

        $requestUrl = 'http://'
            .self::platformApiBackendHost
            .'/backend/ingress/getWikiVersionForDomain?domain='
            .$_SERVER['SERVER_NAME'];

        $headers = get_headers($requestUrl, true);

        if (is_array($headers)) {
            if (isset($headers['x-version'])) {
                $wikiVersion = $headers['x-version'];

                // mapping like in https://github.com/wmde/wbaas-deploy/blob/main/k8s/helmfile/env/local/platform-nginx.nginx.conf#L4
                // TODO https://phabricator.wikimedia.org/T409078
                switch ($wikiVersion) {
                    case 'mw1.39-wbs1':
                        $host = 'mediawiki-139-app-backend.default.svc.cluster.local';
                        break;
                    case 'mw1.43-wbs1':
                        $host = 'mediawiki-143-app-backend.default.svc.cluster.local';
                        break;
                }
            }
        }

        return $host;
    }

    /**
     * Calling this means that oauth.php will just do the right thing in terms of wbstack.
     * Not calling this before MW_OAuth is used in a magnus tool will result in an error.
     *
     * @param string $consumerName Example: "Widar"
     * @param string $consumerVersion Example: "1.0.1"
     * @param array $grants Example: ['highvolume','editpage']
     * @param string $callbackUrlTail Example: "/tools/widar/index.php"
     */
    public static function setOauthDetails(
        string $consumerName,
        string $consumerVersion,
        array $grants,
        string $callbackUrlTail
    )
    {
        self::$isSetup = true;
        self::$consumerName = $consumerName;
        self::$consumerVersion = $consumerVersion;
        self::$grants = $grants;
        self::$callbackUrlTail = $callbackUrlTail;
    }

    /**
     * Can be used in place of a real parse ini file call, to actually get the details via
     * an API call rather than a file on disk...
     *
     * Parse a configuration file
     * @link https://php.net/manual/en/function.parse-ini-file.php
     * @param string $filename <p>
     * The filename of the ini file being parsed.
     * </p>
     * @param bool $process_sections [optional] <p>
     * By setting the process_sections
     * parameter to true, you get a multidimensional array, with
     * the section names and settings included. The default
     * for process_sections is false
     * </p>
     * @param int $scanner_mode [optional] <p>
     * Can either be INI_SCANNER_NORMAL (default) or
     * INI_SCANNER_RAW. If INI_SCANNER_RAW
     * is supplied, then option values will not be parsed.
     * </p>
     * <p>
     * As of PHP 5.6.1 can also be specified as <strong><code>INI_SCANNER_TYPED</code></strong>.
     * In this mode boolean, null and integer types are preserved when possible.
     * String values <em>"true"</em>, <em>"on"</em> and <em>"yes"</em>
     * are converted to <b>TRUE</b>. <em>"false"</em>, <em>"off"</em>, <em>"no"</em>
     * and <em>"none"</em> are considered <b>FALSE</b>. <em>"null"</em> is converted to <b>NULL</b>
     * in typed mode. Also, all numeric strings are converted to integer type if it is possible.
     * </p>
     * @return array|bool The settings are returned as an associative array on success,
     * and false on failure.
     * @since 4.0
     * @since 5.0
     */
    public static function parse_ini_file($filename) {
        if(!self::$isSetup) {
            die('oauth connection not correctly configured / setup');
        }
        if(substr($filename,-9, 9) !== 'oauth.ini') {
            die('unexpected file load');
        }

        $headers = [
            'Host: ' . $_SERVER['SERVER_NAME'],
        ];

        $apiParams = [
            'consumerName' => self::$consumerName,
            'consumerVersion' => self::$consumerVersion,
            'grants' => implode( '|', self::$grants ),
            'callbackUrlTail' => self::$callbackUrlTail,
        ];

        $client = curl_init('http://' . self::getVersionedMediawikiBackendHost() . '/w/api.php?action=wbstackPlatformOauthGet&format=json');
        curl_setopt($client, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $client, CURLOPT_USERAGENT, "WBStack - " .  self::$consumerName . " - WbstackMagnusOauth::parse_ini_file" );
        curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($client, CURLOPT_POST, true);
        curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($apiParams));
        $response = curl_exec($client);
        $response = json_decode( $response, true );
        if(!$response || !$response['wbstackPlatformOauthGet']['success']) {
            return [];
        }
        $response = $response['wbstackPlatformOauthGet']['data'];
        if ( $response !== [] && $response !== null ) {
            // Fake settings parsed from fake INI file (api call)
            return [
                'agent' => $response['agent'],
                'consumerKey' => $response['consumerKey'],
                'consumerSecret' => $response['consumerSecret'],
            ];
        } else {
            //TODO log
        }
        // Per the method do we should return false here... BUT oauth.php doesnt have very good handling for this.
        // so instead die...
        //return false;
        die( "Failed to get oauth data" );
    }

    /**
     * Used to get the parameters for MW_OAUTH by magnus appropriate for the request that is currently
     * being processed.
     * @param string $toolName Example: "widar"
     * @param string $toolUrlTail Example: "/tools/widar"
     * @return array
     */
    public static function getOauthParams(
        string $toolName,
        string $toolUrlTail
    ): array
    {
        $site = self::getSite( $toolUrlTail );
        $params = [
            'tool' => $toolName,
            'language' => $site->oauth->language,
            'project' => $site->oauth->project,
            'ini_file' => $site->oauth->ini_file,
            'mwOAuthUrl' => $site->oauth->mwOAuthUrl,
            'mwOAuthIW' => $site->oauth->mwOAuthIW,
            'apiUrl' => $site->api,
        ];
        if (isset($site->oauth->publicMwOAuthUrl)) $params['publicMwOAuthUrl'] = $site->oauth->publicMwOAuthUrl;
        return $params;
    }

    /**
     * @param string $toolUrlTail Example: "/tools/widar"
     * @return mixed
     */
    public static function getSite(
        string $toolUrlTail
    ) {
        // XXX: this same logic is in quickstatements.php and platform api WikiController backend
        $domain = $_SERVER['SERVER_NAME'];

        $wbRoot = $domain;
        $toolRoot = $domain . $toolUrlTail;

        // Directly for config
        $publicMwOAuthUrl = 'https://' . $wbRoot . '/w/index.php?title=Special:OAuth';
        $mwOAuthUrl = 'https://' . $wbRoot . '/w/index.php?title=Special:OAuth';
        $wbPublicHostAndPort = $wbRoot;
        $wbApi = 'https://' . $wbRoot . '/w/api.php'; // TODO this could use the internal network
        $wbPageBase = 'https://' . $wbRoot . '/wiki/';
        $toolbase = 'https://' . $toolRoot;
        $entityBase = 'https://' . $wbRoot . '/entity/';

        $site = [
            'oauth' => [
                'language' => 'en',
                'project' => $domain,
                'ini_file' => '/var/www/html/someDirDoesThisMatter/oauth.ini',
                'publicMwOAuthUrl' => $publicMwOAuthUrl,
                'mwOAuthUrl' => $mwOAuthUrl,
                'mwOAuthIW' => 'mw', // TODO WTF? :(
            ],
            'server' => $wbPublicHostAndPort,
            'api' => $wbApi,
            'pageBase' => $wbPageBase,
            'entityBase' => $entityBase,
            'toolBase' => $toolbase,
            'types' => [
                "P" => [ "type" => 'property', 'ns' => 122, 'ns_prefix' => 'Property:' ],
                "Q" => [ "type" => 'item', 'ns' => 120, 'ns_prefix' => 'Item:' ],
                // TODO should include lexeme when enabled?
            ],
        ];
        $site = json_decode(json_encode($site));
        return $site;
    }

	/**
	 * Set the HTTP Headers for the curl handle
	 *
	 * Sets the HOST parameter when internally talking to wbstack platform ingress
	 *
	 */
	public static function setCurlHttpHeaders( $curlHandle, $headers = [] ) {
        if( !empty($headers) ) {
			curl_setopt( $curlHandle, CURLOPT_HTTPHEADER, $headers );
		}
	}
}
