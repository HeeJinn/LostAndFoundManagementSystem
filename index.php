<?php
require "config/config.php";
require_once 'vendor/autoload.php';

// --- Basic Setup ---
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

// Define the base path of your project.
define('BASE_PATH', '/LostAndFoundManagementSystem');

// *** Make BASE_PATH available in all Twig templates ***
$twig->addGlobal('BASE_PATH', BASE_PATH);

// --- ROUTING LOGIC ---
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = str_replace(BASE_PATH, '', $request_uri);

if (empty($route)) {
    $route = '/';
}

// This makes the '$route' variable available as 'current_route' in all Twig files.
$twig->addGlobal('current_route', $route);

switch ($route) {
    case '/':
    case '/home':
        echo $twig->render('home.html.twig');
        break;

    case '/lostitems':
        try {
            // --- Get Parameters (Search, Filter, Page) ---
            $itemsPerPage = 12;
            $currentPage = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
            // Sanitize search and filter inputs
            $searchTerm = trim(filter_input(INPUT_GET, 'search_query', FILTER_SANITIZE_STRING) ?: '');
            $category = filter_input(INPUT_GET, 'category_filter', FILTER_SANITIZE_STRING) ?: 'all';

            // --- Build WHERE Clause Dynamically ---
            $whereClauses = [];
            $params = []; // Parameters for prepared statement

            if (!empty($searchTerm)) {
                // Search in item name, description, and location
                $whereClauses[] = "(li.item_name LIKE :search OR li.item_description LIKE :search OR li.found_location LIKE :search)";
                $params[':search'] = '%' . $searchTerm . '%';
            }

            if ($category !== 'all' && !empty($category)) {
                // Assuming filtering by category NAME.
                $whereClauses[] = "c.category_name = :category";
                $params[':category'] = $category;
                // If filtering by category ID instead:
                // $categoryId = filter_input(INPUT_GET, 'category_filter', FILTER_VALIDATE_INT);
                // if ($categoryId) {
                //    $whereClauses[] = "li.category_id = :category_id";
                //    $params[':category_id'] = $categoryId;
                // }
            }

            $whereSql = '';
            if (!empty($whereClauses)) {
                $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
            }
            // --- End Building WHERE Clause ---

            // --- Get Total Filtered Item Count ---
            $countSql = "SELECT COUNT(*)
                         FROM lost_items li
                         LEFT JOIN categories c ON li.category_id = c.category_id
                         {$whereSql}"; // Apply the same filters to the count

            $countStmt = $conn->prepare($countSql);
            $countStmt->execute($params); // Execute count query with filter params
            $totalItems = $countStmt->fetchColumn();
            $totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1; // Ensure at least 1 page

            // Ensure current page is valid after filtering and counting
            $currentPage = min($currentPage, $totalPages);
            if ($currentPage < 1) $currentPage = 1; // Recalculate if totalPages became 0 or less
            $offset = ($currentPage - 1) * $itemsPerPage;

            // --- Fetch Filtered Items for Current Page ---
            $sql = "SELECT li.item_id AS id, li.item_name, li.item_description, li.item_image_url,
                           li.item_status, li.found_location, li.reported_at, c.category_name,
                           r.first_name AS reporter_first_name, r.last_name AS reporter_last_name, r.student_id
                    FROM lost_items li
                    LEFT JOIN categories c ON li.category_id = c.category_id
                    LEFT JOIN reporters r ON li.reporter_id = r.reporter_id
                    {$whereSql}  -- Apply filters
                    ORDER BY li.reported_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $conn->prepare($sql);

            // Bind filter parameters (if any)
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            // Bind pagination parameters
            $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            if ($stmt === false) {
                $errorInfo = $stmt->errorInfo();
                error_log("Database Query Failed: " . $errorInfo[2]);
                echo $twig->render('error.html.twig', ['message' => 'Could not retrieve lost items.']);
                exit;
            }

            $itemsForCurrentPage = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Render the full forum page with filtered/paginated data
            // The 'app.request.query.get' in Twig will handle keeping form values
            echo $twig->render('forum.html.twig', [
                'items' => $itemsForCurrentPage,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
            ]);
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            echo $twig->render('error.html.twig', ['message' => 'An unexpected database error occurred.']);
            exit;
        }
        break; // End /lostitems case

    case '/reportitem':

        echo $twig->render('reportitem.html.twig', []);
        break;

    case '/viewitem':
        // Fetch the ID from the URL
        $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$itemId) {
            http_response_code(404);
            echo $twig->render('404.html.twig');
            break;
        }

        try {
            // Query with JOINs for the single item
            $sql = "SELECT
                        li.item_id AS id,
                        li.item_name,
                        li.item_description,
                        li.item_image_url,
                        li.item_status,
                        li.found_location,
                        li.reported_at,
                        c.category_name,
                        r.first_name AS reporter_first_name,
                        r.last_name AS reporter_last_name
                    FROM
                        lost_items li
                    LEFT JOIN
                        categories c ON li.category_id = c.category_id
                    LEFT JOIN
                        reporters r ON li.reporter_id = r.reporter_id
                    WHERE
                        li.item_id = :item_id"; // Filter by the specific item_id

            $stmt = $conn->prepare($sql);
            $stmt->execute([':item_id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_OBJ);

            if ($item) {
                echo $twig->render('viewitem.html.twig', [
                    'item' => $item
                ]);
            } else {
                // No item found with that ID
                http_response_code(404);
                echo $twig->render('404.html.twig');
            }
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            echo $twig->render('error.html.twig', ['message' => 'Could not retrieve item details.']);
        }
        break;

    default:
        http_response_code(404);
        echo $twig->render('404.html.twig');
        break;
}
