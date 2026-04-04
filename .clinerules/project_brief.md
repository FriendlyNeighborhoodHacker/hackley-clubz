Clubz "Project Brief"

# Overview

Our mission is to centralize how Hackley students engage with clubs, so that it is easier for students to join clubs, participate in club communication, RSVP to club events, and we hope that this also gives faculty visibility into community engagement activities at Hackley so that we can recognize community engagement more easily.

The biggest challenge is getting people to use it, so we want this to make it easier for clubs to do things they already need to do.

# Problems to solve / key high level functionality

## General Users: Events

### Easy RSVP to club events.
It should be really easy for a student to say that they are attending an event

### Calendar.
People should be able to see a calendar of all events that they’ve RSVP’d to, and also all upcoming events in their clubs so that they can RSVP to the ones they want to attend.

### Event reminders.
People should be reminded of events that they’ve RSVP’d to. There should also be an integration into Google Calendar, so that people can integrate their events into their calendar.

## General Users: Conversation Threads

### Start a conversation with members of the club.
It should be easy for a member of a club to start a conversation with other members of the club via the app.

### Get notified about a new message and reply.
This flow should be easy - students should be able to get a notification, click, type a reply, and submit. They should also be able to react to posts/messages by “liking” the message with a little pink heart. People should be able to edit or delete the messages that they post.

### Reporting a post.
Members should be able to report a post. Reporting a post should notify the club admins that the member reported the post, and the notification should make it easy for club admins to take action. Reporting a post should be associated with a red flag. Reporting a post should also include a short description for the user to write about why they are reporting the post.

## General Users: Sign up / manage memberships

### Sign up for a club.
It should be easy for a student to sign up for a club by finding the club on the app and signing up. QR code integration should allow club admins to generate QR codes so that during a club fair, students can sign up easily.

### Manage memberships in clubs.
It should be easy for someone to see all of the clubs they are in and remove their membership from anything they don’t want to be in. Also, it should be easy for people to see all the clubs, search by keyword, and join clubs they want to join.

## Club Admins: Events

### Create event.
It should be easy for club members or admins to create events for a club.

### Manage events.
It should be easy to edit an event, delete an event.

## Club Admins: Communication

### View and communicate with club members.
It should be easy for club admins to message all the members of the club - either via email or via text message.
Question: How do text messages actually work? Is this even possible?

### Club Admins should be able to moderate communication threads.
They should be able to delete a comment, delete threads, and pin and unpin posts at the top of threads for everyone in the club to see.

### Safety for posts.
When someone posts a new message, the app should determine whether it complies with reasonable policies of communication (we’ll write these policies, but this is going to be not in the first version). The app should decide whether the post (a) clearly is okay, (b) clearly violates a policy, or (c) is risky. If (a), the app should allow the post. If (b) the app should disallow the post. If (c), the app should display a message to the user before the post and ask them to confirm, and the post should be flagged for admins to review.

## Policies to follow
There should never be a conversation with only one adult and one student. (ie, if one exists, they shouldn’t be able to post anything)
Posts should not be able to use offensive language (we’ll have a file with a list of keywords)

### Sign someone up for a club.
This should be easy so that a club admin can do this during a club fair.

# User Types

## In the app overall:
Users can either be a student or adult. This is the “user type”.
Users can also either be admins or not. This is the “admin flag”

## Clubs can have members.
Members can either be club admins or not. Members can also have roles in the club that can be displayed sometimes to give context. The club admins can specify roles. Club admins should be able to manage club settings.
Adults in a club should be called faculty advisors.
The displayed terms for the users to see should be (club members, club leaders, and faculty advisors)

So, the user entity should have:
- email
- first_name
- last_name
- phone
- user_type (student, adult)
- is_admin
- profile_photo_id
(plus fields for account verification)

There should be a “club_membership” table which has
- user_id
- club_id
- is_club_admin
- role (string, like “Treasurer”)
- notification_setting (“everything”, “just_for_you”, “nothing”)

# Becoming a user
Anyone with an email address of “students.hackleyschool.org” or “hackleyschool.org” should be able to register. These domains should be configurable as app-wide settings.

