-- Example for a local administrator. Hosting panels often create the database/user instead.
-- Replace the password before execution. Do not run against a production database.
CREATE DATABASE testlab_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'testlab_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_DB_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER, REFERENCES
  ON testlab_shop.* TO 'testlab_app'@'localhost';
-- After installation, remove migration privileges or use a separate migration user.
-- REVOKE CREATE, INDEX, ALTER, REFERENCES ON testlab_shop.* FROM 'testlab_app'@'localhost';
