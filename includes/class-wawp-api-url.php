<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Wawp_Api_Url {
	/* ================================================================
	 *  CONFIG / FEATURE FLAGS
	 * ------------------------------------------------------------- */
	private const DEFAULT_BASE_URL        = 'https://wawp.net/wp-json/awp/v1/';

	// Seconds (WP expects seconds), but we'll translate to ms in cURL hook.
	private const HTTP_TIMEOUT_DEFAULT    = 12;   // was 10
	private const HTTP_TIMEOUT_FAST       = 0.25; // ~250ms for fire-and-forget
	private const HTTP_REDIRECTIONS       = 0;
	private const RETRIES                 = 0;
	private const RETRY_BACKOFF_MS        = 100;

	private const OPT_ENABLE_TYPING       = true;

	/* Tracking IDs feature flag */
	const OPT_TRACKING_IDS = 'awp_include_tracking_ids';
	const DEF_TRACKING_IDS = 1;

	/* ================================================================
	 *  REQUEST-SCOPED MEMOIZATION
	 * ------------------------------------------------------------- */
	/** @var array<string, array{0:string,1:string}> */
	private static $phone_pair_cache = [];
	/** @var array<string,bool> */
	private static $blocked_cache    = [];
	/** @var bool */
	private static $curl_tuned       = false;

	/** @var string|null */
	private static $client_ip_cache  = null;

	/** @var bool|null */
	private static $tracking_ids_on  = null;

	/* ================================================================
	 *  SHARED INTERNALS
	 * ------------------------------------------------------------- */
	private static function base_url(): string {
		$url = apply_filters( 'wawp_api_base_url', self::DEFAULT_BASE_URL );
		return rtrim( $url, '/' ) . '/';
	}

	public static function tracking_ids_enabled(): bool {
		// cache the option for the whole request
		if ( self::$tracking_ids_on === null ) {
			self::$tracking_ids_on = (bool) get_option( self::OPT_TRACKING_IDS, self::DEF_TRACKING_IDS );
		}
		return self::$tracking_ids_on;
	}

	public static function make_tracking_ids( int $len = 8 ): string {
		if ( ! self::tracking_ids_enabled() ) return '';
		$unique = wp_generate_password( $len, false, false );
		$msg_id = wp_generate_password( $len, false, false );
		return "\n\nUnique ID: {$unique}\nMessage ID: {$msg_id}";
	}

	private static function format_phone_pair( string $raw ) : array {
		if ( isset( self::$phone_pair_cache[ $raw ] ) ) return self::$phone_pair_cache[ $raw ];
		$digits = preg_replace( '/\D/', '', ltrim( $raw, '+' ) );
		return self::$phone_pair_cache[ $raw ] = [ $digits, $digits === '' ? '' : ( $digits . '@c.us' ) ];
	}

	private static function is_blocked( string $digits_only ) : bool {
		if ( $digits_only === '' ) return false;
		if ( isset( self::$blocked_cache[ $digits_only ] ) ) return self::$blocked_cache[ $digits_only ];
		$blocked = false;
		if ( class_exists( 'AWP_Database_Manager' ) ) {
			$dbm = new AWP_Database_Manager();
			$blocked = (bool) $dbm->is_phone_blocked( $digits_only );
		}
		return self::$blocked_cache[ $digits_only ] = $blocked;
	}

	private static function ensure_transport_hooks(): void {
		if ( self::$curl_tuned ) return;
		add_action( 'http_api_curl', [ __CLASS__, 'tune_curl' ], 10, 3 );
		self::$curl_tuned = true;
	}

	/**
	 * Aggressive cURL tuning:
	 * - HTTP/2 over TLS if available
	 * - ms-precision connect/overall timeouts
	 * - no signals (sub-second timeouts work on Unix)
	 * - TCP_NODELAY + keepalive
	 * - Prefer IPv4 if IPv6 is flaky (filterable)
	 */
	public static function tune_curl( $handle, $args, $url ): void {
		if ( ! ( is_resource( $handle ) || $handle instanceof \CurlHandle ) ) return;

		// prefer IPv4 only if site opts into it (avoids rare IPv6 stalls)
		$force_v4 = (bool) apply_filters( 'wawp_api_force_ipv4', false );
		if ( $force_v4 && defined('CURL_IPRESOLVE_V4') ) {
			@curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
		}

		// HTTP/2 if supported (falls back silently)
		if ( defined('CURL_HTTP_VERSION_2TLS') ) {
			@curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS );
		}

		// ms-level timeouts (sync with WP 'timeout' seconds)
		$timeout_s  = isset($args['timeout']) ? (float) $args['timeout'] : self::HTTP_TIMEOUT_DEFAULT;
		$timeout_ms = max( 1, (int) round( $timeout_s * 1000 ) );
		if ( defined('CURLOPT_NOSIGNAL') ) {
			@curl_setopt( $handle, CURLOPT_NOSIGNAL, true );
		}
		if ( defined('CURLOPT_CONNECTTIMEOUT_MS') ) {
			@curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT_MS, min( $timeout_ms, 10000 ) ); // fail fast on connect
		}
		if ( defined('CURLOPT_TIMEOUT_MS') ) {
			@curl_setopt( $handle, CURLOPT_TIMEOUT_MS, $timeout_ms );
		}

		// lower latency over TCP
		@curl_setopt( $handle, CURLOPT_TCP_NODELAY, true );
		if ( defined('CURLOPT_TCP_KEEPALIVE') ) {
			@curl_setopt( $handle, CURLOPT_TCP_KEEPALIVE, 1 );
		}
		if ( defined('CURLOPT_TCP_KEEPIDLE') )   @curl_setopt( $handle, CURLOPT_TCP_KEEPIDLE, 20 );
		if ( defined('CURLOPT_TCP_KEEPINTVL') )  @curl_setopt( $handle, CURLOPT_TCP_KEEPINTVL, 20 );

		// allow all encodings (cURL will add Accept-Encoding and decompress)
		if ( defined('CURLOPT_ACCEPT_ENCODING') ) {
			@curl_setopt( $handle, CURLOPT_ACCEPT_ENCODING, '' );
		}

		// connection reuse hints
		@curl_setopt( $handle, CURLOPT_FORBID_REUSE, false );
		@curl_setopt( $handle, CURLOPT_FRESH_CONNECT, false );
	}

	private static function client_ip(): string {
		if ( self::$client_ip_cache !== null ) return self::$client_ip_cache;
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return self::$client_ip_cache = sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = trim( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
			return self::$client_ip_cache = sanitize_text_field( $ip );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return self::$client_ip_cache = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
		}
		return self::$client_ip_cache = '';
	}

	/**
	 * Low-level HTTP helper (awaits response).
	 */
	private static function do_request( string $route, array $body ) : array {
		self::ensure_transport_hooks();

		$url       = self::base_url() . ltrim( $route, '/' );
		$client_ip = self::client_ip();

		$args = [
			'method'      => 'POST',
			'headers'     => array_filter([
				'Content-Type'     => 'application/json',
				'Accept'           => 'application/json',
				'Connection'       => 'keep-alive',
				'Expect'           => '', // avoid 100-continue
				'X-Forwarded-For'  => $client_ip ?: null,
				'X-Real-IP'        => $client_ip ?: null,
				'User-Agent'       => 'WAWP-Client/1.3 (+WordPress)', // bump UA for cache busting
			]),
			'body'        => wp_json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'timeout'     => self::HTTP_TIMEOUT_DEFAULT,
			'redirection' => self::HTTP_REDIRECTIONS,
			'decompress'  => true,
			'httpversion' => '1.1', // cURL hook will negotiate h2
		];

		$attempts = 0;

		do {
			$attempts++;
			$response  = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				if ( $attempts <= ( 1 + self::RETRIES ) ) { self::usleep_backoff( $attempts ); continue; }
				return [ 'status' => 'error', 'message' => $response->get_error_message(), 'raw_response' => '', 'http_code' => 0 ];
			}

			$http_code   = (int) wp_remote_retrieve_response_code( $response );
			$raw_body    = (string) wp_remote_retrieve_body( $response );
			$contentType = (string) wp_remote_retrieve_header( $response, 'content-type' );

			// Retry on upstream bursty conditions (disabled by default).
			if ( in_array( $http_code, [429, 502, 503, 504], true ) && $attempts <= ( 1 + self::RETRIES ) ) {
				self::usleep_backoff( $attempts );
				continue;
			}

			// Fast path: empty 2xx
			if ( $raw_body === '' && $http_code >= 200 && $http_code < 300 ) {
				return [ 'status' => 'success', 'message' => 'OK (empty response).', 'full_response' => null, 'raw_response' => '', 'http_code' => $http_code ];
			}

			// Decode JSON only when content-type looks like JSON (saves work for HTML/text errors)
			$is_jsonish = stripos($contentType, 'json') !== false || preg_match('/^\s*\{|\[/', $raw_body);
			$decoded    = $is_jsonish ? json_decode( $raw_body, true ) : null;

			if ( $decoded !== null && JSON_ERROR_NONE === json_last_error() ) {
				$base = [ 'raw_response' => $raw_body, 'http_code' => $http_code, 'full_response' => $decoded ];
				if ( $http_code >= 200 && $http_code < 300 ) {
					return $base + [ 'status' => 'success', 'message' => (string) ( $decoded['message'] ?? 'Message sent.' ) ];
				}
				return $base + [ 'status' => 'error', 'message' => (string) ( $decoded['message'] ?? 'awp Connector returned an error.' ) ];
			}

			// Non-JSON fallback
			return [
				'status'        => ($http_code >= 200 && $http_code < 300) ? 'success' : 'error',
				'message'       => ($http_code >= 200 && $http_code < 300) ? 'OK (non-JSON response).' : 'Invalid JSON from awp Connector',
				'full_response' => null,
				'raw_response'  => $raw_body,
				'http_code'     => $http_code,
			];

		} while ( $attempts <= ( 1 + self::RETRIES ) );
	}

	private static function usleep_backoff( int $attempt ): void {
		$base   = (int) ( self::RETRY_BACKOFF_MS * max( 1, $attempt - 1 ) );
		$jitter = random_int( 0, 50 );
		usleep( ( $base + $jitter ) * 1000 );
	}

	/* ================================================================
	 *  PUBLIC HELPERS
	 * ------------------------------------------------------------- */
	public static function send_message(
		string $instance_id,
		string $access_token,
		string $recipient,
		string $message
	) : array {
		[ $digits, $chatId ] = self::format_phone_pair( $recipient );
		if ( $digits === '' || $chatId === '' ) return [ 'status' => 'error', 'message' => 'Invalid phone number.', 'http_code' => 0 ];
		if ( self::is_blocked( $digits ) ) {
			return [ 'status' => 'error', 'message' => 'This number is blocked by the system.', 'full_response' => [ 'status' => 'blocked' ], 'raw_response' => '', 'http_code' => 0 ];
		}

		$message = self::strip_single_braces( $message ) . self::make_tracking_ids();

		if ( self::OPT_ENABLE_TYPING ) self::typing_action( 'startTyping', $instance_id, $access_token, $chatId );

		$resp = self::do_request( 'send', [
			'instance_id'  => $instance_id,
			'access_token' => $access_token,
			'chatId'       => $chatId,
			'message'      => $message,
		] );

		if ( self::OPT_ENABLE_TYPING ) self::typing_action( 'stopTyping', $instance_id, $access_token, $chatId );
		return $resp;
	}

	public static function send_image(
		string $instance_id,
		string $access_token,
		string $recipient,
		string $image_url,
		string $caption = ''
	) : array {
		[ $digits, $chatId ] = self::format_phone_pair( $recipient );
		if ( $digits === '' || $chatId === '' ) return [ 'status' => 'error', 'message' => 'Invalid phone number.', 'http_code' => 0 ];
		if ( self::is_blocked( $digits ) ) {
			return [ 'status' => 'error', 'message' => 'This number is blocked by the system.', 'full_response' => [ 'status' => 'blocked' ], 'raw_response' => '', 'http_code' => 0 ];
		}

		$caption  = self::strip_single_braces( $caption );
		$path     = parse_url( $image_url, PHP_URL_PATH );
		$filename = $path ? basename( $path ) : 'image.jpg';
		$ft       = wp_check_filetype( $filename );
		$mimetype = $ft['type'] ?: 'image/jpeg';

		if ( self::OPT_ENABLE_TYPING ) self::typing_action( 'startTyping', $instance_id, $access_token, $chatId );

		$resp = self::do_request( 'sendImage', [
			'instance_id'  => $instance_id,
			'access_token' => $access_token,
			'chatId'       => $chatId,
			'file'         => [ 'url' => $image_url, 'filename' => $filename, 'mimetype' => $mimetype ],
			'caption'      => $caption,
		] );

		if ( self::OPT_ENABLE_TYPING ) self::typing_action( 'stopTyping', $instance_id, $access_token, $chatId );
		return $resp;
	}

	private static function strip_single_braces( string $txt ) : string {
		return preg_replace( '/\{([^{}]+)\}/', '$1', $txt );
	}

	/**
	 * Fire-and-forget typing action (kept non-blocking, cheaper overhead).
	 */
	private static function typing_action(
		string $action,
		string $instance_id,
		string $access_token,
		string $chatId
	) : void {
		self::ensure_transport_hooks();
		$client_ip = self::client_ip();

		wp_remote_post(
			self::base_url() . ltrim( $action, '/' ),
			[
				'headers'      => array_filter([
					'Content-Type'    => 'application/json',
					'Accept'          => 'application/json',
					'Connection'      => 'keep-alive',
					'Expect'          => '',
					'X-Forwarded-For' => $client_ip ?: null,
					'X-Real-IP'       => $client_ip ?: null,
					'User-Agent'      => 'WAWP-Client/1.3 (+WordPress)',
				]),
				'body'         => wp_json_encode( [
					'instance_id'  => $instance_id,
					'access_token' => $access_token,
					'chatId'       => $chatId,
				], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'blocking'     => false,
				// very small "timeout" still passes through; cURL hook maps to ~250ms
				'timeout'      => self::HTTP_TIMEOUT_FAST,
				'redirection'  => self::HTTP_REDIRECTIONS,
				'decompress'   => true,
				'httpversion'  => '1.1',
			]
		);
	}
}
