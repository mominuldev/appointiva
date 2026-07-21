<?php
/**
 * Simple success/failure result value object, used instead of exceptions
 * for expected, user-facing failure paths (validation, unavailable slots).
 *
 * @package Appointiva
 */

namespace Appointiva\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Result {

	private function __construct(
		public readonly bool $ok,
		public readonly mixed $data = null,
		public readonly string $error_code = '',
		public readonly string $error_message = ''
	) {}

	public static function success( mixed $data = null ): self {
		return new self( true, $data );
	}

	public static function failure( string $code, string $message ): self {
		return new self( false, null, $code, $message );
	}
}