The reset password flow should automatically create a new user if the user does not exist, if the email has one of these domains. The domain-list should be part of the general site settings.

If a user has an email with “students.hackleyschool.org”, they should be a “student”. 
If a user has an email with the domain “hackleyschool.org”, they should be an “adult”.

There may be adults that are advisors to Hackley clubs that do not have a hackleyschool.org address. It isn’t clear whether this is necessary, though, so for now, let’s not allow this.

# User flows

## Opening the app
- If the user is already logged in, the site should display the homepage for the user.
- If the user is not already logged in, the site should flash an “open-app-quote” for a few seconds, cycling between the following quotes (but adjustable over time):
“Go forth and spread beauty and light.”
“United, we help one another.”
“Character is higher than intellect.”
“Enter here to be and find a friend.”
… then, the user should be taken to a login page.

## Login page.
The login page should show a logo (configurable in the site settings page) and allow the user to enter their username and password and click “Login”. There should be a link underneath “Create Account” that begins the create account flow and a “Reset Password” link which starts the “Reset Password” flow. After logging in, the user should be able to be taken to a homepage. 

### Deep Link support
If a user deep links to a page on the site but isn’t logged in, the site should redirect to the login page, but the destination should be preserved so that after login, the user is taken to the original page instead. This deep link url should be passed through the login flow and survive error handling.

## Account Creation

This should be a wizard with a step indicator.

### Page 1: “What is your school email?”
There should be a single input box with placeholder text “Enter email here.”
If the email doesn’t match the allowed domains, it should fail with an error message that their email domain isn’t allowed.

### Page 2: “Enter a password:”
There should be an input box with placeholder text “Enter password here.” and another input box that says “Confirm Password”. Each should have a small icon next to it that looks like an eye that shows the typing.

The password should be at least 4 characters and it cannot be their username from their email. They should be able to see the password they have entered and a box underneath 

If the user already has an account in the system, the system should try to log them in with the email and password. If that works, it should go to the homepage with a message “You have been logged in.”

If there isn’t an account already, it should send an email to the user to confirm their email with a link to confirm the email and be logged into the site, and it should display a message “You’re almost done. — Please check your email and click on the verification link.”

### Page 3: “Email verified, now… What is your name?”
Underneath the description should say “Be yourself – we use real names on Clubz”. The keyboard should switch back to a regular keyboard and there should be two text boxes. The first should have placeholder text hint that says “First Name” and the second should have placeholder text hint that says “Last Name”.

### Page 4: “Add a profile photo”
This should allow users to add a photo. On mobile, it should support taking a new photo or using a photo from their camera role. On desktop, it should support uploading a file. They should be able to position this photo (zoom in, zoom out, drag) to fit a profile bubble (circle).  They should be able to “go back” to choose a different photo which should take them to the original page.

Submitting this should take the user to the next page.

### Page 5: “What is your phone number?”

There should be an easy way to enter the phone number, and as the user types, the page should auto-format the number so that it is easy to read.  There should be a button to “Verify phone number” and a link underneath to “Do this later.” which should take the user to the homepage.

### Page 6 “Enter your code”
Should say “Enter your code” and underneath should say “We just sent a code to __[the number inputted]___.” There should be an input box to enter a six digit code. And a link “Didn’t get a code?  Tap to resend.”.  The input box should auto-submit after six numbers so that the user doesn’t have to click submit.  It should be big and clear.

If the code is invalid, the user should be prompted to re-enter the code.  If the code is valid, the user should be redirected to the homepage.

## Forgot my password.
/forgot_password.php.  Generates and emails a link to /reset_password.php with a token that allows a user to reset their password.  If the email matches one of the whitelisted email domains but the user does not have an account, it should prompt the user to create an account as part of this flow or retype their email.

### Logout
There should be a logout link available from the top menu.


## Events/Calender
The calendar page Should be titled “Event Activity” bold should be the month and on the left should be the day and next to the day should be a floating description of the event 

## Site Design

### Thin left menu with icons.
On the left, there should be a thin, vertical column of icons.  They should be:
- scrollable set of club profile photos (a large use-case is selecting a club to participate in)
- calendar icon (this should take the user to their calendar)
- admin icon (only for app admins)
- profile photo (to edit your profile)

