<?php
/**
 * A text filter for roles and capabilities which behaves in a similar manner to the multilang filter.
 * 
 * @copyright  Copyright 2013 Web Courseworks, Ltd.
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU Public License 3.0
 * @package    filter
 * @subpackage multirole
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
 
defined('MOODLE_INTERNAL') || die();

class filter_multirole extends moodle_text_filter {
    // Use the HTML5 data-* style attributes so we still have valid HTML.
    const FILTER_CAPABILITY_ATTRIBUTE = 'data-capability';
    const FILTER_ROLE_ATTRIBUTE = 'data-role';

    public function hash() {
        global $USER;

        $userid = $USER->id;
        $accessdeftime = !empty($USER->access['time']) ? $USER->access['time'] : time();

        return "MULTIROLE#{$userid}#ACCESSDEFTIME#{$accessdeftime}";
    }

    public function filter($text, array $options = array()){
        global $CFG;

        if (empty($text)) {
            return $text;
        }

        $capattributeused = stripos($text, self::FILTER_CAPABILITY_ATTRIBUTE) !== false;
        $roleattributeused = stripos($text, self::FILTER_ROLE_ATTRIBUTE) !== false;

        // Skip if there's no presence of the data-capability or data-role attribute in the text.
        if (!$capattributeused && !$roleattributeused) {
            return $text;
        }

        // Don't really care about parsing errors.
        $useerrors = libxml_use_internal_errors(true);

        $document = new DOMDocument();
        $document->loadHTML($text);
        $xpath = new DOMXPath($document);

        $mutated = false;

        // Filter html based on capability.
        if ($capattributeused) {
            $mutated = $mutated | $this->filter_caps($xpath, $document);
        }

        // Filter html based on a role shortname.
        if ($roleattributeused) {
            $mutated = $mutated | $this->filter_roles($xpath, $document);
        }

        // Reset libxml_use_internal_errors back to what it was.
        libxml_use_internal_errors($useerrors);
        // And clear the error buffer so we don't have that memory hanging around.
        libxml_clear_errors();

        // Skip saving the html if we didn't change anything.
        if (!$mutated) {
            return $text;
        }

        return $document->saveHTML();
    }

    /**
     * Filter any elements with the data-capability attribute.
     * 
     * @param DOMXPath $xpath An xpath object for the supplied $document.
     * @param DOMDocument $document The HTML document.
     * @return bool Whether or not any changes were made to the $document.
     */
    protected function filter_caps(DOMXPath $xpath, DOMDocument $document) {
        $mutated = false;

        foreach($xpath->query('//*[@'.self::FILTER_CAPABILITY_ATTRIBUTE.']') as $node) {
            $attribute = $node->attributes->getNamedItem(self::FILTER_CAPABILITY_ATTRIBUTE);

            if (empty($attribute) || empty($attribute->nodeValue)) {
                continue;
            }

            // Sanitize the passed in cap to ensure it might look something like type/m.od2:exa_m-ple.
            $capability = preg_replace('/[^a-z,0-9,\/,:,\.,-,_]/i', '', $attribute->nodeValue);

            if (!has_capability($capability, $this->context)) {
                $this->remove_node($document, $node);
                $mutated = true;
            }
        }
        
        return $mutated;
    }

    /**
     * Filter any elements with the data-role attribute.
     *
     * @param DOMXPath $xpath An xpath object for the supplied $document.
     * @param DOMDocument $document The HTML document.
     * @return bool Whether or not any changes were made to the $document.
     */
    protected function filter_roles(DOMXPath $xpath, DOMDocument $document) {
        $mutated = false;

        $roleshortnames = $this->get_assigned_roles_shortnames();

        foreach($xpath->query('//*[@'.self::FILTER_ROLE_ATTRIBUTE.']') as $node) {
            $attribute = $node->attributes->getNamedItem(self::FILTER_ROLE_ATTRIBUTE);

            if (empty($attribute) || empty($attribute->nodeValue)) {
                continue;
            }

            // No need to clean this since we're just doing an array_search.
            $role = $attribute->nodeValue;

            if (array_search($role, $roleshortnames) === false) {
                $this->remove_node($document, $node);
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
    protected function remove_node(DOMDocument $document, DOMNode $node) {
        if (!empty($node->parentNode)) {
            $node->parentNode->removeChild($node);
        } else {
            $document->removeChild($node);
        }
    }

    /**
     * Get a list of all shortnames of roles assigned to the user on the given context or parent contexts.
     *
     * @return array Array of role short names.
     */
    protected function get_assigned_roles_shortnames() {
        $roles = get_user_roles($this->context);    
        $roleshortnames = !empty($roles) ? array_map(array($this, 'shortname_extractor_callback'), $roles) : array();

        return $roleshortnames;
    }

    /**
     * An array_map callback used in filter_multirole::get_assigned_roles_shortnames.
     */
    protected function shortname_extractor_callback($item) {
        return $item->shortname;
    }
}