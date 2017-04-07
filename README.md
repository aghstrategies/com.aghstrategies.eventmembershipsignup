# CiviCRM Event Additional Signup

Generate memberships or other event registrations from price options in a price set on an event

## Installation and Configuration

Follow the standard CiviCRM extension installation procedure.

## Usage is simple

1. When creating or editing a price option (a radio or checkbox option for a price ﬁeld), you may select “Other sign up” as Participant or Membership.
2. Select the membership type or event that the membership or participant record should be created for.
3. People who select the option while registering for an event that uses your price set will then be registered for the other event or have a membership added.

![Field Options Setup](/images/eas-field-options-setup.jpg)


## Caveats

* The payment is not associated with the membership or participant record.  You may want to accommodate this in bookkeeping, or you may not care.  (And what you consider to be the other event’s share of the income might be greater than the option’s price.)
* Memberships are started/renewed immediately, even if the event registration is pay-later.
* Options corresponding to events that are full will be disabled, even if a waitlist is available.  (A notice and link will allow joining the waitlist separately.)
* Options corresponding to events that are in the past, or with registration closing dates in the past, will be disabled.
* If options correspond to events that have registration opening dates in the future, or with the “Allow online registration” box unchecked, that will not affect whether they’re enabled.  A common use case is to only allow registration for one event via another event.

## Help and Improvements

Please help improve this extension by using the extension issue queue to report any troubles and to make requests for feature improvements. The issue queue is here: https://github.com/aghstrategies/com.aghstrategies.eventmembershipsignup/issues

Issues submitted to the issue queue will be addressed based on availability and interest. Please contact us for request paid support.
