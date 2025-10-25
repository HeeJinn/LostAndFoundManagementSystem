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
        $items = $conn->query("SELECT * FROM item");
        $items->execute();
        $allItems = $items->fetchAll(PDO::FETCH_OBJ);

        echo $twig->render('forum.html.twig', [
            'items' => $allItems,
        ]);
        break;

    case '/reportitem':

        echo $twig->render('reportitem.html.twig', []);
        break;

    case '/viewitem':
        $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$itemId) {
            http_response_code(404);
            echo $twig->render('404.html.twig');
            break; 
        }

        try {
            $stmt = $conn->prepare("SELECT * FROM item WHERE id = :id");
            $stmt->execute([':id' => $itemId]);
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
            http_response_code(500); 
            echo $twig->render('error.html.twig', ['message' => 'Could not retrieve item details.']);
        }
        break;

    default:
        http_response_code(404);
        echo $twig->render('404.html.twig');
        break;
}
