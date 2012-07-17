<?php
/**
 * A text filter for roles and capabilities which behaves in a similar manner to the multilang filter.
 * 
 * @copyright Copyright 2011 Web Courseworks, Ltd.
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GNU Public License 2.0
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Use the HTML5 data-* style attributes so we still have valid HTML.
define('MULTIROLE_FILTER_CAPABILITY_ATTRIBUTE', 'data-capability');
define('MULTIROLE_FILTER_ROLE_ATTRIBUTE', 'data-role');

/**
 * Multirole text filter implementation.
 * 
 * @uses MULTIROLE_FILTER_CAPABILITY_ATTRIBUTE
 * @uses MULTIROLE_FILTER_ROLE_ATTRIBUTE
 * @uses CONTEXT_COURSE
 * @param int $courseid The id of the course the text is being filtered for.
 * @param string $text The text to filter.
 * @return string The filtered text.
 */
function multirole_filter($courseid, $text) {
    global $CFG;

    if (empty($text)) {
        return $text;
    }

    $capattributeused = stripos($text, MULTIROLE_FILTER_CAPABILITY_ATTRIBUTE) !== false;
    $roleattributeused = stripos($text, MULTIROLE_FILTER_ROLE_ATTRIBUTE) !== false;

    // Skip if there's no presence of the data-capability or data-role attribute in the text.
    if (!$capattributeused && !$roleattributeused) {
        return $text;
    }

    $context = get_context_instance(CONTEXT_COURSE, $courseid);

    // Don't really care about parsing errors.
    $useerrors = libxml_use_internal_errors(true);

    $document = new DOMDocument();
    $document->loadHTML($text);
    $xpath = new DOMXPath($document);

    $mutated = false;

    // Filter html based on capability.
    if ($capattributeused) {
        $mutated = $mutated | multirole_filter_caps($xpath, $document, $context);
    }

    // Filter html based on a role shortname.
    if ($roleattributeused) {
        $mutated = $mutated | multirole_filter_roles($xpath, $document, $context);
    }

    // Reset libxml_use_internal_errors back to what it was.
    libxml_use_internal_errors($useerrors);
    // And clear the error buffer so we don't have that memory hanging around.
    libxml_clear_errors();

    // Skip saving the html if we didn't change anything.
    if (!$mutated) {
        return $text;
    }

    // This result cannot be cached because it's highly user specific.
    $CFG->currenttextiscacheable = false;

    return $document->saveHTML();
}

/**
 * Filter any elements with the data-capability attribute.
 * 
 * @uses MULTIROLE_FILTER_CAPABILITY_ATTRIBUTE
 * @param DOMXPath $xpath An xpath object for the supplied $document.
 * @param DOMDocument $document The HTML document.
 * @param object $context The current context to check capabilities against.
 * @return bool Whether or not any changes were made to the $document.
 */
function multirole_filter_caps($xpath, $document, $context) {
    $mutated = false;

    foreach($xpath->query('//*[@'.MULTIROLE_FILTER_CAPABILITY_ATTRIBUTE.']') as $node) {
        $attribute = $node->attributes->getNamedItem(MULTIROLE_FILTER_CAPABILITY_ATTRIBUTE);

        if (empty($attribute) || empty($attribute->nodeValue)) {
            continue;
        }

        // Sanitize the passed in cap to ensure it might look something like type/m.od2:exa_m-ple.
        $capability = preg_replace('/[^a-z,0-9,\/,:,\.,-,_]/i', '', $attribute->nodeValue);

        if (!has_capability($capability, $context)) {
            multirole_remove_node($document, $node);
            $mutated = true;
        }
    }
    
    return $mutated;
}

/**
 * Filter any elements with the data-role attribute.
 *
 * @uses MULTIROLE_FILTER_ROLE_ATTRIBUTE
 * @param DOMXPath $xpath An xpath object for the supplied $document.
 * @param DOMDocument $document The HTML document.
 * @param object $context The current context to check roles against.
 * @return bool Whether or not any changes were made to the $document.
 */
function multirole_filter_roles($xpath, $document, $context) {
    $mutated = false;

    $roleshortnames = multirole_get_assigned_roles_shortnames($context);

    foreach($xpath->query('//*[@'.MULTIROLE_FILTER_ROLE_ATTRIBUTE.']') as $node) {
        $attribute = $node->attributes->getNamedItem(MULTIROLE_FILTER_ROLE_ATTRIBUTE);

        if (empty($attribute) || empty($attribute->nodeValue)) {
            continue;
        }

        // No need to clean this since we're just doing an array_search.
        $role = $attribute->nodeValue;

        if (array_search($role, $roleshortnames) === false) {
            multirole_remove_node($document, $node);
            $mutated = true;
        }
    }

    return $mutated;
}

/**
 * Remove a specific XMLNode from the XMLDocument.
 *
 * @param DOMDocument $document The document the node belongs to.
 * @param DOMNode $node The node to remove.
 */
function multirole_remove_node($document, $node) {
    if (!empty($node->parentNode)) {
        $node->parentNode->removeChild($node);
    } else {
        $document->removeChild($node);
    }
}

/**
 * Get a list of all shortnames of roles assigned to the user on the given context or parent contexts.
 *
 * @param object $context The context to find the current user's roles for.
 * @return array Array of role short names.
 */
function multirole_get_assigned_roles_shortnames($context) {
    $roles = get_user_roles($context);    
    $roleshortnames = !empty($roles) ? array_map('mutlirole_shortname_extractor_callback', $roles) : array();
    
    return $roleshortnames;
}

/**
 * An array_map callback used in multirole_get_assigned_roles_shortnames.
 */
function mutlirole_shortname_extractor_callback($item) {
    return $item->shortname;
}