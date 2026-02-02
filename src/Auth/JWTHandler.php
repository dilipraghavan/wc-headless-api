<?php
/**
 * JWT Handler class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Exception;

/**
 * Handles JWT token generation and validation.
 */
class JWTHandler {

	/**
	 * JWT algorithm.
	 *
	 * @var string
	 */
	private const ALGORITHM = 'HS256';

	/**
	 * Token type: access.
	 *
	 * @var string
	 */
	public const TOKEN_TYPE_ACCESS = 'access';

	/**
	 * Token type: refresh.
	 *
	 * @var string
	 */
	public const TOKEN_TYPE_REFRESH = 'refresh';

	/**
	 * JWT secret key.
	 *
	 * @var string
	 */
	private string $secret;

	/**
	 * Access token expiration in seconds.
	 *
	 * @var int
	 */
	private int $access_expiration;

	/**
	 * Refresh token expiration in seconds.
	 *
	 * @var int
	 */
	private int $refresh_expiration;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->secret = $this->get_secret();
		$this->load_settings();
	}

	/**
	 * Get JWT secret from options.
	 *
	 * @return string
	 */
	private function get_secret(): string {
		$secret = get_option( 'wcha_jwt_secret' );

		if ( ! $secret ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( 'wcha_jwt_secret', $secret );
		}

		return $secret;
	}

	/**
	 * Load settings from options.
	 *
	 * @return void
	 */
	private function load_settings(): void {
		$settings = get_option( 'wcha_settings', array() );

		/**
		 * Filter JWT access token expiration.
		 *
		 * @param int $expiration Expiration in seconds.
		 */
		$this->access_expiration = apply_filters(
			'wcha_jwt_expiration',
			$settings['jwt_expiration'] ?? 3600
		);

		$this->refresh_expiration = $settings['refresh_expiration'] ?? 604800;
	}

	/**
	 * Generate access token for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function generate_access_token( int $user_id ): string {
		return $this->generate_token( $user_id, self::TOKEN_TYPE_ACCESS, $this->access_expiration );
	}

	/**
	 * Generate refresh token for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function generate_refresh_token( int $user_id ): string {
		return $this->generate_token( $user_id, self::TOKEN_TYPE_REFRESH, $this->refresh_expiration );
	}

	/**
	 * Generate a JWT token.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $type       Token type (access or refresh).
	 * @param int    $expiration Expiration in seconds.
	 * @return string
	 */
	private function generate_token( int $user_id, string $type, int $expiration ): string {
		$issued_at  = time();
		$expires_at = $issued_at + $expiration;

		$payload = array(
			'iss'  => get_site_url(),
			'iat'  => $issued_at,
			'exp'  => $expires_at,
			'sub'  => $user_id,
			'type' => $type,
			'jti'  => wp_generate_uuid4(),
		);

		return JWT::encode( $payload, $this->secret, self::ALGORITHM );
	}

	/**
	 * Validate and decode a token.
	 *
	 * @param string $token         JWT token.
	 * @param string $expected_type Expected token type.
	 * @return array{valid: bool, user_id?: int, error?: string, expired?: bool}
	 */
	public function validate_token( string $token, string $expected_type = self::TOKEN_TYPE_ACCESS ): array {
		try {
			$decoded = JWT::decode( $token, new Key( $this->secret, self::ALGORITHM ) );

			// Verify issuer.
			if ( $decoded->iss !== get_site_url() ) {
				return array(
					'valid' => false,
					'error' => 'Invalid token issuer.',
				);
			}

			// Verify token type.
			if ( $decoded->type !== $expected_type ) {
				return array(
					'valid' => false,
					'error' => 'Invalid token type.',
				);
			}

			// Verify user exists.
			$user = get_user_by( 'ID', $decoded->sub );
			if ( ! $user ) {
				return array(
					'valid' => false,
					'error' => 'User not found.',
				);
			}

			return array(
				'valid'   => true,
				'user_id' => (int) $decoded->sub,
			);

		} catch ( ExpiredException $e ) {
			return array(
				'valid'   => false,
				'error'   => 'Token has expired.',
				'expired' => true,
			);
		} catch ( Exception $e ) {
			return array(
				'valid' => false,
				'error' => 'Invalid token.',
			);
		}
	}

	/**
	 * Get token from Authorization header.
	 *
	 * @return string|null
	 */
	public function get_token_from_header(): ?string {
		$auth_header = $this->get_authorization_header();

		if ( empty( $auth_header ) ) {
			return null;
		}

		// Check for Bearer token.
		if ( preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Get Authorization header.
	 *
	 * @return string|null
	 */
	private function get_authorization_header(): ?string {
		// Apache and Nginx with mod_rewrite.
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		// Nginx with php-fpm.
		if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		// Apache with CGI/FastCGI.
		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
			if ( isset( $headers['Authorization'] ) ) {
				return $headers['Authorization'];
			}
			// Some servers lowercase headers.
			if ( isset( $headers['authorization'] ) ) {
				return $headers['authorization'];
			}
		}

		return null;
	}

	/**
	 * Get access token expiration.
	 *
	 * @return int
	 */
	public function get_access_expiration(): int {
		return $this->access_expiration;
	}

	/**
	 * Get refresh token expiration.
	 *
	 * @return int
	 */
	public function get_refresh_expiration(): int {
		return $this->refresh_expiration;
	}
}
