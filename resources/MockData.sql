/* Products Table */
INSERT INTO products (
    sku,
    name,
    category,
    product_type,
    price,
    stock,
    short_description,
    description,
    image_path,
    image_alt,
    status
)
VALUES

(
    'PG-PHY-001',
    'Daily Reflection Journal',
    'Personal Growth',
    'physical',
    28.00,
    80,
    'A hardcover journal for daily reflection and gratitude.',
    'A simple journal designed to help users record daily thoughts, gratitude and personal reflections.',
    'images/products/daily-reflection-journal.jpg',
    'Daily Reflection Journal',
    'published'
),

(
    'PG-PHY-002',
    'Habit Tracker Notebook',
    'Personal Growth',
    'physical',
    24.00,
    65,
    'A notebook for tracking daily habits and routines.',
    'A practical notebook that helps users monitor their habits and maintain consistent routines.',
    'images/products/habit-tracker-notebook.jpg',
    'Habit Tracker Notebook',
    'published'
),

(
    'PG-PHY-003',
    'Goal Setting Card Set',
    'Personal Growth',
    'physical',
    32.00,
    45,
    'Cards containing prompts for setting personal goals.',
    'A collection of simple prompt cards designed to help users identify and organise their personal goals.',
    'images/products/goal-setting-cards.jpg',
    'Goal Setting Card Set',
    'published'
),

(
    'PG-DIG-004',
    '30-Day Habit Tracker PDF',
    'Personal Growth',
    'digital',
    12.00,
    999,
    'A downloadable PDF for tracking habits over 30 days.',
    'A printable digital habit tracker that helps users monitor their progress throughout a 30-day period.',
    'images/products/30-day-habit-tracker.jpg',
    '30-Day Habit Tracker PDF',
    'published'
),

(
    'PG-DIG-005',
    'Personal Goal Planner Template',
    'Personal Growth',
    'digital',
    18.00,
    999,
    'An editable template for planning personal goals.',
    'A downloadable planning template for recording personal goals and tracking progress.',
    'images/products/personal-goal-planner.jpg',
    'Personal Goal Planner Template',
    'published'
),

(
    'PG-DIG-006',
    'Self-Reflection Workbook PDF',
    'Personal Growth',
    'digital',
    22.00,
    999,
    'A downloadable workbook for personal reflection.',
    'A digital workbook containing simple reflection exercises for personal development.',
    'images/products/self-reflection-workbook.jpg',
    'Self-Reflection Workbook PDF',
    'draft'
),

(
    'LEA-PHY-001',
    'Leadership Skills Workbook',
    'Leadership',
    'physical',
    38.00,
    60,
    'A workbook for developing everyday leadership skills.',
    'A printed workbook containing exercises and activities for developing practical leadership skills.',
    'images/products/leadership-skills-workbook.jpg',
    'Leadership Skills Workbook',
    'published'
),

(
    'LEA-PHY-002',
    'Leadership Prompt Card Set',
    'Leadership',
    'physical',
    34.00,
    40,
    'Prompt cards for leadership discussions and reflection.',
    'A physical card set designed to encourage thoughtful leadership discussions and team reflection.',
    'images/products/leadership-prompt-cards.jpg',
    'Leadership Prompt Card Set',
    'published'
),

(
    'LEA-DIG-003',
    'New Manager Starter Guide',
    'Leadership',
    'digital',
    25.00,
    999,
    'A downloadable guide for newly appointed managers.',
    'A practical digital guide introducing essential management responsibilities and leadership practices.',
    'images/products/new-manager-guide.jpg',
    'New Manager Starter Guide',
    'published'
),

(
    'LEA-DIG-004',
    'One-to-One Meeting Template',
    'Leadership',
    'digital',
    15.00,
    999,
    'A reusable template for manager-employee meetings.',
    'An editable digital template for organising structured one-to-one meetings and recording discussion points.',
    'images/products/one-to-one-template.jpg',
    'One-to-One Meeting Template',
    'published'
),

(
    'LEA-DIG-005',
    'Performance Review Template',
    'Leadership',
    'digital',
    20.00,
    999,
    'A template for conducting employee performance reviews.',
    'A digital template designed to organise employee performance discussions and feedback.',
    'images/products/performance-review-template.jpg',
    'Performance Review Template',
    'draft'
),

