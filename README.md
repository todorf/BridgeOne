# BridgeOne

_- Requirements -_

Composer |
PHP >= 8.1 |
MySQL

_- Setup Guide -_

Clone the git repository:

    git clone https://github.com/todorf/BridgeOne.git

In php.ini, enable **extension=mysqli** if it is not enabled yet.

Inside the cloned repo, run `composer install`.

Go to **config/config.php** and change the database settings and API parameters.

`'api_base_url' => 'https://app.otasync.me/'`

The API base URL must end with a trailing slash (/).

Next, start the MySQL server and run the migration script **schemas\schema.sql** to create the required database and tables.

    mysql -u user_name < path_to_sql_file

Each sync script has an **$id_properties** or **$sync_data** variable that can be modified to fetch data for different properties.

All scripts must be run from the project root!

- **Task 1**

  `php sync_catalog.php`

- **Task 2**

  `php sync_reservations.php --from=2026-01-01 --to=2026-01-31`

- **Task 3**

  `php update_reservation.php --reservation_id=XXXX`

- **Task 4**

  `php generate_invoice.php --reservation_id=XXXX`

- **Task 5**

  Start a local PHP web server with:

  `php -S localhost:8000`

Once the server is started, you can send POST requests to http://localhost:8000/webhooks/otasync.php

You can use cURL to send a request to the endpoint. The request body should include the keys **type** and **data**.

Below are examples of cURL requests:

    curl -X POST http://localhost:8000/webhooks/otasync.php ^
    -H "Content-Type: application/json" ^
    -d "{\"type\":\"reservation_insert\",\"data\":{\"id_reservations\":\"606308\",\"id_properties\":\"6546\",\"id_pricing_plans\":\"432432\", \"status\": \"pending\"}}"

    curl -X POST http://localhost:8000/webhooks/otasync.php ^
    -H "Content-Type: application/json" ^
    -d "{\"type\":\"reservation_update\",\"data\":{\"id_reservations\":\"606308\",\"id_properties\":\"856\",\"id_pricing_plans\":\"23\",\"first_name\":\"Test\", \"last_name\":\"Test Lastname\",\"status\":\"confirmed\"}}"

    curl -X POST http://localhost:8000/webhooks/otasync.php ^
    -H "Content-Type: application/json" ^
    -d "{\"type\":\"reservation_cancel\",\"data\":{\"id_reservations\":\"606308\"}}"
