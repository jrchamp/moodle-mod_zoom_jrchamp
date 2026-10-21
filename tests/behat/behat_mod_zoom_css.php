<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Context;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Moodle\BehatExtension\Exception\SkippedException;

/**
 * Behat steps for asserting on computed CSS styles and guarding by H5P library version.
 *
 * @package mod_zoom
 * @category test
 * @copyright 2026 Jonathan Champ
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_zoom_css extends behat_base implements Context {
    /**
     * Checks an element's computed CSS property value, requiring a JavaScript session.
     *
     * The value may be a literal (e.g. '#ddd'), an equivalent notation, or a custom property
     * name (e.g. '--h5p-theme-alternative-base').
     *
     * @param string $selector CSS selector for the element.
     * @param string $property CSS property name.
     * @param string $value Expected value, or a custom property name.
     * @throws ExpectationException
     */
    #[Then('the element ":selector" should have the CSS property ":property" with value ":value"')]
    public function assert_element_has_css_property(string $selector, string $property, string $value): void {
        $computedvalue = (string) $this->get_computed_css_value($selector, $property);

        if (!$this->css_property_matches($selector, $property, $computedvalue, $value)) {
            throw new ExpectationException(
                'The CSS property "' . $property . '" for the element "' . $selector
                    . '" is currently "' . $computedvalue . '", but "' . $value . '" was expected.',
                $this->getSession()
            );
        }
    }

    /**
     * Checks an element's computed CSS property value does not match, requiring a JavaScript session.
     *
     * @param string $selector CSS selector for the element.
     * @param string $property CSS property name.
     * @param string $value Value that must not be present, or a custom property name.
     * @throws ExpectationException
     */
    #[Then('the element ":selector" should not have the CSS property ":property" with value ":value"')]
    public function assert_element_does_not_have_css_property(string $selector, string $property, string $value): void {
        $computedvalue = (string) $this->get_computed_css_value($selector, $property);

        if ($this->css_property_matches($selector, $property, $computedvalue, $value)) {
            throw new ExpectationException(
                'The CSS property "' . $property . '" for the element "' . $selector
                    . '" is currently "' . $computedvalue . '", but it should not be.',
                $this->getSession()
            );
        }
    }

    /**
     * Skips the rest of the scenario unless the given H5P library version is installed.
     *
     * @param string $library H5P library machine name (e.g. 'H5P.MultiChoice').
     * @param string $version Required major.minor version (e.g. '1.14').
     * @throws SkippedException
     */
    #[Given('the site is running H5P library ":library" version ":version"')]
    public function the_site_is_running_h5p_library_version(string $library, string $version): void {
        global $DB;

        $libraries = $DB->get_records(
            'h5p_libraries',
            ['machinename' => $library],
            'majorversion DESC, minorversion DESC',
            'id, majorversion, minorversion'
        );

        $installed = [];
        foreach ($libraries as $record) {
            $installed[] = $record->majorversion . '.' . $record->minorversion;
        }

        if (!in_array($version, $installed, true)) {
            throw new SkippedException(
                'Site must be running H5P library ' . $library . ' version ' . $version
                    . ' (installed: ' . (empty($installed) ? 'none' : implode(', ', $installed)) . ')'
            );
        }
    }

    /**
     * Checks whether a computed CSS value matches, comparing literally first, then normalised.
     *
     * @param string $selector CSS selector for the element.
     * @param string $property CSS property name.
     * @param string $computedvalue The element's computed value.
     * @param string $value Expected value, or a custom property name.
     * @return bool
     */
    protected function css_property_matches(
        string $selector,
        string $property,
        string $computedvalue,
        string $value
    ): bool {
        $computedvalue = trim($computedvalue);
        $value = trim($value);

        if ($computedvalue === $value) {
            return true;
        }

        $expected = $this->get_computed_css_value($selector, $property, $value);

        return $expected !== null && $computedvalue === trim($expected);
    }

    /**
     * Returns the computed value for $property, or the resolved value of $expected if given.
     *
     * @param string $selector CSS selector for the element.
     * @param string $property CSS property name.
     * @param string|null $expected Expected value, or a custom property name, to resolve instead.
     * @return string|null
     */
    protected function get_computed_css_value(string $selector, string $property, ?string $expected = null): ?string {
        // Computed styles are only available when a JavaScript driver is in use.
        $this->require_javascript('Computed CSS styles cannot be read without a JavaScript session.');

        // The find() call spins until the element is available, or throws an ElementNotFoundException.
        $node = $this->find('css', $selector);

        $wdelement = $this->get_webdriver_element_from_node_element($node);
        $webdriver = $this->getSession()->getDriver()->getWebDriver();

        $js = <<<'EOF'
            return (function(element, property, expected) {
                const style = window.getComputedStyle(element);
                if (expected === null) {
                    return style.getPropertyValue(property);
                }

                let cssvalue = expected;
                if (expected.startsWith('--')) {
                    // Only resolve a custom property that is actually defined on the element.
                    if (!style.getPropertyValue(expected).trim()) {
                        return null;
                    }
                    cssvalue = 'var(' + expected + ')';
                } else if (!window.CSS || !window.CSS.supports(property, expected)) {
                    // The expected value is not valid for this property, so it cannot be normalised.
                    return null;
                }

                const probe = document.createElement('div');
                probe.style.setProperty('position', 'absolute');
                probe.style.setProperty('visibility', 'hidden');
                element.appendChild(probe);
                probe.style.setProperty(property, cssvalue);
                const value = window.getComputedStyle(probe).getPropertyValue(property);
                element.removeChild(probe);
                return value;
            })(arguments[0], arguments[1], arguments[2])
            EOF;

        $value = $webdriver->executeScript($js, [$wdelement, $property, $expected]);

        return $value === null ? null : (string) $value;
    }
}
