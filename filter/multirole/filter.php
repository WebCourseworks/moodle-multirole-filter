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

function multirole_filter($courseid, $text) {
    global $CFG;

    if (empty($text) or is_numeric($text)) {
        return $text;
    }

    $context = get_context_instance(CONTEXT_COURSE, $courseid);

    // Don't really care about parsing errors.
    libxml_use_internal_errors(true);

    $document = new DOMDocument();
    $document->loadHTML($text);
    $xpath = new DOMXPath($document);

    $mutated = false;
    
    // Filter
    $mutated = $mutated | multirole_filter_caps($xpath, $document, $context);
    $mutated = $mutated | multirole_filter_roles($xpath, $document, $context);

    // Skip saving the html if we didn't change anything.
    if (!$mutated) {
        return $text;
    }

    return $document->saveHTML();
}

/**
 * Filter any elements with the data-capability attribute,
 */
function multirole_filter_caps($xpath, $document, $context) {
    // This is the name of the attribute we'll be looking at.  Use the HTML5 data-* style attributes so we still have valid HTML.
    $attributename = 'data-capability';

    $mutated = false;

    foreach($xpath->query("//*[@{$attributename}]") as $node) {
        $attribute = $node->attributes->getNamedItem($attributename);

        if (empty($attribute) || empty($attribute->nodeValue)) {
            continue;
        }

        // Sanitize the passed in cap to ensure it might look something like type/mod:example.
        $capability = preg_replace('/[^a-z,\/,:]/i', '', $attribute->nodeValue);

        if (!has_capability($capability, $context)) {
            multirole_remove_node($document, $node);
            $mutated = true;
        }
    }
    
    return $mutated;
}

/**
 * Filter any elements with the data-role attribute,
 */
function multirole_filter_roles($xpath, $document, $context) {
    // This is the name of the attribute we'll be looking at.  Use the HTML5 data-* style attributes so we still have valid HTML.
    $attributename = 'data-role';

    $mutated = false;

    $roleshortnames = multirole_get_assigned_roles_shortnames($context);

    foreach($xpath->query("//*[@{$attributename}]") as $node) {
        $attribute = $node->attributes->getNamedItem($attributename);

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