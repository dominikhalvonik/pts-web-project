-- Read the UUID from GUI data-testid="order-id" or from the POST /orders response.
-- Do not find a parallel test's order using MAX(id) or the globally latest row.
SET @order_id = 'REPLACE_WITH_ORDER_UUID';
SELECT id, order_number, status, subtotal_cents, discount_cents,
       shipping_cents, total_cents, currency, coupon_code, delivery_method
FROM orders WHERE id=@order_id;
-- Reference scenario: 8970, 897, 490, 8563 cents; status created.
SELECT sku, product_name, unit_price_cents, quantity, line_total_cents
FROM order_items WHERE order_id=@order_id ORDER BY sku;
-- Reference lines: KB-001 -> 3490 * 2 = 6980; MS-001 -> 1990 * 1 = 1990.
SELECT p.sku,p.stock,w.code AS workspace_code
FROM products p JOIN workspaces w ON w.id=p.workspace_id
JOIN orders o ON o.workspace_id=p.workspace_id
WHERE o.id=@order_id AND p.sku IN ('KB-001','MS-001') ORDER BY p.sku;
-- Fresh isolated fixture after reference order: KB-001 -> 98, MS-001 -> 99.
-- After cancellation: both 100, provided no other orders used this fixture.