On desktop the calendar, profile photo and admin icons should be on the top right instead of the bottom left.  The club list should be longer and just appear without scrolling.

Clicking on a club profile photo should open the “Club Menu” for that club from the left.
Clicking on the calendar icon should take the user to the calendar page.
Clicking on the admin icon should open an “Admin Menu” from the left.
Clicking on the profile photo should go to the “My profile” page.

### My profile page
Here users should be able to edit their information - first name, last name, phone number, profile photo.  They should also see a “Logout” button here on this page.

### Admin Menu
This should be “App Settings”, “Manage Clubs”, “Activity Log”, “Email Log”, and this will grow over time.

### Club menu
This is just to the left of the thin, left menu with icons.  On desktop, this should always appear.  On mobile, this should appear when a user clicks on a particular club icon on the left menu.

- Club name (in bold, not a link), settings icon on right (within this left club menu) or “...” for menu if admin.  Admin menu should be “Club Settings”, “My settings”.
- “Members (N)” [+invite]
- Meets {meeting time + place description} 
- “Club Info”
Events (heading)
- Event #1
- Event #2
…
Chat Threads
- Chat Thread #1
- Chat Thread #2
…

## Pages - Clubs

### Club Events Page
This should be the same as the calendar page, but there should be a club heading and only events for this club.  There should be a button on the right that says “Create Event”.

### Club Create Event Page
Someone should be able to create an event here, by specifying:
- Event Name
- Starts at
- Ends at
- Location Name
- Location address
- Google maps location link (optional)
- Description
- Event image (optional)

### Club Info Page
People should be able to view information about the club.  This is:
- Hero image (edit link if admin)
- Name: {Club Name}... Edit button on right if admin, should take user to clubs/edit.php?id={...}
- Meets: When and where does it meet
- Description

### Club Settings Page
This is where an admin should be able to change the following settings:
- Secret
Admins should be able to add/edit that club icon (photo), club description, club meets (meeting info)

### Club Members Page
This should be a list of club members with profile picture on left, then a column for name, role, email, phone, then action buttons on the right for club admins.  For admins, the action buttons should be “Make Admin” (if not admin), “Edit Role”, and “Remove”

### Event Detail Page
- RSVP line should be on top.
… If RSVP’d a statement “You have RSVP’d YES.  edit link”
… If not RSVP’d: Buttons: “Next time”, “Interested”, “Going”.  (next time = no, interested = maybe, going = yes)
- Hero image for event
- Date and time like “Nov 2, 4:30pm - 5:30pm”
- Club name
- Event title, in bold. (Admins should see a “Edit Event” button on right)
- Location
- Facepile of people going (if anyone is going)
… Admins should see an “Add RSVP” link here where they can RSVP for other people.
… “Add to google calendar” link.

## Pages - general user pages

### Calendar Page
This should be a list of upcoming events, by month.

The format should be:
- [Month name, bolded]
- [event card/date sets for that month]

An event card/date set should be:
- A thin, left column with the three letter month abbreviation and the date like NOV 02 (two lines to save space, small font for the month, larger font for the date number)
- The event card.

The event card should be:
- Hero image for event
- Date and time like “Nov 2, 4:30pm - 5:30pm”
- Event title, in bold.
- Location
- Facepile of people going (if anyone is going)
- RSVP line.
… If RSVP’d a statement “You have RSVP’d YES.  edit link”
… If not RSVP’d: Buttons: “Next time”, “Interested”, “Going”.  (next time = no, interested = maybe, going = yes)
… “Add to google calendar” link.
- Club name line (icon, then name)

The card /date sets should float so that on desktop they use the space.
On mobile, they should just be a list of cards vertically under the month header.

Clicking on the title or hero image of a card should open an event description page.


—---

## Pages - Admin

### Actiity Log

### Settings

### Email Log

### User Management
The user management lists all of the user of the system and allows the admin to click on them to edit their meta-data. 

There should be a admin/users/edit.php page where an admin can go and edit the metadata for the user, and there should be actions like "Send Password Reset Email", "edit profile photo" (I want to use the same patterns that users use when edit an account to edit a profile photo and the other data too.)