(
    'COA-PHY-001',
    'Coaching Conversation Card Deck',
    'Coaching',
    'physical',
    36.00,
    50,
    'Cards for encouraging meaningful coaching conversations.',
    'A physical card deck containing discussion prompts for coaching sessions and meaningful conversations.',
    'images/products/coaching-card-deck.jpg',
    'Coaching Conversation Card Deck',
    'published'
),

(
    'COA-PHY-002',
    'Coaching Session Notebook',
    'Coaching',
    'physical',
    26.00,
    55,
    'A notebook for recording coaching sessions and notes.',
    'A practical notebook for coaches to record session notes, observations and follow-up actions.',
    'images/products/coaching-session-notebook.jpg',
    'Coaching Session Notebook',
    'published'
),

(
    'COA-PHY-003',
    'Team Facilitation Kit',
    'Coaching',
    'physical',
    68.00,
    20,
    'A physical toolkit for organising team workshops.',
    'A collection of workshop materials designed to support group activities and team facilitation.',
    'images/products/team-facilitation-kit.jpg',
    'Team Facilitation Kit',
    'draft'
),

(
    'COA-DIG-004',
    'Coaching Session Planner',
    'Coaching',
    'digital',
    18.00,
    999,
    'A digital planner for organising coaching sessions.',
    'A downloadable planner for recording session objectives, discussion topics and follow-up tasks.',
    'images/products/coaching-session-planner.jpg',
    'Coaching Session Planner',
    'published'
),

(
    'COA-DIG-005',
    'Workshop Facilitation Guide',
    'Coaching',
    'digital',
    28.00,
    999,
    'A downloadable guide for planning workshops.',
    'A practical digital guide containing basic steps for organising and conducting workshops.',
    'images/products/workshop-facilitation-guide.jpg',
    'Workshop Facilitation Guide',
    'published'
),

(
    'PRO-PHY-001',
    'Weekly Desk Planner',
    'Productivity',
    'physical',
    22.00,
    90,
    'A paper planner for organising weekly tasks.',
    'A simple paper desk planner for organising weekly schedules, priorities and activities.',
    'images/products/weekly-desk-planner.jpg',
    'Weekly Desk Planner',
    'published'
),

(
    'PRO-PHY-002',
    'Focus Desk Notepad',
    'Productivity',
    'physical',
    16.00,
    100,
    'A notepad for recording daily priorities.',
    'A compact desk notepad designed to help users record important tasks and daily priorities.',
    'images/products/focus-desk-notepad.jpg',
    'Focus Desk Notepad',
    'published'
),

(
    'PRO-PHY-003',
    'Productivity Planner',
    'Productivity',
    'physical',
    30.00,
    70,
    'A planner for organising tasks and improving productivity.',
    'A printed planner designed to help users organise tasks, manage schedules and review progress.',
    'images/products/productivity-planner.jpg',
    'Productivity Planner',
    'draft'
),

(
    'PRO-DIG-004',
    'Time Blocking Template',
    'Productivity',
    'digital',
    12.00,
    999,
    'An editable template for scheduling daily activities.',
    'A downloadable template that helps users divide their time into organised working sessions.',
    'images/products/time-blocking-template.jpg',
    'Time Blocking Template',
    'published'
),

(
    'PRO-DIG-005',
    'Task Prioritisation Template',
    'Productivity',
    'digital',
    14.00,
    999,
    'A template for organising tasks by importance.',
    'An editable digital template for categorising tasks and identifying important priorities.',
    'images/products/task-prioritisation-template.jpg',
    'Task Prioritisation Template',
    'published'
),

(
    'PRO-DIG-006',
    'Weekly Productivity Planner',
    'Productivity',
    'digital',
    18.00,
    999,
    'A downloadable planner for weekly productivity tracking.',
    'A digital planner for recording weekly goals, tasks and productivity progress.',
    'images/products/weekly-productivity-planner.jpg',
    'Weekly Productivity Planner',
    'published'
);

