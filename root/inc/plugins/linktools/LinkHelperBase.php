<?php

/**
 *  Part of the Link Tools plugin for MyBB 1.8.
 *  Copyright (C) 2021 Laird Shaw
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

abstract class LinkHelper {
	/**
	 * The singleton instance of this class, as an array so as to support
	 * descendant class singletons.
	 */
	static private $instances = array();

	/**
	 * The regex against which by default the link after normalisation via
	 * lkt_normalise_url() is tested to determine whether or not it is
	 * supported by this Helper. This is the preferred regex, since
	 * matching non-normalised links may result in multiple helpers being
	 * prioritised highest for URLs which are all the same in the sense of
	 * normalising to the same normalised URL.
	 */
	static protected $supported_norm_links_regex;

	/**
	 * The alternative regex against which a link is tested to determine
	 * whether or not it is supported by this Helper if the regex above for
	 * normalised URLs is not set.
	 */
	static protected $supported_links_regex;

	/**
	 * This Helper's priority as a candidate to generate the preview for
	 * those links which it supports. The greater the value, the higher its
	 * priority.
	 *
	 * A signed integer (negative values are valid) defaulting to zero.
	 */
	static protected $priority = 0;

	/**
	 * This Helper's version, used to determine whether a link preview
	 * generated by this Helper is still valid (i.e., was generated by the
	 * Helper with the same version number).
	 *
	 * Also used to determine when this Helper's template needs to be
	 * updated in the database.
	 * 
	 * For that reason, this property's value should be increased whenever
	 * the $template property is modified.
	 */
	static protected $version;

	/**
	 * A friendly name for this helper (localisation not supported), to be
	 * shown in the ACP Config's Link Helpers module at:
	 * admin/index.php?module=config-linkhelpers
	 */
	protected $friendly_name;

	/**
	 * This contains the contents of the template which Link Tools adds to
	 * MyBB when this Link Helper is enabled in the ACP. If it is empty, the
	 * call to $this->get_template_for_eval() will return the contents of the
	 * 'linktools_linkpreview_default' template. If it is not empty, it will
	 * be named 'linktools_linkpreview_' followed by the lowercased class
	 * name with the "LinkHelper" prefix removed.
	 *
	 * The $version property should be increased whenever this property is
	 * changed.
	 */
	protected $template;

	/**
	 * Block instantiations of this class using the "new" keyword.
	 * Instead, get_instance() should be used.
	 */
	private function __construct() {}

	/**
	 * Block cloning of this singleton class.
	 */
	private function __clone() {}

	/**
	 * Retrieves this class's (or one of its descendants') singleton.
	 */
	public static function get_instance(): LinkHelper {
		$class = static::class;
		if (!isset(self::$instances[$class])) {
			self::$instances[$class] = new static();
		}

		return self::$instances[$class];
	}

	/**
	 * Tests whether the given link is supported by this Helper.
	 *
	 * @param $link The link to test.
	 *
	 * @return boolean True if supported; false if not supported.
	 */
	static public function supports_link($link) {
		$class = static::class;

		return $class::$supported_norm_links_regex
		         ? preg_match($class::$supported_norm_links_regex, lkt_normalise_url($link))
		         : preg_match($class::$supported_links_regex     ,                   $link );
	}

	/**
	 * Gets this helper's priority.
	 *
	 * @return integer This helper's priority.
	 */
	static public function get_priority() {
		$class = static::class;
		return $class::$priority;
	}

	/**
	 * Gets this helper's version number.
	 *
	 * @return string Version number, e.g., "1.3.0".
	 */
	static public function get_version() {
		$class = static::class;
		return $class::$version;
	}

	/**
	 * Gets this helper's friendly name.
	 *
	 * @return string The friendly name.
	 */
	public function get_friendly_name() {
		return $this->friendly_name;
	}

	/**
	 * Makes HTML safe to avert XSS attacks.
	 *
	 * @param string $html The HTML to make safe.
	 *
	 * @return string The safe HTML.
	 */
	public function make_safe($html) {
		return strip_tags($html);
	}

	static public function mk_tpl_nm_frm_classnm($classname) {
		$prefix = 'linkhelper';
		$name = strtolower($classname);
		if (my_substr($name, 0, strlen($prefix)) == $prefix) {
			$name = my_substr($name, strlen($prefix));
		}

		return 'linktools_linkpreview_'.$name;
	}

	/**
	 * Gets the name of the template this helper uses for its output.
	 *
	 * @return string The template name, suitable for passing to
	 *                $templates->get().
	 */
	public function get_template_name($ret_empty_if_default = false) {
		if ($this->template) {
			return self::mk_tpl_nm_frm_classnm(get_class($this));
		} else if ($ret_empty_if_default) {
			return '';
		} else	return 'linktools_linkpreview_default';
	}

	/**
	 * Gets the `template` property of this class, if any. This is the
	 * contents of the template as it is to be inserted into the database
	 * if/when a Helpers which descends from this base class is enabled
	 * at admin/index.php?module=config-linkhelpers.
	 *
	 * @return string The template.
	 */
	public function get_template_raw() {
		return $this->template;
	}
	
	/**
	 * Gets the template this helper uses for its output, the contents of
	 * which should then be eval()d as usual.
	 *
	 * @return string The template.
	 */
	public function get_template_for_eval() {
		global $templates;

		return $templates->get($this->get_template_name(), 1, 1); 
	}

	/**
	 * Get the preview's valid HTML. This is the primary function called
	 * by consumers of this class.
	 *
	 * @param $link         The link for which the preview should be generated.
	 *                      Is checked for validity (support).
	 * @param $content      The contents of the page at $link.
	 * @param $content_type The content type returned for the contents of
	 *                      the previous variable ($content_type).
	 *
	 * @return Mixed The preview as a string of HTML or false if $link is
	 *               not supported.
	 */
	public function get_preview($link, $content, $content_type) {
		$class = static::class;
		if (!$class::supports_link($link)) {
			return false;
		}

		return $this->get_preview_contents($link, $content, $content_type);
	}

	/**
	 * The heart of the class, which should be implemented in a descendant
	 * class. Parses the HTML of the link to generate the inner preview,
	 * which (unless this functionality is overridden in a descendant class)
	 * is then wrapped by the caller in a container.
	 *
	 * @param $link         The link for which the preview should be generated.
	 *                      Is checked for validity (support).
	 * @param $content      The contents of the page at $link.
	 * @param $content_type The content type returned for the contents of
	 *                      the previous variable ($content_type).
	 *
	 * @return string The preview as valid HTML.
	 */
	abstract protected function get_preview_contents($link, $content, $content_type);
}