# Design Notes

## Colors.
The app colors should primarily feature a clean, modern aesthetic recognized by vibrant coral/orange and a purple/dark blue gradient. Inside the app, the interface should be generally light with white backgrounds, dark text, and soft pastel accents for chat bubbles and UI elements to encourage a clean, organized, and inviting community atmosphere. App Interface: White backgrounds (Light Mode). Overall Vibe: Friendly, modern, and uncluttered. In the future, we may implement a “Dark mode”, so please make it easy for the core colors to be specified as a palette. Any “next button” that would take the user to the next page should be in #038BFF.

## Fonts.
I want the main font of the app to be BD Megalona Extra Light. This font should be used for titles, and when the app is asking the user to do something. Normal fonds should be in BB Noname Pro Regular

## Bolding and italicizing
Any text with instructions to the user should bold and italicize the most important part of that text so that it is easy to read. An example of this is “What’s your number?” or “Enter your code.” Anything text that is bold and italicized should be in BD Megalona Extra Light.

# Architectural notes
1. The application is a PHP / MYSQL application.  (Eventually, we'll add an "app" for iOS and Android, but for now it's just a web application)
2. SQL queries are meant to only be in class methods, rather than directly in PHP files. for new code, please either add new SQL code within a method of an existing class or create a new class and put the SQL code there.
3. The database schema should be documented in a file schema.sql.
4. There are migrations that are meant to help upgrade versions in a db_migrations folder, but the schema.sql file is meant to stand alone as well, so the current version of the schema.sql file at any time should not need any migrations.
5. There should be an activity log table in the database which logs all write actions and logins.
IMPORTANT: When making database changes, always update schema.sql to reflect the current state. The schema.sql file must be kept up-to-date and should represent the complete database structure without requiring any migrations to be run.  Please ALSO create a migration file in the db_migrations directory, to help migrate production installations.
6. There should be an “email_log” that logs all email sent.
7. There is a SQL file that I’d like you to use to help which is the basic structure for another application that was built (different use-case, but some things similar).

## File structure
lib/
lib/UserManagement.php
lib/EventManagement.php
clubs/index.php
clubs/add_eval.php
clubs/add.php
clubs/edit.php
clubs/edit_eval.php
clubs/remove_eval.php
clubs/events/calendar.php
clubs/events/event.php
clubs/events/create.php
clubs/info.php
clubs/settings.php
clubs/members.php
admin/settings.php
login.php
users/create_flow/step_1.php
users/create_flow/step_2.php
users/forgot_password.php
logout.php
profile/index.php
profile/edit.php
profile/edit_eval.php
profile/edit_picture.php
conversations/… (but we won’t do this now)

## Database Writes

Database writes should only happen in methods of classes that are object management classes.  The public methods on these classes should take a "UserContext" object, which should specify the user id, whether they're an admin or not, and whether they're logged in via super.

There should be a UserContext class that exposes the method:
UserContext::getLoggedInUserContext()
... which should return a user context object that can be passed to these functions.

The UserContext is important because all writes to the database should also write to the ActivityLog, and the ActivityLog will need this information.

## Template files to learn best practices from

I have copied the following files as templates / best practices from another 
application that works well into dev_notes/

dev_notes/lib/ActivityLog.php
dev_notes/lib/Application.php
dev_notes/lib/EmailLog.php
dev_notes/lib/Files.php
dev_notes/lib/mailer.php
dev_notes/lib/UserContext.php
dev_notes/config.local.php.example

## Images
1. There should be an "images" table in the database which stores blobs of data by id.
2. Profile photos should reference an "image_id"
3. Profile photos should be displayed with a "reder_image.php" file passing in the id.
4. When rendering a link for an image like this, the system should try to cache the file in the file system in a cache/ directory and instead of displaying the render_image.php link, it should show the cache link.
To clarify - I want the image tag written out with the cached image or render_image.php.  I don't want render_image.php to try to hit the cache.   

## Security notes
1. Forms are protected with CSRF tokens.
2. Passwords have reasonable constraints to disallow weak passwords.
3. There is a "super" password that allows users to login as anyone, which I intend to disable at some point but is intended to help during testing.
4. There is a config.local.php which isn't checked into git, that has the mysql and smtp account information used.