/*Users Table */ 
INSERT IGNORE INTO users
(name, email, password_hash, role)
VALUES
(
    'Alice Tan',
    'alice@example.com',
    '$2y$12$R7ecNGhiIF74ZQAsjsaQQu2qkstsCQ7YRqUY2gYDGQMEGI2DkKyYS', /* "ShopnestDemo123!" */
    'customer'
),
(
    'Daniel Lim',
    'daniel@example.com',
    '$2y$12$R7ecNGhiIF74ZQAsjsaQQu2qkstsCQ7YRqUY2gYDGQMEGI2DkKyYS',
    'customer'
),
(
    'Sarah Lee',
    'sarah@example.com',
    '$2y$12$R7ecNGhiIF74ZQAsjsaQQu2qkstsCQ7YRqUY2gYDGQMEGI2DkKyYS',
    'customer'
),
(
    'ShopNest Admin',
    'admin@example.com',
    '$2y$12$R7ecNGhiIF74ZQAsjsaQQu2qkstsCQ7YRqUY2gYDGQMEGI2DkKyYS',
    'admin'
);

/* Carts Table */
INSERT IGNORE INTO carts (user_id)
SELECT id
FROM users
WHERE email IN (
    'alice@example.com',
    'daniel@example.com',
    'sarah@example.com'
);

/* Cart Items Table */
INSERT IGNORE INTO cart_items
(cart_id, product_id, quantity)

SELECT c.id, p.id, 2
FROM carts c
JOIN users u ON u.id = c.user_id
JOIN products p ON p.sku = 'PG-PHY-001'
WHERE u.email = 'alice@example.com';

INSERT IGNORE INTO cart_items
(cart_id, product_id, quantity)

SELECT c.id, p.id, 1
FROM carts c
JOIN users u ON u.id = c.user_id
JOIN products p ON p.sku = 'PRO-DIG-004'
WHERE u.email = 'alice@example.com';

INSERT IGNORE INTO cart_items
(cart_id, product_id, quantity)

SELECT c.id, p.id, 1
FROM carts c
JOIN users u ON u.id = c.user_id
JOIN products p ON p.sku = 'LEA-PHY-001'
WHERE u.email = 'daniel@example.com';

INSERT IGNORE INTO cart_items
(cart_id, product_id, quantity)

SELECT c.id, p.id, 2
FROM carts c
JOIN users u ON u.id = c.user_id
JOIN products p ON p.sku = 'COA-DIG-004'
WHERE u.email = 'daniel@example.com';

INSERT IGNORE INTO cart_items
(cart_id, product_id, quantity)

SELECT c.id, p.id, 1
FROM carts c
JOIN users u ON u.id = c.user_id
JOIN products p ON p.sku = 'PRO-PHY-001'
WHERE u.email = 'sarah@example.com';

