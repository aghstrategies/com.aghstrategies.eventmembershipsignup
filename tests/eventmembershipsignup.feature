Scenario: Completing pay-later payments for an event with an additional event signup
  Given Event Additional Signup is installed
    And two events are set up
    And the first event has online registration enabled with a price set that has a field with an option where the second event is selected for an additional signup
    And a contact has registered for the first event with Pay Later, causing a pending contribution and two pending participant records
  When an admin user clicks Record Payment on the contribution
    And the payment is equal to the total amount due
  Then the contribution should have the status Completed
    And the participant record for the second event should have the status Registered

Scenario: Making partial payments for an event with an additional event signup
  Given Event Additional Signup is installed
    And two events are set up
    And the first event has online registration enabled with a price set that has a field with an option where the second event is selected for an additional signup
    And a contact has registered for the first event with Pay Later, causing a pending contribution and two pending participant records
  When an admin user clicks Record Payment on the contribution
    And the payment is less than the total amount due
  Then the contribution should have the status Partially Paid
    And the participant record for the second event should have the status Partially Paid

Scenario: Completing partial payments for an event with an additional event signup
  Given Event Additional Signup is installed
    And two events are set up
    And the first event has online registration enabled with a price set that has a field with an option where the second event is selected for an additional signup
    And a contact has registered for the first event with Pay Later, causing a pending contribution and two pending participant records
    And a partial payment has been made, with the status of the contribution record and the two participant records being Partially Paid
  When an admin user clicks Record Payment on the contribution
    And the payment is equal to the remaining amount due
  Then the contribution should have the status Completed
    And the participant record for the second event should have the status Registered
