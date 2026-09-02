@mod @mod_pagecheck
Feature: Submitting work for grading
  In order to have my work marked
  As a student
  I need to attach files and send them for grading

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
    And the following "activities" exist:
      | activity  | course | name  | minpages | maxpages |
      | pagecheck | C1     | Essay | 0        | 0        |

  @javascript
  Scenario: A student with nothing attached cannot send work for grading
    When I am on the "Essay" "pagecheck activity" page logged in as student1
    Then I should see "Nothing submitted yet"
    And I should not see "Send for grading"

  @javascript
  Scenario: A teacher sees that nobody has submitted yet
    When I am on the "Essay" "pagecheck activity" page logged in as teacher1
    Then I should see "0 of 1 participants have sent work for grading"
    And I follow "View submissions"
    And I should see "Student One"
    And I should see "Nothing submitted yet"
