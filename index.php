<?php
require "config/config.php";
require_once 'vendor/autoload.php';

session_start();

// --- Basic Setup ---
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

// Define the base path of your project.
define('BASE_PATH', '/LostAndFoundManagementSystem');
define('BASE_ADMIN_PATH', '/admin/dashboard');

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
        try {
            $sql = "SELECT
                    li.item_id AS id,
                    li.item_name,
                    li.item_image_url,
                    li.reported_at,
                    li.found_location
                FROM
                    lost_items li
                ORDER BY
                    li.reported_at DESC
                LIMIT 4";

            $stmt = $conn->query($sql);

            if ($stmt === false) {
                $errorInfo = $conn->errorInfo();
                error_log("Homepage Query Failed: " . $errorInfo[2]);
                $recentItems = [];
            } else {
                $recentItems = $stmt->fetchAll(PDO::FETCH_OBJ);
            }

            echo $twig->render('home.html.twig', [
                'recentItems' => $recentItems
            ]);
        } catch (PDOException $e) {
            error_log("Database Error on Homepage: " . $e->getMessage());
            echo $twig->render('home.html.twig', [
                'recentItems' => [],
                'error_message' => 'Could not load recent items.'
            ]);
        }
        break;

    case '/lostitems':
        try {
            $itemsPerPage = 12;
            $currentPage = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);

            $searchTerm = trim(filter_input(INPUT_GET, 'search_query', FILTER_SANITIZE_STRING) ?: '');
            $category = filter_input(INPUT_GET, 'category_filter', FILTER_SANITIZE_STRING) ?: 'all';


            $whereClauses = [];
            $params = [];

            if (!empty($searchTerm)) {
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

            $countSql = "SELECT COUNT(*)
                         FROM lost_items li
                         LEFT JOIN categories c ON li.category_id = c.category_id
                         {$whereSql}";

            $countStmt = $conn->prepare($countSql);
            $countStmt->execute($params);
            $totalItems = $countStmt->fetchColumn();
            $totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;


            $currentPage = min($currentPage, $totalPages);
            if ($currentPage < 1) $currentPage = 1;
            $offset = ($currentPage - 1) * $itemsPerPage;


            $sql = "SELECT li.item_id AS id, li.item_name, li.item_description, li.item_image_url,
                           li.item_status, li.found_location, li.reported_at, c.category_name,
                           r.first_name AS reporter_first_name, r.last_name AS reporter_last_name, r.student_id
                    FROM lost_items li
                    LEFT JOIN categories c ON li.category_id = c.category_id
                    LEFT JOIN reporters r ON li.reporter_id = r.reporter_id
                    {$whereSql}  
                    ORDER BY li.reported_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $conn->prepare($sql);


            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
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
        break;

    case '/reportitem':
        // Handle POST request (form submission)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // --- FIX: Initialize variables used later ---
            $reporterStudentId = null;
            $reporterEmail = null;
            $uploadedImagePath = null;
            $reporterId = null;
            $formErrors = []; // Array to hold validation errors
            // --- End Initialization ---

            // --- 1. Define Upload Directory and Allowed Types ---
            $uploadDir = 'item_images/';
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            $maxFileSize = 2 * 1024 * 1024; // 2 MB

            // --- 2. Get Form Data (Sanitize!) ---
            $itemName = filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING);
            $itemDesc = filter_input(INPUT_POST, 'item_description', FILTER_SANITIZE_STRING);
            $itemStatus = filter_input(INPUT_POST, 'itemStatus', FILTER_SANITIZE_STRING) ?: 'found';
            $foundLocation = filter_input(INPUT_POST, 'location_found', FILTER_SANITIZE_STRING);
            $categoryId = filter_input(INPUT_POST, 'category', FILTER_VALIDATE_INT);
            $reporterFName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
            $reporterLName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
            // Assign potentially null/false values from filter_input
            $reporterStudentId = filter_input(INPUT_POST, 'student_id', FILTER_SANITIZE_STRING);
            $reporterEmail = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $reporterContact = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);


            // --- 3. Validate Form Data ---
            // (Ensure these checks handle potential null/false from filter_input if needed)
            if (empty($itemName)) $formErrors[] = "Item name is required.";
            if (empty($itemDesc)) $formErrors[] = "Item description is required.";
            if (empty($foundLocation)) $formErrors[] = "Location found is required.";
            if ($categoryId === false || $categoryId === null) $formErrors[] = "Invalid or missing category selected.";
            if (empty($reporterFName)) $formErrors[] = "Reporter first name is required.";
            if (empty($reporterLName)) $formErrors[] = "Reporter last name is required.";
            if (empty($reporterStudentId)) $formErrors[] = "Reporter student ID is required."; // Check if empty after assignment
            if ($reporterEmail === false) $formErrors[] = "Invalid reporter email address."; // Check for false explicitly
            if ($reporterEmail === null) $formErrors[] = "Reporter email is required."; // Check for null if not submitted
            if (empty($reporterContact)) $formErrors[] = "Reporter contact number is required.";


            // --- 4. Handle File Upload ---
            // ... (File upload logic - use the previously corrected version) ...
            $fileExtension = ''; // Initialize
            if (isset($_FILES['itemImage']) && $_FILES['itemImage']['error'] === UPLOAD_ERR_OK) {
                // ... (get tmp_name, name, size, type) ...
                $fileName = $_FILES['itemImage']['name'];
                $fileTmpPath = $_FILES['itemImage']['tmp_name'];
                $fileSize = $_FILES['itemImage']['size'];

                $fileNameCmps = explode(".", $fileName);
                if (count($fileNameCmps) > 1 && $fileNameCmps[0] !== '') {
                    $fileExtension = strtolower(end($fileNameCmps));
                } else {
                    $formErrors[] = "Invalid filename or file has no extension.";
                }

                // Validation (only if extension likely found and no prior errors)
                if (empty($formErrors) && !empty($fileExtension)) {
                    if (!in_array($fileExtension, $allowedTypes)) {
                        $formErrors[] = "Invalid file type.";
                    }
                } elseif (empty($formErrors) && empty($fileExtension)) {
                    if (!in_array("Invalid filename or file has no extension.", $formErrors)) {
                        $formErrors[] = "Could not determine file extension.";
                    }
                }
                if ($fileSize > $maxFileSize) {
                    $formErrors[] = "File size exceeds 2MB.";
                }

                // Move file if validation passed
                if (empty($formErrors)) {
                    // ... (generate unique name, destPath, mkdir, move_uploaded_file) ...
                    $newFileName = uniqid('', true) . '.' . $fileExtension;
                    $destPath = $uploadDir . $newFileName;
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0777, true)) {
                            $formErrors[] = "Failed to create upload directory.";
                        }
                    }
                    if (is_dir($uploadDir) && move_uploaded_file($fileTmpPath, $destPath)) {
                        $uploadedImagePath = $destPath;
                    } else { /* ... error moving file ... */
                        if (!is_dir($uploadDir)) {
                            $formErrors[] = "Upload directory missing.";
                        } else {
                            $formErrors[] = "Error moving file.";
                        }
                    }
                }
            } elseif (!isset($_FILES['itemImage']) || $_FILES['itemImage']['error'] !== UPLOAD_ERR_NO_FILE) {
                $formErrors[] = "File upload error: Code " . ($_FILES['itemImage']['error'] ?? 'Unknown');
            } else {
                $formErrors[] = "Item image is required.";
            }
            // --- End File Upload ---


            // --- 5. Process Reporter (Get ID or Insert New) ---
            if (empty($formErrors)) {
                try {
                    // Check if reporter exists - Ensure variables are not null/false before using
                    $checkSql = "SELECT reporter_id FROM reporters WHERE student_id = :student_id OR email = :email LIMIT 1";
                    $checkStmt = $conn->prepare($checkSql);

                    // Check if email is valid before executing
                    if ($reporterEmail === false || $reporterStudentId === null) {
                        // This case should ideally be caught by validation above, but as a safeguard:
                        if ($reporterEmail === false) $formErrors[] = "Invalid email format for reporter lookup.";
                        if ($reporterStudentId === null) $formErrors[] = "Student ID missing for reporter lookup.";
                        // Re-render form with errors
                        echo $twig->render('reportitem.html.twig', [
                            'errors' => $formErrors,
                            'old' => $_POST
                        ]);
                        exit; // Stop execution before trying to execute query with invalid data
                    }

                    // Execute only if email/studentId seem valid at this point
                    $checkStmt->execute([':student_id' => $reporterStudentId, ':email' => $reporterEmail]); // This was likely line 260
                    $existingReporter = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingReporter) {
                        $reporterId = $existingReporter['reporter_id'];
                    } else {
                        // Insert new reporter
                        $insertRepSql = "INSERT INTO reporters (first_name, last_name, student_id, email, contact_number) VALUES (:fname, :lname, :sid, :email, :contact)";
                        $insertRepStmt = $conn->prepare($insertRepSql);
                        $insertRepStmt->execute([
                            ':fname' => $reporterFName,
                            ':lname' => $reporterLName,
                            ':sid' => $reporterStudentId, // Make sure this isn't null/false
                            ':email' => $reporterEmail,  // Make sure this isn't null/false
                            ':contact' => $reporterContact
                        ]);
                        $reporterId = $conn->lastInsertId();
                    }
                } catch (PDOException $e) {
                    error_log("Reporter DB Error: " . $e->getMessage());
                    $formErrors[] = "Database error processing reporter information.";
                }
            }
            // --- End Reporter Processing ---


            // --- 6. Insert Item if No Errors ---
            if (empty($formErrors) && $reporterId !== null && $uploadedImagePath !== null) {
                try {
                    // --- GENERATE UNIQUE QR CODE DATA ---
                    // Using uniqid() is simple. Prefix helps identify it.
                    // For more robust uniqueness, consider UUID libraries if needed.
                    $qrCodeData = uniqid('item_qr_', true);
                    // --- END GENERATION ---

                    // --- MODIFIED INSERT SQL ---
                    // Added 'qr_code_data' column and ':qr_code' placeholder
                    $insertSql = "INSERT INTO lost_items (
                                item_name, 
                                item_description, 
                                item_image_url, 
                                item_status, 
                                found_location, 
                                category_id, 
                                reporter_id, 
                                qr_code_data,  -- Added column
                                reported_at
                              ) VALUES (
                                :name, 
                                :desc, 
                                :img_path, 
                                :status, 
                                :loc, 
                                :cat_id, 
                                :rep_id, 
                                :qr_code,     -- Added placeholder
                                NOW()
                              )";
                    // --- END MODIFIED SQL ---

                    $insertStmt = $conn->prepare($insertSql);

                    // --- MODIFIED EXECUTE ARRAY ---
                    // Added ':qr_code' binding
                    $insertStmt->execute([
                        ':name' => $itemName,
                        ':desc' => $itemDesc,
                        ':img_path' => $uploadedImagePath,
                        ':status' => $itemStatus,
                        ':loc' => $foundLocation,
                        ':cat_id' => $categoryId,
                        ':rep_id' => $reporterId,
                        ':qr_code' => $qrCodeData // Bind the generated unique value
                    ]);
                    // --- END MODIFIED EXECUTE ---

                    header('Location: ' . BASE_PATH . '/lostitems?status=reported_ok');
                    exit;
                } catch (PDOException $e) {
                    error_log("Item Insert Error: " . $e->getMessage());
                    $formErrors[] = 'Could not save item report.';
                    // !!! ADD THIS LINE FOR DEBUGGING !!!
                    $formErrors[] = 'Database Detail: ' . htmlspecialchars($e->getMessage());
                    // !!! REMOVE OR COMMENT OUT ABOVE LINE IN PRODUCTION !!!

                    // if ($uploadedImagePath && file_exists($uploadedImagePath)) { unlink($uploadedImagePath); }
                }
            }

            // --- 7. If Errors, Re-render Form ---
            if (!empty($formErrors)) {
                echo $twig->render('reportitem.html.twig', [
                    'errors' => $formErrors,
                    'old' => $_POST
                ]);
            }
        } else {
            // Handle GET request (show blank form)
            echo $twig->render('reportitem.html.twig');
        }
        break;

    case '/viewitem':

        $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$itemId) {
            http_response_code(404);
            echo $twig->render('404.html.twig');
            break;
        }

        try {

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
                        li.item_id = :item_id";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':item_id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_OBJ);

            if ($item) {
                echo $twig->render('viewitem.html.twig', [
                    'item' => $item
                ]);
            } else {
                http_response_code(404);
                echo $twig->render('404.html.twig');
            }
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            echo $twig->render('error.html.twig', ['message' => 'Could not retrieve item details.']);
        }
        break;

    case '/admin':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = filter_input(INPUT_POST, 'username');
            $password = filter_input(INPUT_POST, 'password');
            $rememberMe = isset($_POST['rememberMe']); // Check if checkbox was checked


            if ($username === 'admin' && $password === 'admin123') {

                session_regenerate_id(true);
                $_SESSION['is_admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;


                if ($rememberMe) {

                    $lifetime = 86400 * 30;
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        session_id(),
                        time() + $lifetime,
                        $params["path"],
                        $params["domain"],
                        $params["secure"],
                        $params["httponly"]
                    );
                }
                header('Location: ' . BASE_PATH . '/admin/dashboard');
                exit;
            } else {
                $error = "Invalid username or password.";
                echo $twig->render("admin.html.twig", ['error' => $error]);
            }
        } else {
            if (isset($_SESSION['is_admin_logged_in']) && $_SESSION['is_admin_logged_in'] === true) {
                header('Location: ' . BASE_PATH . '/admin/dashboard');
                exit;
            }
            echo $twig->render("admin.html.twig");
        }
        break;

    case '/logout':
        session_unset();
        session_destroy();

        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );


        header('Location: ' . BASE_PATH . '/admin?status=logged_out');
        exit;
        break;

    case '/admin/dashboard':
        // --- PROTECTED ROUTE: Check if logged in ---
        if (!isset($_SESSION['is_admin_logged_in']) || $_SESSION['is_admin_logged_in'] !== true) {
            header('Location: ' . BASE_PATH . '/admin?status=auth_required');
            exit;
        }

        // Initialize variables
        $totalItems = 0;
        $foundItems = 0;
        $claimedItems = 0;
        $archivedItems = 0;
        $statusChartData = ['labels' => [], 'data' => []];
        $monthlyChartData = ['labels' => [], 'data' => []];
        $categoryChartData = ['labels' => [], 'data' => []];
        $errorMessage = null;

        try {
            // --- 1. Fetch Counts for Summary Cards ---
            // (Your existing count queries remain here)
            $totalItemsStmt = $conn->query("SELECT COUNT(*) FROM lost_items");
            $totalItems = $totalItemsStmt ? $totalItemsStmt->fetchColumn() : 0;
            $foundItemsStmt = $conn->query("SELECT COUNT(*) FROM lost_items WHERE item_status = 'found'");
            $foundItems = $foundItemsStmt ? $foundItemsStmt->fetchColumn() : 0;
            $claimedItemsStmt = $conn->query("SELECT COUNT(*) FROM lost_items WHERE item_status = 'claimed'");
            $claimedItems = $claimedItemsStmt ? $claimedItemsStmt->fetchColumn() : 0;
            $archivedItemsStmt = $conn->query("SELECT COUNT(*) FROM lost_items WHERE item_status = 'archived'");
            $archivedItems = $archivedItemsStmt ? $archivedItemsStmt->fetchColumn() : 0;


            // --- 2. Prepare Data for Charts ---

            // Status Distribution
            $statusChartData = [
                'labels' => ['Found', 'Claimed', 'Archived'],
                'data' => [(int)$foundItems, (int)$claimedItems, (int)$archivedItems]
            ];

            // ==========================================================
            // == CORRECTED: Fetch Actual Monthly Reports Data ==
            // ==========================================================
            $monthlyLabels = [];
            $monthlyData = [];
            $resultsMap = []; // Initialize map to store counts keyed by month label

            // Generate labels for the last 12 months using PHP date format 'M y' (e.g., 'Oct 25')
            $phpMonthFormat = 'M y';
            for ($i = 11; $i >= 0; $i--) {
                $monthLabel = date($phpMonthFormat, strtotime("-$i months"));
                $monthlyLabels[] = $monthLabel;
                $resultsMap[$monthLabel] = 0; // Initialize count for this label to 0
            }

            // Query to get counts grouped by month (YYYY-MM format) for the last 12 months
            $sqlMonthFormat = '%Y-%m'; // SQL format for grouping
            $monthlySql = "SELECT
                               DATE_FORMAT(reported_at, '{$sqlMonthFormat}') AS report_month_sql,
                               COUNT(*) AS count
                           FROM lost_items
                           WHERE reported_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                             AND reported_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) -- Ensure we include today up to the end
                           GROUP BY report_month_sql
                           ORDER BY report_month_sql ASC"; // Order by the SQL date format
            $monthlyStmt = $conn->query($monthlySql);

            if ($monthlyStmt) {
                $monthlyResults = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all rows

                // Fill the map with actual counts from the database
                foreach ($monthlyResults as $row) {
                    // Convert the SQL 'YYYY-MM' back to the PHP label format 'M y'
                    $labelMonth = date($phpMonthFormat, strtotime($row['report_month_sql'] . '-01'));
                    // Check if this month label exists in our map and update its count
                    if (isset($resultsMap[$labelMonth])) {
                        $resultsMap[$labelMonth] = (int)$row['count'];
                    }
                }

                // Final data uses the generated labels and the counts from the map (in the correct order)
                $monthlyChartData = [
                    'labels' => $monthlyLabels, // Use the generated labels array ('Oct 24', 'Nov 24', ...)
                    'data' => array_values($resultsMap) // Get the counts (including zeros for months with no data)
                ];
            } else {
                // Handle query failure
                error_log("Failed to get monthly report data. Error: " . implode(" ", $conn->errorInfo()));
                $errorMessage = ($errorMessage ? $errorMessage . " | " : "") . "Failed to load monthly chart data.";
                // monthlyChartData remains ['labels' => [], 'data' => []]
            }
            // ==========================================================
            // == END CORRECTION ==
            // ==========================================================


            // Category Distribution Data (Fetch from DB - Assuming this part works)
            $categorySql = "SELECT COALESCE(c.category_name, 'Uncategorized') AS category_name, COUNT(li.item_id) AS count
                            FROM lost_items li
                            LEFT JOIN categories c ON li.category_id = c.category_id
                            GROUP BY COALESCE(c.category_name, 'Uncategorized')
                            ORDER BY count DESC";
            $categoryStmt = $conn->query($categorySql);

            if ($categoryStmt) {
                $categoryResults = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($categoryResults !== false) {
                    $categoryChartData = [
                        'labels' => array_column($categoryResults, 'category_name'),
                        'data' => array_map('intval', array_column($categoryResults, 'count'))
                    ];
                } // else keep default empty
            } else {
                // Handle query failure
                error_log("Failed to get category distribution. Error: " . implode(" ", $conn->errorInfo()));
                $errorMessage = ($errorMessage ? $errorMessage . " | " : "") . "Failed to load category chart data.";
                // categoryChartData remains ['labels' => [], 'data' => []]
            }
        } catch (PDOException $e) {
            $errorMessage = 'Could not load dashboard data due to a database error.';
            error_log("Admin Dashboard DB Error: " . $e->getMessage());
            // Use default values initialized earlier
        } catch (Exception $e) {
            $errorMessage = 'An unexpected error occurred.';
            error_log("Admin Dashboard General Error: " . $e->getMessage());
        }

        // --- Render Template ---
        echo $twig->render('admin_dashboard.html.twig', [
            'totalItems' => $totalItems,
            'foundItems' => $foundItems,
            'claimedItems' => $claimedItems,
            'archivedItems' => $archivedItems,
            'statusChartJson' => json_encode($statusChartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_NUMERIC_CHECK),
            'monthlyChartJson' => json_encode($monthlyChartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_NUMERIC_CHECK),
            'categoryChartJson' => json_encode($categoryChartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_NUMERIC_CHECK),
            'error_message' => $errorMessage
        ]);

        break; // End /admin/dashboard case

    case '/admin/items':
        // --- PROTECTED ROUTE: Check if admin is logged in ---
        if (!isset($_SESSION['is_admin_logged_in']) || $_SESSION['is_admin_logged_in'] !== true) {
            header('Location: ' . BASE_PATH . '/admin?status=auth_required');
            exit;
        }

        // Initialize variables
        $itemsForCurrentPage = [];
        $currentPage = 1;
        $totalPages = 1;
        $errorMessage = null;
        $filterParams = []; // To store current filter values for pagination links

        try {
            // --- Get Parameters (Search, Filter, Page) ---
            $itemsPerPage = 10; // Adjust items per page for admin view if needed
            $currentPage = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
            $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?: ''); // Assuming input name="search"
            $statusFilter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?: 'all'; // Assuming select name="status"
            $categoryFilter = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 'all'; // Assuming select name="category_id"

            // Store active filters for pagination links
            if (!empty($searchTerm)) $filterParams['search'] = $searchTerm;
            if ($statusFilter !== 'all') $filterParams['status'] = $statusFilter;
            if ($categoryFilter !== 'all') $filterParams['category_id'] = $categoryFilter;


            // --- Build WHERE Clause Dynamically ---
            $whereClauses = [];
            $params = []; // Parameters for prepared statement

            if (!empty($searchTerm)) {
                // Search in item name, description, location, reporter name/student_id
                $whereClauses[] = "(li.item_name LIKE :search OR li.item_description LIKE :search OR li.found_location LIKE :search OR r.first_name LIKE :search OR r.last_name LIKE :search OR r.student_id LIKE :search)";
                $params[':search'] = '%' . $searchTerm . '%';
            }

            if ($statusFilter !== 'all' && in_array($statusFilter, ['found', 'claimed', 'archived'])) {
                $whereClauses[] = "li.item_status = :status";
                $params[':status'] = $statusFilter;
            }

            if ($categoryFilter !== 'all' && $categoryFilter > 0) {
                $whereClauses[] = "li.category_id = :category_id";
                $params[':category_id'] = $categoryFilter;
            }

            $whereSql = '';
            if (!empty($whereClauses)) {
                $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
            }
            // --- End Building WHERE Clause ---

            // --- Get Total Filtered Item Count ---
            $countSql = "SELECT COUNT(li.item_id)
                         FROM lost_items li
                         LEFT JOIN categories c ON li.category_id = c.category_id
                         LEFT JOIN reporters r ON li.reporter_id = r.reporter_id
                         {$whereSql}"; // Apply filters

            $countStmt = $conn->prepare($countSql);
            $countStmt->execute($params);
            $totalItems = $countStmt->fetchColumn();
            $totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;

            // Ensure current page is valid
            $currentPage = min($currentPage, $totalPages);
            if ($currentPage < 1) $currentPage = 1;
            $offset = ($currentPage - 1) * $itemsPerPage;

            // --- Fetch Filtered Items for Current Page ---
            $sql = "SELECT li.item_id AS id, li.item_name, li.item_description, li.item_image_url,
                           li.item_status, li.found_location, li.reported_at, c.category_name,
                           r.first_name AS reporter_first_name, r.last_name AS reporter_last_name, r.student_id
                    FROM lost_items li
                    LEFT JOIN categories c ON li.category_id = c.category_id
                    LEFT JOIN reporters r ON li.reporter_id = r.reporter_id
                    {$whereSql}
                    ORDER BY li.reported_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $conn->prepare($sql);

            // Bind filter parameters
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            // Bind pagination parameters
            $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            if ($stmt === false) {
                $errorInfo = $stmt->errorInfo();
                throw new PDOException("Item Query Failed: " . $errorInfo[2]);
            }

            $itemsForCurrentPage = $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $errorMessage = 'Could not load item list due to a database error.';
            error_log("Admin Items Error: " . $e->getMessage());
            // Use default empty values initialized earlier
        } catch (Exception $e) {
            $errorMessage = 'An unexpected error occurred while loading items.';
            error_log("Admin Items General Error: " . $e->getMessage());
        }

        // --- Render Template ---
        echo $twig->render('admin_items.html.twig', [
            'items' => $itemsForCurrentPage,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'filters' => $filterParams, // Pass current filters back to Twig for pagination links
            'error_message' => $errorMessage
        ]);

        break; // End /admin/items case

    case '/admin/categories':

        echo $twig->render("admin_categories.html.twig", []);

        break;

    case '/admin/claims':

        echo $twig->render("admin_claims.html.twig", []);

        break;

    case '/admin/activity':

        echo $twig->render("admin_activity.html.twig", []);

        break;

    default:
        http_response_code(404);
        echo $twig->render('404.html.twig');
        break;
}