/* Wishlist Items Table */
INSERT IGNORE INTO wishlist_items
(user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'PG-PHY-002'
WHERE u.email = 'alice@example.com';

INSERT IGNORE INTO wishlist_items
(user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'LEA-DIG-003'
WHERE u.email = 'alice@example.com';

INSERT IGNORE INTO wishlist_items
(user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'PRO-PHY-001'
WHERE u.email = 'alice@example.com';

INSERT IGNORE INTO wishlist_items
(user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'PG-PHY-003'
WHERE u.email = 'daniel@example.com';

INSERT IGNORE INTO wishlist_items
(user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'COA-PHY-001'
WHERE u.email = 'daniel@example.com';

INSERT IGNORE INTO wishlist_items
(user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'PRO-DIG-005'
WHERE u.email = 'daniel@example.com';

INSERT IGNORE INTO wishlist_items
(user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'PG-PHY-001'
WHERE u.email = 'sarah@example.com';

INSERT IGNORE INTO wishlist_items
(user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'COA-DIG-005'
WHERE u.email = 'sarah@example.com';

/* Product Likes Table */
INSERT IGNORE INTO product_likes (user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'PG-PHY-001'
WHERE u.email IN (
    'alice@example.com',
    'daniel@example.com',
    'sarah@example.com'
);

INSERT IGNORE INTO product_likes (user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'LEA-PHY-001'
WHERE u.email IN (
    'alice@example.com',
    'sarah@example.com'
);

INSERT IGNORE INTO product_likes (user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'COA-PHY-001'
WHERE u.email IN (
    'daniel@example.com',
    'sarah@example.com'
);

INSERT IGNORE INTO product_likes (user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'PRO-PHY-001'
WHERE u.email = 'alice@example.com';

INSERT IGNORE INTO product_likes (user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'PRO-DIG-004'
WHERE u.email = 'daniel@example.com';

INSERT IGNORE INTO product_likes (user_id, product_id)

SELECT u.id, p.id
FROM users u
JOIN products p ON p.sku = 'PG-PHY-003'
WHERE u.email = 'sarah@example.com';

/* Orders Table */
INSERT IGNORE INTO orders (
    order_number,
    user_id,
    customer_name,
    customer_email,
    shipping_address,
    city,
    postcode,
    subtotal,
    shipping_fee,
    total,
    payment_status,
    fulfillment_status,
    cancellation_status,
    is_demo_payment
)
SELECT
    'SN-DEMO-1001',
    id,
    'Alice Tan',
    'alice@example.com',
    '12 Jalan Example 1',
    'Kuala Lumpur',
    '50000',
    80.00,
    5.00,
    85.00,
    'paid',
    'delivered',
    'none',
    1
FROM users
WHERE email = 'alice@example.com';


INSERT IGNORE INTO orders (
    order_number,
    user_id,
    customer_name,
    customer_email,
    shipping_address,
    city,
    postcode,
    subtotal,
    shipping_fee,
    total,
    payment_status,
    fulfillment_status,
    cancellation_status,
    is_demo_payment
)
SELECT
    'SN-DEMO-1002',
    id,
    'Daniel Lim',
    'daniel@example.com',
    '25 Jalan Example 2',
    'Petaling Jaya',
    '46000',
    53.00,
    5.00,
    58.00,
    'paid',
    'shipped',
    'none',
    1
FROM users
WHERE email = 'daniel@example.com';


INSERT IGNORE INTO orders (
    order_number,
    user_id,
    customer_name,
    customer_email,
    shipping_address,
    city,
    postcode,
    subtotal,
    shipping_fee,
    total,
    payment_status,
    fulfillment_status,
    cancellation_status,
    is_demo_payment
)
SELECT
    'SN-DEMO-1003',
    id,
    'Sarah Lee',
    'sarah@example.com',
    '8 Jalan Example 3',
    'Shah Alam',
    '40000',
    54.00,
    5.00,
    59.00,
    'pending',
    'processing',
    'none',
    1
FROM users
WHERE email = 'sarah@example.com';


INSERT IGNORE INTO orders (
    order_number,
    user_id,
    customer_name,
    customer_email,
    shipping_address,
    city,
    postcode,
    subtotal,
    shipping_fee,
    total,
    payment_status,
    fulfillment_status,
    cancellation_status,
    is_demo_payment
)
SELECT
    'SN-DEMO-1004',
    id,
    'Alice Tan',
    'alice@example.com',
    '12 Jalan Example 1',
    'Kuala Lumpur',
    '50000',
    48.00,
    5.00,
    53.00,
    'paid',
    'delivered',
    'none',
    1
FROM users
WHERE email = 'alice@example.com';

/* Order Items Table */
INSERT INTO order_items (
    order_id,
    product_id,
    product_name,
    product_type,
    unit_price,
    quantity
)
SELECT
    o.id, p.id, p.name, p.product_type, p.price, 2
FROM orders o
JOIN products p ON p.sku = 'PG-PHY-001'
WHERE o.order_number = 'SN-DEMO-1001'
AND NOT EXISTS (
    SELECT 1 FROM order_items oi
    WHERE oi.order_id = o.id
    AND oi.product_id = p.id
);


INSERT INTO order_items (
    order_id, product_id, product_name,
    product_type, unit_price, quantity
)
SELECT
    o.id, p.id, p.name, p.product_type, p.price, 1
FROM orders o
JOIN products p ON p.sku = 'PG-PHY-002'
WHERE o.order_number = 'SN-DEMO-1001'
AND NOT EXISTS (
    SELECT 1 FROM order_items oi
    WHERE oi.order_id = o.id
    AND oi.product_id = p.id
);


INSERT INTO order_items (
    order_id, product_id, product_name,
    product_type, unit_price, quantity
)
SELECT
    o.id, p.id, p.name, p.product_type, p.price, 1
FROM orders o
JOIN products p ON p.sku = 'LEA-PHY-001'
WHERE o.order_number = 'SN-DEMO-1002'
AND NOT EXISTS (
    SELECT 1 FROM order_items oi
    WHERE oi.order_id = o.id
    AND oi.product_id = p.id
);


INSERT INTO order_items (
    order_id, product_id, product_name,
    product_type, unit_price, quantity
)
SELECT
    o.id, p.id, p.name, p.product_type, p.price, 1
FROM orders o
JOIN products p ON p.sku = 'LEA-DIG-004'
WHERE o.order_number = 'SN-DEMO-1002'
AND NOT EXISTS (
    SELECT 1 FROM order_items oi
    WHERE oi.order_id = o.id
    AND oi.product_id = p.id
);


INSERT INTO order_items (
    order_id, product_id, product_name,
    product_type, unit_price, quantity
)
SELECT
    o.id, p.id, p.name, p.product_type, p.price, 1
FROM orders o
JOIN products p ON p.sku = 'COA-PHY-001'
WHERE o.order_number = 'SN-DEMO-1003'
AND NOT EXISTS (
    SELECT 1 FROM order_items oi
    WHERE oi.order_id = o.id
    AND oi.product_id = p.id
);


INSERT INTO order_items (
    order_id, product_id, product_name,
    product_type, unit_price, quantity
)
SELECT
    o.id, p.id, p.name, p.product_type, p.price, 1
FROM orders o
JOIN products p ON p.sku = 'COA-DIG-004'
WHERE o.order_number = 'SN-DEMO-1003'
AND NOT EXISTS (
    SELECT 1 FROM order_items oi
    WHERE oi.order_id = o.id
    AND oi.product_id = p.id
);


INSERT INTO order_items (
    order_id, product_id, product_name,
    product_type, unit_price, quantity
)
SELECT
    o.id, p.id, p.name, p.product_type, p.price, 1
FROM orders o
JOIN products p ON p.sku = 'PRO-PHY-001'
WHERE o.order_number = 'SN-DEMO-1004'
AND NOT EXISTS (
    SELECT 1 FROM order_items oi
    WHERE oi.order_id = o.id
    AND oi.product_id = p.id
);


INSERT INTO order_items (
    order_id, product_id, product_name,
    product_type, unit_price, quantity
)
SELECT
    o.id, p.id, p.name, p.product_type, p.price, 1
FROM orders o
JOIN products p ON p.sku = 'PRO-DIG-004'
WHERE o.order_number = 'SN-DEMO-1004'
AND NOT EXISTS (
    SELECT 1 FROM order_items oi
    WHERE oi.order_id = o.id
    AND oi.product_id = p.id
);

INSERT INTO order_items (
    order_id, product_id, product_name,
    product_type, unit_price, quantity
)
SELECT
    o.id, p.id, p.name, p.product_type, p.price, 1
FROM orders o
JOIN products p ON p.sku = 'PRO-DIG-005'
WHERE o.order_number = 'SN-DEMO-1004'
AND NOT EXISTS (
    SELECT 1 FROM order_items oi
    WHERE oi.order_id = o.id
    AND oi.product_id = p.id
);

/* Product Versions Table */
INSERT IGNORE INTO product_versions (
    product_id,
    version_number,
    created_by,
    actor_name,
    status,
    note,
    snapshot
)
SELECT
    p.id,
    1,
    u.id,
    'ShopNest Admin',
    'historical',
    'Original product version',
    '{"name":"Daily Reflection Journal","price":25.00,"stock":80,"status":"published"}'
FROM products p
JOIN users u ON u.email = 'admin@example.com'
WHERE p.sku = 'PG-PHY-001';


INSERT IGNORE INTO product_versions (
    product_id,
    version_number,
    created_by,
    actor_name,
    status,
    note,
    snapshot
)
SELECT
    p.id,
    2,
    u.id,
    'ShopNest Admin',
    'live',
    'Updated product price',
    '{"name":"Daily Reflection Journal","price":28.00,"stock":80,"status":"published"}'
FROM products p
JOIN users u ON u.email = 'admin@example.com'
WHERE p.sku = 'PG-PHY-001';


INSERT IGNORE INTO product_versions (
    product_id,
    version_number,
    created_by,
    actor_name,
    status,
    note,
    snapshot
)
SELECT
    p.id,
    1,
    u.id,
    'ShopNest Admin',
    'live',
    'Initial product version',
    '{"name":"Leadership Skills Workbook","price":38.00,"stock":60,"status":"published"}'
FROM products p
JOIN users u ON u.email = 'admin@example.com'
WHERE p.sku = 'LEA-PHY-001';

/* Activity Logs Table */
INSERT INTO activity_logs (
    actor_id,
    actor_name,
    action,
    resource_type,
    resource_id,
    detail
)
SELECT
    u.id,
    u.name,
    'Created product',
    'product',
    CAST(p.id AS CHAR),
    'Created Daily Reflection Journal'
FROM users u
JOIN products p ON p.sku = 'PG-PHY-001'
WHERE u.email = 'admin@example.com'
AND NOT EXISTS (
    SELECT 1
    FROM activity_logs a
    WHERE a.action = 'Created product'
    AND a.resource_type = 'product'
    AND CAST(a.resource_id AS UNSIGNED) = p.id
    AND a.actor_id = u.id
);


INSERT INTO activity_logs (
    actor_id,
    actor_name,
    action,
    resource_type,
    resource_id,
    detail
)
SELECT
    u.id,
    u.name,
    'Updated product',
    'product',
    CAST(p.id AS CHAR),
    'Updated Daily Reflection Journal price'
FROM users u
JOIN products p ON p.sku = 'PG-PHY-001'
WHERE u.email = 'admin@example.com'
AND NOT EXISTS (
    SELECT 1
    FROM activity_logs a
    WHERE a.action = 'Updated product'
    AND a.resource_type = 'product'
    AND CAST(a.resource_id AS UNSIGNED) = p.id
    AND a.actor_id = u.id
);


INSERT INTO activity_logs (
    actor_id,
    actor_name,
    action,
    resource_type,
    resource_id,
    detail
)
SELECT
    u.id,
    u.name,
    'Created product',
    'product',
    CAST(p.id AS CHAR),
    'Created Leadership Skills Workbook'
FROM users u
JOIN products p ON p.sku = 'LEA-PHY-001'
WHERE u.email = 'admin@example.com'
AND NOT EXISTS (
    SELECT 1
    FROM activity_logs a
    WHERE a.action = 'Created product'
    AND a.resource_type = 'product'
    AND CAST(a.resource_id AS UNSIGNED) = p.id
    AND a.actor_id = u.id
);


INSERT INTO activity_logs (
    actor_id,
    actor_name,
    action,
    resource_type,
    resource_id,
    detail
)
SELECT
    u.id,
    u.name,
    'Updated order',
    'order',
    CAST(o.id AS CHAR),
    'Updated order fulfillment status'
FROM users u
JOIN orders o ON o.order_number = 'SN-DEMO-1002'
WHERE u.email = 'admin@example.com'
AND NOT EXISTS (
    SELECT 1
    FROM activity_logs a
    WHERE a.action = 'Updated order'
    AND a.resource_type = 'order'
    AND CAST(a.resource_id AS UNSIGNED) = o.id
    AND a.actor_id = u.id
);


INSERT INTO activity_logs (
    actor_id,
    actor_name,
    action,
    resource_type,
    resource_id,
    detail
)
SELECT
    u.id,
    u.name,
    'Customer registration',
    'user',
    CAST(u.id AS CHAR),
    'Customer account created'
FROM users u
WHERE u.email = 'alice@example.com'
AND NOT EXISTS (
    SELECT 1
    FROM activity_logs a
    WHERE a.action = 'Customer registration'
    AND a.resource_type = 'user'
    AND CAST(a.resource_id AS UNSIGNED) = u.id
);

