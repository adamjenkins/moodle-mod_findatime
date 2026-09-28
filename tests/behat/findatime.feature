@mod @mod_findatime
Feature: Group members find a common meeting time
  In order to meet as a group
  As a teacher or a student
  I need to mark availability, see the group's overlap and confirm a time

  # The activity runs from 10 to 12 June 2030, 09:00-11:00 London time (BST), so no DST change
  # falls inside it. Student 1 is in London and student 2 in New York (09:00 London = 04:00 there).
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | timezone         |
      | teacher1 | Tessa     | Teacher  | Europe/London    |
      | student1 | Alice     | London   | Europe/London    |
      | student2 | Bob       | NewYork  | America/New_York |
      | student3 | Chika     | Tokyo    | Asia/Tokyo       |
    And the following "courses" exist:
      | fullname | shortname | groupmode | groupmodeforce |
      | Course 1 | C1        | 1         | 1              |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | G1       |
      | Group 2 | C1     | G2       |
    And the following "group members" exist:
      | user     | group |
      | student1 | G1    |
      | student2 | G1    |
      | student3 | G2    |
    And the following "activities" exist:
      | activity  | name         | course | idnumber | timezone      | datestart               | dateend                 | daystartmins | dayendmins | slotsize | duration |
      | findatime | Team meeting | C1     | fat1     | Europe/London | ##10 June 2030 12:00## | ##12 June 2030 12:00## | 540          | 660        | 30       | 60       |

  @javascript
  Scenario: A teacher creates a Find a time activity
    Given I log in as "teacher1"
    When I add a "findatime" activity to course "Course 1" section "1" and I fill the form with:
      | Name              | Project kickoff |
      | Daily start time  | 10:00           |
      | Daily end time    | 12:00           |
      | Slot size         | 60 minutes      |
      | Meeting length    | 1 hour          |
    And I am on the "Project kickoff" "findatime activity" page
    Then I should see "Project kickoff"
    And I should see "Times are shown in your timezone, Europe/London."
    And I should see "Choose a group to see its availability."

  @javascript
  Scenario: Two students in a group mark their availability and see where it overlaps
    Given I am on the "Team meeting" "findatime activity" page logged in as student1
    And I should see "Group overlap: Group 1"
    When I click on ".mod-findatime-grid [data-row='0'][data-col='0']" "css_element"
    And I click on ".mod-findatime-grid [data-row='1'][data-col='0']" "css_element"
    And I click on "Save availability" "button"
    Then I should see "Saved." in the "[data-region='savestatus']" "css_element"
    And I should see "1" in the ".mod-findatime-heatmap [data-row='0'][data-col='0']" "css_element"
    And I log out
    And I am on the "Team meeting" "findatime activity" page logged in as student2
    And I should see "The activity's times were set in Europe/London."
    And I should see "4:00 AM" in the ".mod-findatime-grid tbody tr:first-child th" "css_element"
    And I click on ".mod-findatime-grid [data-row='0'][data-col='0']" "css_element"
    And I click on ".mod-findatime-grid [data-row='1'][data-col='0']" "css_element"
    And I click on "Save availability" "button"
    And I should see "Saved." in the "[data-region='savestatus']" "css_element"
    And I should see "2" in the ".mod-findatime-heatmap [data-row='0'][data-col='0']" "css_element"
    And I should see "2 of 2 have answered"
    And I should see "2 of 2 available: Alice London, Bob NewYork"

  @javascript
  Scenario: A teacher confirms the best time and group members are told
    Given the following "mod_findatime > availabilities" exist:
      | activity | user     | day | time  |
      | fat1     | student1 | 1   | 09:00 |
      | fat1     | student1 | 1   | 09:30 |
      | fat1     | student2 | 1   | 09:00 |
      | fat1     | student2 | 1   | 09:30 |
    And I am on the "Team meeting" "findatime activity" page logged in as teacher1
    And I select "Group 1" from the "Separate groups" singleselect
    And I should see "No meeting time has been confirmed yet."
    When I click on "Confirm this time" "button"
    And I set the field "Location or link" to "Room 101"
    And I click on "Confirm" "button" in the "Confirm a time" "dialogue"
    Then I should see "Tuesday, 11 June 2030, 9:00 AM" in the "[data-region='mod-findatime-meeting']" "css_element"
    And I should see "Room 101" in the "[data-region='mod-findatime-meeting']" "css_element"
    And I should see "Confirmed by Tessa Teacher."
    And I log out
    And I am on the "Team meeting" "findatime activity" page logged in as student1
    And I should see "Room 101" in the "[data-region='mod-findatime-meeting']" "css_element"
    And I should not see "Cancel meeting"

  @javascript
  Scenario: The confirmed meeting is in the calendar of the group's members only
    Given the following "mod_findatime > meetings" exist:
      | activity | group | day | time  | location |
      | fat1     | G1    | 0   | 09:00 | Room 7   |
    When I log in as "student2"
    And I visit "/calendar/view.php?view=day&time=1907323200"
    Then I should see "Meeting: Team meeting"
    And I log out
    And I log in as "student3"
    And I visit "/calendar/view.php?view=day&time=1907323200"
    And I should not see "Meeting: Team meeting"
