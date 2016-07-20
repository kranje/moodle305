@local_lae
Feature: Search for courses from the meta link interface
  In order to easily add new course meta links
  As a teacher
  I need to be able to search for courses

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Terry | Teacher | teacher1@example.net |
      | student1 | Sally | Student | student1@example.net |
      | student2 | Steve | Student | student2@example.net |
      | student3 | Sean  | Student | student3@example.net |
      | student4 | Sarah | Student | student4@example.net |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |   
    And I log in as "admin"   

  @javascript
  Scenario: Add a course meta link via search
    Given I navigate to "Manage enrol plugins" node in "Site administration > Plugins > Enrolments"
    And I click on "Enable" "link" in the "Course meta link" "table_row"
    And I click on "Dashboard" "link" in the "Navigation" "block"
    And I follow "Courses"
    And I follow "Course 2"
    And I navigate to "Enrolment methods" node in "Course administration > Users"
    And I set the following fields to these values:
      | Add method | Course meta link |
    And I set the following fields to these values:
      | Search | Course 1 |
    And I set the following fields to these values:
      | link | Course 1 |
      | Add to group | Create new group |
    And I press "Add method"
    And I follow "Enrolled users"
    And I should see "Sally Student"
    And I navigate to "Groups" node in "Course administration > Users"
    And I should see "Course 1 course (5)"
