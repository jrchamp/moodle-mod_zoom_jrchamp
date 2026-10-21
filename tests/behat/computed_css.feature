@mod @mod_zoom
Feature: Assert the computed CSS properties of page elements

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name    | intro     | introformat | course | content | contentformat | idnumber |
      | page     | H5PPage | PageDesc1 | 1           | C1     | H5Ptest | 1             | 1        |
    And the following "contentbank content" exist:
      | contextlevel | reference | contenttype     | user     | contentname     | filepath                                    |
      | Course       | C1        | contenttype_h5p | teacher1 | Multiple choice | /h5p/tests/fixtures/multiple-choice-2-6.h5p |
    And the "displayh5p" filter is "on"
    # Override this capability so the editing teacher may install the H5P libraries bundled in the package.
    And the following "permission overrides" exist:
      | capability                 | permission | role           | contextlevel | reference |
      | moodle/h5p:updatelibraries | Allow      | editingteacher | System       |           |
    # Required on Moodle 5.3+ until MDL-90210 is resolved (sticky footer blocks embedded H5P hover).
    And the following config values are set as admin:
      | enablelinearnav | 0 | format_topics |

  @javascript @core_h5p @repository_contentbank
  Scenario: Check a computed CSS property inside an iframe
    Given I am on the "H5PPage" "page activity editing" page logged in as "teacher1"
    And I click on "Insert H5P content" "button" in the "#fitem_id_page" "css_element"
    And I click on "Browse repositories..." "button" in the "Insert H5P content" "dialogue"
    And I select "Content bank" repository in file picker
    And I click on "Multiple choice" "file" in repository content area
    And I click on "Link to the file" "radio"
    And I click on "Select this file" "button"
    And I click on "Insert H5P" "button" in the "Insert H5P content" "dialogue"
    And I click on "Save and display" "button"
    And I wait until "h5p-iframe" iframe is interactable and switch to it
    And I wait until "h5p-iframe" iframe is interactable and switch to it
    # The fixture bundles H5P.MultiChoice 1.14; only assert its exact colours on that version.
    And the site is running H5P library "H5P.MultiChoice" version "1.14"
    Then the element ".h5p-answer .h5p-alternative-container" should have the CSS property "background-color" with value "rgb(221, 221, 221)"
    When I hover ".h5p-answer .h5p-alternative-container" "css_element"
    Then the element ".h5p-answer .h5p-alternative-container" should have the CSS property "background-color" with value "rgb(236, 236, 236)"
    And I switch to the main frame