## Data Model Notes
1. The data model is best understood by reading schema.sql, and new features of the data model should be written to this file and also a database_migration sql file, so the schema.sql file should always be up to date, but each new change should have a migration file so it can be added to the existing system.

When we start, we won’t have a data model, so please use the dev_notes/starting_template.sql

Other Core Data Entities:
- clubs
- memberships
- conversations
- messages 

## Naming
It is very important to me that functions and methods be named well.  The name of a method should express its intent.  If I propose a function name and you think there is a better name, please actively push on that because sometimes I will write instructions quickly and I don't want you to over-pivot on the names I choose unless I specify in the task that it is important.

## Modal Dialog Implementation Pattern
When implementing modal dialogs that require server-side data or processing, follow this separation of concerns pattern:
(1) Modal UI: Include modal HTML and JavaScript in the main page via UI manager classes (e.g., EventUIManager) so users get consistent modal experiences across all relevant pages
(2) AJAX Endpoints: Create dedicated PHP endpoints specifically for modal functionality (e.g., `admin_event_emails.php`, `event_attendees_export.php`) that return JSON responses. These endpoints should contain only the server-side logic needed by the modal, not full page rendering
(3) JavaScript Integration: Modal JavaScript should make AJAX calls to these dedicated endpoints rather than posting back to the current page. This keeps modal logic separate from page-specific logic, allows modals to work consistently across multiple pages, and makes the codebase more maintainable. The modal JavaScript handles success/error responses, updates modal content, and provides user feedback. Direct page links (like "Edit Event" or "Manage Volunteers") should still link directly to their respective pages without modals.

## Ajax endpoing html fragments
Ajax endpoints in general should return html fragments for the part of the page they affect so that they can replace that part of the page's html without having a full page reload.  Those parts of the page should be functionalized so that the ajax page can generate the HTML with the same logic as the original page itself.  The page should replace a section of html with the fragment returned on success and on error should put the error string in an appropriate place and handle the error in whatever way makes sense.

## Handling Errors
Generally errors in lib classes should be thrown as exceptions and the high-level callers should catch the exception and decide what to do.  Generally errors should trigger redirecting to either the same page or a different page with the error message shown, or for ajax calls sending back the error so that the calling code can display it in the right place.

Also - errors should not be swallowed!!! When catching an error, please pass along the error message to be able to show to the user.

## Single concerns per file
Some of the files in the current system have if branches at their top which handle form evaluations of the file.  This is not a pattern I want to continue.  If PHP file 1 is a form which evalutes, it should evaluate to PHP file 2.  Or if it calls an ajax query, that should be PHP file 3.  In other words, I want to prefer to not have one file have more than one purpose.

## GET vs POST
Web requests that don't modify data should generally be GET requests.  Web requests that modify persistent data (not logging to disk or traffic logging, but modifying important data in the database) should generally be POST requests.

## cachebusting
CSS and JS files should use a cachebusting technique so that updates get passed through.  Right now, the technique is to use the file modification time as a version strategy and append a querystring variable with the file modification time.

## On thoroughness

Please make sure when you call a function that it actually exists.  Make sure that if you change something you consider all the implications of the change.

## On checking your work
Anytime you change a file, you should check a few things:
- That when you call a function, it exists and the signature is what you expect
- When you call a function that fetches a set of data elements, you must absolutely check to see that the function exists and the signature is correct.  I've had an issue in the past with this particular type of call.
- That the file parses correctly (ie, it's unacceptable for there to be syntax errors for your changes)

# Order of Implementation
1. Users - login, logout, edit profile, menu with basic application infrastructure - activity log, email log, config.local.php
2. Clubs - join clubs, admin add club, club membership management, club settings, etc.
3. Events
4. Conversations
Features that we will not implement at first
- Moderation
- SMS messaging
- Notifications

## Non-goals (for now)

- No mobile apps
- No real-time websockets (use polling)
- No external integrations beyond email

## Edge Cases

- User RSVPs twice → should update, not duplicate
- Event deleted → RSVPs cascade delete
- User leaves club → should not see future events
- Admin removed → permissions revoked immediately
