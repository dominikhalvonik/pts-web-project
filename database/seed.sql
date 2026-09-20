-- LOCAL CLASSROOM DATA ONLY. Import once after schema.sql into empty tables.
-- Accounts: student@example.test, student2@example.test. Password: Student123!
-- Never use these public demonstration credentials on an unrestricted public server.
SET NAMES utf8mb4;
START TRANSACTION;
INSERT INTO workspaces (code,name) VALUES ('demo','Študent Demo');
SET @demo_workspace = LAST_INSERT_ID();
INSERT INTO users (workspace_id,name,email,password_hash) VALUES (@demo_workspace,'Študent Demo','student@example.test','$2y$12$hUu2VvfNbaKQ.kEG1TZTPuJd3eBrLKaSn98AsHiCd3tnYsZPS/0XK');
INSERT INTO users (workspace_id,name,email,password_hash) VALUES (@demo_workspace,'Druhý Študent','student2@example.test','$2y$12$hUu2VvfNbaKQ.kEG1TZTPuJd3eBrLKaSn98AsHiCd3tnYsZPS/0XK');
INSERT INTO products (workspace_id,sku,name,description,category,price_cents,stock) VALUES (@demo_workspace,'KB-001','Mechanická klávesnica','Kompaktná klávesnica s USB pripojením.','Periférie',3490,100);
INSERT INTO products (workspace_id,sku,name,description,category,price_cents,stock) VALUES (@demo_workspace,'MS-001','Bezdrôtová myš','Ľahká ergonomická myš na každodennú prácu.','Periférie',1990,100);
INSERT INTO products (workspace_id,sku,name,description,category,price_cents,stock) VALUES (@demo_workspace,'USB-001','USB-C kábel','Dvojmetrový kábel na nabíjanie a prenos dát.','Príslušenstvo',1250,100);
INSERT INTO products (workspace_id,sku,name,description,category,price_cents,stock) VALUES (@demo_workspace,'MON-001','Monitor 24 palcov','IPS monitor do pracovného a študijného prostredia.','Monitory',14900,20);
INSERT INTO products (workspace_id,sku,name,description,category,price_cents,stock) VALUES (@demo_workspace,'CAM-001','Webová kamera','Kamera s krytkou objektívu na videohovory.','Periférie',5900,40);
INSERT INTO products (workspace_id,sku,name,description,category,price_cents,stock) VALUES (@demo_workspace,'PAD-001','Podložka pod myš','Protišmyková podložka v minimalistickom dizajne.','Príslušenstvo',995,100);
INSERT INTO products (workspace_id,sku,name,description,category,price_cents,stock) VALUES (@demo_workspace,'HUB-001','USB-C rozbočovač','Rozbočovač s viacerými portmi. Aktuálne vypredaný.','Príslušenstvo',2990,0);
INSERT INTO products (workspace_id,sku,name,description,category,price_cents,stock) VALUES (@demo_workspace,'BAG-001','Obal na notebook','Ochranný obal na notebook s uhlopriečkou 14 palcov.','Príslušenstvo',2500,30);
COMMIT;
