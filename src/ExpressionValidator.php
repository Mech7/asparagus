<?php

namespace Asparagus;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Package-private class to validate expressions like variables and IRIs.
 *
 * @license GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class ExpressionValidator {

	/**
	 * Accept all expressions
	 */
	const VALIDATE_ALL = 64;

	/**
	 * Accept variables
	 */
	const VALIDATE_VARIABLE = 1;

	/**
	 * Accept IRIs
	 */
	const VALIDATE_IRI = 2;

	/**
	 * Accept prefixed IRIs
	 */
	const VALIDATE_PREFIXED_IRI = 4;

	/**
	 * Accept prefixes
	 */
	const VALIDATE_PREFIX = 8;

	/**
	 * Accept functions
	 */
	const VALIDATE_FUNCTION = 16;

	/**
	 * Accept functions with variable assignments
	 */
	const VALIDATE_FUNCTION_AS = 32;

	/**
	 * @var string[]
	 */
	private static $functions = array(
		'COUNT', 'SUM', 'MIN', 'MAX', 'AVG', 'SAMPLE', 'GROUP_CONCAT', 'STR',
		'LANG', 'LANGMATCHES', 'DATATYPE', 'BOUND', 'IRI', 'URI', 'BNODE',
		'RAND', 'ABS', 'CEIL', 'FLOOR', 'ROUND', 'CONCAT', 'STRLEN', 'UCASE',
		'LCASE', 'ENCODE_FOR_URI', 'CONTAINS', 'STRSTARTS', 'STRENDS',
		'STRBEFORE', 'STRAFTER', 'YEAR', 'MONTH', 'DAY', 'HOURS', 'MINUTES',
		'SECONDS', 'TIMEZONE', 'TZ', 'NOW', 'UUID', 'STRUUID', 'MD5', 'SHA1',
		'SHA256', 'SHA384', 'SHA512', 'COALESCE', 'IF', 'STRLANG', 'STRDT',
		'sameTerm', 'isIRI', 'isURI', 'isBLANK', 'isLITERAL', 'isNUMERIC',
		'REGEX', 'SUBSTR', 'REPLACE', 'EXISTS', 'NOT EXISTS'
	);

	/**
	 * @var string[]
	 */
	private $variables = array();

	/**
	 * @var string[]
	 */
	private $prefixes = array();

	/**
	 * Validates the given expression and tracks it.
	 * The default options accept IRIs, prefixed IRIs and variables.
	 *
	 * @param string $expression
	 * @param int $options
	 * @throws InvalidArgumentException
	 * @throws UnexpectedValueException
	 */
	public function validateExpression( $expression, $options = -1 ) {
		if ( !is_string( $expression ) ) {
			throw new InvalidArgumentException( '$expression has to be a string.' );
		}

		if ( $options < 0 ) {
			$options = self::VALIDATE_IRI | self::VALIDATE_PREFIXED_IRI | self::VALIDATE_VARIABLE;
		}

		if ( ( $options & self::VALIDATE_VARIABLE ) && $this->isVariable( $expression ) ) {
			$this->trackVariables( $expression );
			return;
		}

		if ( ( $options & self::VALIDATE_IRI ) && $this->isIRI( $expression ) ) {
			return;
		}

		if ( ( $options & self::VALIDATE_PREFIXED_IRI ) && $this->isPrefixedIRI( $expression ) ) {
			$this->trackPrefixes( $expression );
			return;
		}

		if ( ( $options & self::VALIDATE_PREFIX ) && $this->isPrefix( $expression ) ) {
			$this->prefixes[$expression] = true;
			return;
		}

		if ( ( $options & self::VALIDATE_FUNCTION ) && $this->isFunction( $expression ) ) {
			$this->trackVariables( $expression );
			$this->trackPrefixes( $expression );
			return;
		}

		if ( ( $options & self::VALIDATE_FUNCTION_AS ) && $this->isFunctionAs( $expression ) ) {
			$this->trackVariables( $expression );
			$this->trackPrefixes( $expression );
			return;
		}

		// @todo better error message
		throw new UnexpectedValueException( '$expression has to match ' . $options );
	}

	private static $variable = '[?$](\w+)';
	private static $iri = '\<((\\.|[^\\<])+)\>';
	private static $prefix = '[A-Za-z_]+';
	private static $name = '\w+';

	private function isVariable( $expression ) {
		return $this->matchesRegex( self::$variable, $expression );
	}

	private function isIRI( $expression ) {
		return $expression === 'a' || $this->matchesRegex( self::$iri, $expression );
	}

	private function isPrefixedIRI( $expression ) {
		return $this->matchesRegex( self::$prefix . ':' . self::$name, $expression );
	}

	private function isPrefix( $expression ) {
		return $this->matchesRegex( self::$prefix, $expression );
	}

	private function isFunction( $expression ) {
		// @todo also support expressions like ?a + ?b > 5 or ?x <= ?y
		// @todo this might not be complete
		// @todo check that opening brackets get closed
		return $this->matchesRegex( '(\d|\w|' . self::$variable . '|' . implode( '|', self::$functions ) . ').*', $expression );
	}

	private function isFunctionAs( $expression ) {
		return $this->matchesRegex( '(\d|\w|' . self::$variable . '|' . implode( '|', self::$functions ) . ') AS ' . self::$variable, $expression );
	}

	private function matchesRegex( $regex, $expression ) {
		return preg_match( '/^' . $regex . '$/', $expression );
	}

	private function trackVariables( $expression ) {
		// negative look-behind
		if ( !preg_match_all( '/(^|\W)(?<!AS )' . self::$variable . '/', $expression, $matches ) ) {
			return;
		}

		foreach ( $matches[2] as $match ) {
			$this->variables[$match] = true;
		}
	}

	private function trackPrefixes( $expression ) {
		if ( !preg_match_all( '/(^|\W)(' . self::$prefix . '):/', $expression, $matches ) ) {
			return;
		}

		foreach ( $matches[2] as $match ) {
			$this->prefixes[$match] = true;
		}
	}

	/**
	 * Returns the list of varialbes which have been used.
	 *
	 * @return string[]
	 */
	public function getVariables() {
		return array_keys( $this->variables );
	}

	/**
	 * Returns the list of prefixes which have been used.
	 *
	 * @return string[]
	 */
	public function getPrefixes() {
		return array_keys( $this->prefixes );
	}

}
