@local @local_ragflowdashboard @javascript
Feature: RAGflow Dashboard report
  In order to review RAGflow usage
  As a site administrator
  I need to open the RAGflow Dashboard report

  Scenario: A site administrator can open the RAGflow usage dashboard
    Given I log in as "admin"
    When I navigate to "Reports > RAGflow Dashboard" in site administration
    Then I should see "RAGflow Dashboard"
