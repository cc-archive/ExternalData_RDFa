<?php
 
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension; it is not a valid entry point' );
}

/**
 * Class for handling the parser functions for External Data
 *
 * @author Yaron Koren
 */
class EDParserFunctions {

	// XML-handling functions based on code found at
	// http://us.php.net/xml_set_element_handler
	static function startElement( $parser, $name, $attrs ) {
		global $edgCurrentXMLTag;
		// set to all lowercase to avoid casing issues
		$edgCurrentXMLTag = strtolower($name);
	}

	static function endElement( $parser, $name ) {
		global $edgCurrentXMLTag;
		$edgCurrentXMLTag = "";
	}

	static function getContent ( $parser, $content ) {
		global $edgCurrentXMLTag, $edgXMLValues;
		$edgXMLValues[$edgCurrentXMLTag] = $content;
	}

	static function getXMLData ( $xml ) {
		global $edgXMLValues;
		$edgXMLValues = array();

		$xml_parser = xml_parser_create();
		xml_set_element_handler( $xml_parser, "EDParserFunctions::startElement", "EDParserFunctions::endElement" );
		xml_set_character_data_handler( $xml_parser, "EDParserFunctions::getContent" );
		if (!xml_parse($xml_parser, $xml, true)) {
			echo(sprintf("XML error: %s at line %d",
			xml_error_string(xml_get_error_code($xml_parser)),
			xml_get_current_line_number($xml_parser)));
		}
		xml_parser_free( $xml_parser );
		return $edgXMLValues;
	}

	static function getCSVData( $csv ) {
		// regular expression copied from http://us.php.net/fgetcsv
		$csv_vals = preg_split('/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/', $csv); 
		// start with a null value so that the real values start with
		// an index of 1 instead of 0
		$values = array( null );
		foreach ( $csv_vals as $csv_val ) {
			$values[] = trim( $csv_val, '"' );
		}
		return $values;
	}

	/**
	 * Recursive function for use by getJSONData()
	 */
	static function parseTree( $tree, &$retrieved_values ) {
		foreach ($tree as $key => $val) {
			if (is_array( $val )) {
				self::parseTree( $val, &$retrieved_values );
			} else {
				$retrieved_values[$key] = $val;
			}
		}
	}

	static function getJSONData( $json ) {
		$json_tree = json_decode($json, true);
		$values = array();
		if ( is_array( $json_tree ) ) {
			self::parseTree( $json_tree, &$values );
		}
		return $values;
	}
 
	/**
	 * Render the #get_external_data parser function
	 */
	static function doGetExternalData( &$parser ) {
		global $edgValues;
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$url = array_shift( $params );
		$url_contents = file_get_contents( $url );
		$format = array_shift( $params );
		$external_values = array();
		if ($format == 'xml') {
			$external_values = self::getXMLData( $url_contents );
		} elseif ($format == 'csv') {
			$external_values = self::getCSVData( $url_contents );
		} elseif ($format == 'json') {
			$external_values = self::getJSONData( $url_contents );
		}
		// for each external variable name specified in the function
		// call, get its value (if one exists), and attach it to the
		// local variable name
		foreach ($params as $param) {
			list( $local_var, $external_var ) = explode( '=', $param );
			// set to all lowercase to avoid casing issues
			$external_var = strtolower( $external_var );
			if ( array_key_exists( $external_var, $external_values ) )
				$edgValues[$local_var] = $external_values[$external_var];
		}

		return '';
	}
 
	/**
	 * Render the #external_value parser function
	 */
	static function doExternalValue( &$parser, $local_var = '' ) {
		global $edgValues;
		if ( array_key_exists( $local_var, $edgValues) )
			return $edgValues[$local_var];
		else
			return '';
	}
}
