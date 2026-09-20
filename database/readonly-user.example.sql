-- Instructor-managed SQL account for a trusted lab machine / JDBC connection.
-- Replace host and password. Do not expose port 3306 to the public Internet.
CREATE USER 'testlab_reader'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_READER_PASSWORD';
GRANT SELECT ON testlab_shop.workspaces TO 'testlab_reader'@'127.0.0.1';
GRANT SELECT ON testlab_shop.products TO 'testlab_reader'@'127.0.0.1';
GRANT SELECT ON testlab_shop.orders TO 'testlab_reader'@'127.0.0.1';
GRANT SELECT ON testlab_shop.order_items TO 'testlab_reader'@'127.0.0.1';
-- This intentionally does NOT grant SELECT on users/api_tokens/login_limits.
-- Row-level classroom isolation is application-level; this SQL account can read all workspaces.
-- Use separate databases/users per group when SQL-level isolation is required.
