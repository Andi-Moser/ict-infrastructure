# TeamEats

## Purpose

TeamEats is a very simple app to coordinate lunches.

The problem: In our office, we have instructors and office workers. Instructors only have a limited time for lunch and are
only here for about 5 days a year (consecutive). The lunch is a valuable place to network and chat so we want to push for
everyone to eat together.

Since the instructors have little time for lunch, we want a platform where office workers can suggest ideas (takeaway, hotdogs, etc)
and instructors can register themselves if they are interested. All other communication can be done in person during the breaks.

## How it works

1. Office workers can create an idea for a certain date, selected from a preset list of ideas or just add a custom one.
2. Instructors can register themselves for a certain date and idea. They can also unregister if they change their mind.
3. An email gets sent to the proposer of the idea, sent by the instructor.
3. The app shows the number of registered instructors for each idea and date, so office workers know what how much to buy/order.

## Tech stack

- Frontend: VueJS (CDN version, no build step needed)
  - Styling with Tailwind CSS, but no need for a build step, just include the CDN links in the HTML.
- Backend: Simple PHP API with SQLite database
  - No framework needed, composer is ok.
- Email sending: A relay smtp server only accessible from the office network, no authentication needed.

## Hosting

In a docker container on a self hosted server. The container will run both the PHP backend and serve the VueJS frontend. The SQLite database will be stored in a volume to persist data.

## Authentication

No authentication needed, the app is only used internally and we trust our users to use it responsibly.

There will be a ip whitelist to restrict access to the app to only our office network, this will be implemented via a global nginx.

## Data structure

- `ideas` table:
  - `id` (integer, primary key)
  - `date` (date)
  - `idea` (string)
  - `description` (string, optional)
  - `image_url` (string, optional)
  - `proposed_by` (string)
  - `email` (string)

- `registrations` table:
  - `id` (integer, primary key)
  - `idea_id` (integer, foreign key to `ideas.id`)
  - `name` (string)
  - `comment` (string)
  - `email` (string)
  - `registered_at` (datetime)

## Predefined ideas

There are some predefined ideas for the usual places. These will be hardcoded in a json file to be selected.
Only predefined ideas can have an image, custom ideas will not have an image.