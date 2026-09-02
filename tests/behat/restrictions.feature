@mod @mod_pagecheck
Feature: Page restrictions on a submission
  In order to receive work that meets the brief
  As a teacher
  I need submissions that break the page restrictions to be refused

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript
  Scenario: A teacher configures a page range
    Given I am on the "Course 1" course page logged in as teacher1
    When I add a "Submission with page check" to section "1" and I fill the form with:
      | Activity name    | Essay |
      | Minimum pages    | 5     |
      | Maximum pages    | 10    |
    Then I am on the "Essay" "pagecheck activity" page
    And I should see "Between 5 and 10"

  @javascript
  Scenario: A student sees the restrictions before submitting
    Given the following "activities" exist:
      | activity  | course | name  | minpages | maxpages |
      | pagecheck | C1     | Essay | 5        | 10       |
    When I am on the "Essay" "pagecheck activity" page logged in as student1
    Then I should see "Restrictions"
    And I should see "Between 5 and 10"
    And I should see "Nothing submitted yet"
    And I should see "Add submission"

  Scenario: A closed activity does not offer a submission button
    Given the following "activities" exist:
      | activity  | course | name  | cutoffdate  |
      | pagecheck | C1     | Essay | ##yesterday## |
    When I am on the "Essay" "pagecheck activity" page logged in as student1
    Then I should not see "Add submission"
